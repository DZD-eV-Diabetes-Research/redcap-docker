# Integration tests for REDCAP_MODULES_WEB_INSTALL_ENABLE (issue #25).
#
# With the hardened webroot, REDCap's Control Center cannot install external
# modules: ExternalModules::downloadModule() creates "<modules>/<name>_tmp" as a
# lock and reports any mkdir() failure as "A duplicate request was detected".
# The opt-in makes only <webroot>/modules writable for www-data.
#
# All tests restore from the session snapshot. The end-to-end test additionally
# downloads one small module from the REDCap Repo (redcap.vumc.org), so it needs
# outbound internet access from the REDCap container, like the snapshot itself.

from __future__ import annotations

import base64

import pytest

from conftest import (
    BOOT_TIMEOUT_FAST,
    REDCAP_COMMUNITY_PASSWORD,
    REDCAP_COMMUNITY_USER,
    REDCAP_TEST_VERSION,
    RedcapStack,
)

_BASE_ENV = {
    "REDCAP_VERSION": REDCAP_TEST_VERSION,
    "REDCAP_COMMUNITY_USER": REDCAP_COMMUNITY_USER,
    "REDCAP_COMMUNITY_PASSWORD": REDCAP_COMMUNITY_PASSWORD,
}
_WEB_INSTALL_ENV = {**_BASE_ENV, "REDCAP_MODULES_WEB_INSTALL_ENABLE": "true"}

# The documented minimal capability set (SECURITY.md, issue #7). It omits FOWNER,
# so root can only chmod files it owns; the permission code must cope with that.
_DOC_CAP_DROP = ["ALL"]
_DOC_CAP_ADD = ["CHOWN", "DAC_OVERRIDE", "SETUID", "SETGID", "NET_BIND_SERVICE"]

WEBROOT = "/var/www/html"
MODULES_DIR = f"{WEBROOT}/modules"

# REDCap Repo module used for the end-to-end download: ROME, the module from the
# original report (folder "rome_v1.0.1").
REPO_MODULE_ID = 2915

DUPLICATE_REQUEST_MSG = "A duplicate request was detected"

# Runs REDCap's own module installer code as the calling user (www-data in the
# tests), exactly like the Control Center does, minus the admin check.
_EM_CLI_PHP = r"""<?php
require_once '/opt/redcap-docker/assets/scripts/startup-scripts/php_helpers/redcap_info.php';
require_once '/opt/redcap-docker/assets/scripts/startup-scripts/php_helpers/db.php';
$redcap_dir = get_installed_redcap_version_dir_path();
define('NOAUTH', true);
define('CRON', true);
define('USERID', 'SYSTEM');
require_once $redcap_dir . '/Config/init_global.php';
[$action, $arg] = [$argv[1], $argv[2]];
$result = $action === 'download'
    ? \ExternalModules\ExternalModules::downloadModule((int) $arg, true)
    : \ExternalModules\ExternalModules::deleteModuleDirectory($arg, true);
echo "EM_RESULT=" . $result . "\n";
"""
_EM_CLI_PATH = "/tmp/rctest_em_cli.php"


def _as_www_data(stack: RedcapStack, cmd: str) -> tuple[int, str]:
    return stack.exec_run(cmd, user="www-data")


def _em_cli(stack: RedcapStack, action: str, arg: str) -> str:
    """Run downloadModule/deleteModuleDirectory as www-data, return its result."""
    b64 = base64.b64encode(_EM_CLI_PHP.encode()).decode()
    code, out = stack.exec_run(
        f"echo '{b64}' | base64 -d > {_EM_CLI_PATH} && chmod 644 {_EM_CLI_PATH}"
    )
    assert code == 0, f"could not write the EM CLI helper: {out}"
    code, out = _as_www_data(stack, f"php -f {_EM_CLI_PATH} {action} {arg}")
    for line in out.splitlines():
        if line.startswith("EM_RESULT="):
            return line[len("EM_RESULT="):]
    raise AssertionError(f"EM CLI helper printed no result (exit {code}):\n{out}")


def _version_dir(stack: RedcapStack) -> str:
    code, out = stack.exec_run(f"ls -d {WEBROOT}/redcap_v* | head -1")
    assert code == 0 and out.strip(), f"no redcap_v* dir in the webroot: {out}"
    return out.strip()


@pytest.mark.timeout(300)
def test_module_web_install_disabled_by_default(
    redcap_stack: RedcapStack,
    installed_a_snapshot: tuple[str, bytes],
) -> None:
    """
    Default: modules/ is part of the read-only webroot, and the startup log
    explains what the misleading "duplicate request" message means.
    """
    vol, dump = installed_a_snapshot
    redcap_stack.start_from_snapshot(vol, dump, _BASE_ENV)
    logs = redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)

    assert "Web UI module installer is disabled" in logs, (
        "Expected the startup log to say the web UI module installer is disabled."
        f"\nLogs:\n{logs}"
    )
    assert DUPLICATE_REQUEST_MSG in logs, (
        "Expected the startup log to explain the 'duplicate request' error.\n"
        f"Logs:\n{logs}"
    )

    owner, group, mode = redcap_stack.stat_path(MODULES_DIR)
    assert (owner, group, mode) == ("root", "www-data", "750"), (
        f"modules/ must be root:www-data 750 by default, got {owner}:{group} {mode}"
    )
    code, out = _as_www_data(redcap_stack, f"mkdir {MODULES_DIR}/rctest_probe")
    assert code != 0, f"www-data must not be able to create a dir in modules/ by default: {out}"


@pytest.mark.timeout(300)
def test_module_web_install_enabled_only_modules_writable(
    redcap_stack: RedcapStack,
    installed_a_snapshot: tuple[str, bytes],
) -> None:
    """
    REDCAP_MODULES_WEB_INSTALL_ENABLE=true: www-data can write into modules/,
    but neither into a redcap_vX.Y.Z dir nor into the webroot itself. Runs under
    the documented minimal capability set (no FOWNER).
    """
    vol, dump = installed_a_snapshot
    redcap_stack.start_from_snapshot(
        vol, dump, _WEB_INSTALL_ENV, cap_drop=_DOC_CAP_DROP, cap_add=_DOC_CAP_ADD,
    )
    logs = redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)

    assert "SECURITY NOTE: REDCAP_MODULES_WEB_INSTALL_ENABLE=true" in logs, (
        f"Expected the web install security note in the startup log.\nLogs:\n{logs}"
    )
    assert "Operation not permitted" not in logs, (
        "A chmod/chown failed under the documented minimal capability set.\n"
        f"Logs:\n{logs}"
    )
    assert "permission operations failed" not in logs, f"Permission fix reported failures:\n{logs}"

    owner, group, mode = redcap_stack.stat_path(MODULES_DIR)
    assert (owner, group, mode) == ("www-data", "www-data", "750"), (
        f"modules/ must be www-data:www-data 750 in web install mode, got {owner}:{group} {mode}"
    )
    owner, group, mode = redcap_stack.stat_path(WEBROOT)
    assert (owner, group, mode) == ("root", "www-data", "750"), (
        f"The webroot must stay root:www-data 750, got {owner}:{group} {mode}"
    )

    code, out = _as_www_data(redcap_stack, f"mkdir {MODULES_DIR}/rctest_probe")
    assert code == 0, f"www-data must be able to create a dir in modules/: {out}"

    version_dir = _version_dir(redcap_stack)
    owner, _, _ = redcap_stack.stat_path(version_dir)
    assert owner == "root", f"{version_dir} must stay root-owned, got {owner!r}"
    code, out = _as_www_data(redcap_stack, f"mkdir {version_dir}/rctest_probe")
    assert code != 0, f"www-data must not be able to write into {version_dir}: {out}"
    code, out = _as_www_data(redcap_stack, f"touch {WEBROOT}/rctest_probe.php")
    assert code != 0, f"www-data must not be able to write into the webroot: {out}"

    # Module provisioning places files as root and must hand them to www-data
    # in this mode, under the same minimal capabilities. A symlink in the tree
    # must not be followed: its root-owned target has to stay untouched.
    code, out = redcap_stack.exec_run(
        "mkdir -p /tmp/rctest_place/sub && echo x > /tmp/rctest_place/sub/f.php "
        "&& chmod 777 /tmp/rctest_place/sub && chmod 666 /tmp/rctest_place/sub/f.php "
        "&& echo secret > /tmp/rctest_target && chmod 600 /tmp/rctest_target "
        "&& ln -s /tmp/rctest_target /tmp/rctest_place/link "
        "&& php -r 'require \"/opt/redcap-docker/assets/scripts/startup-scripts/"
        "php_helpers/module_fetcher.php\"; module_apply_webroot_perms(\"/tmp/rctest_place\");' "
        "&& stat -c '%U %G %a' /tmp/rctest_place/sub /tmp/rctest_place/sub/f.php /tmp/rctest_target"
    )
    assert code == 0, f"module_apply_webroot_perms check failed: {out}"
    assert out.split() == [
        "www-data", "www-data", "750",
        "www-data", "www-data", "640",
        "root", "root", "600",
    ], (
        "Provisioned module files must end up www-data:www-data 750/640 in web "
        f"install mode, and a symlink target must stay root:root 600, got:\n{out}"
    )


@pytest.mark.timeout(300)
def test_module_web_install_relocked_after_disabling(
    redcap_stack: RedcapStack,
    installed_a_snapshot: tuple[str, bytes],
) -> None:
    """
    Switching the option back off must return modules/ and everything www-data
    wrote there to root:www-data read-only, under the minimal capability set.
    """
    vol, dump = installed_a_snapshot
    redcap_stack.start_from_snapshot(vol, dump, _WEB_INSTALL_ENV)
    redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)

    # www-data also plants a symlink to a root-owned file of the image and a
    # dangling one. Re-locking must change the links, never their targets.
    target = "/opt/redcap-docker/assets/scripts/healthcheck.sh"
    t_owner, t_group, t_mode = redcap_stack.stat_path(target)
    code, out = _as_www_data(
        redcap_stack,
        f"mkdir {MODULES_DIR}/rctest_probe && echo '<?php' > {MODULES_DIR}/rctest_probe/x.php "
        f"&& ln -s {target} {MODULES_DIR}/rctest_link "
        f"&& ln -s /nonexistent {MODULES_DIR}/rctest_dangling",
    )
    assert code == 0, f"setup: www-data could not write into modules/: {out}"

    redcap_stack.restart_redcap(_BASE_ENV, cap_drop=_DOC_CAP_DROP, cap_add=_DOC_CAP_ADD)
    logs = redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)
    assert "Operation not permitted" not in logs, f"Re-locking modules/ failed:\n{logs}"
    assert "permission operations failed" not in logs, f"Permission fix reported failures:\n{logs}"

    assert redcap_stack.stat_path(target) == (t_owner, t_group, t_mode), (
        f"Re-locking modules/ followed a symlink planted by www-data and changed {target} "
        f"(was {t_owner}:{t_group} {t_mode}, now {redcap_stack.stat_path(target)})"
    )

    for path, want_mode in (
        (MODULES_DIR, "750"),
        (f"{MODULES_DIR}/rctest_probe", "750"),
        (f"{MODULES_DIR}/rctest_probe/x.php", "640"),
    ):
        owner, group, mode = redcap_stack.stat_path(path)
        assert (owner, group, mode) == ("root", "www-data", want_mode), (
            f"{path} must be root:www-data {want_mode} after disabling, got {owner}:{group} {mode}"
        )
    code, out = _as_www_data(redcap_stack, f"touch {MODULES_DIR}/rctest_probe/y.php")
    assert code != 0, f"www-data must not write into modules/ after disabling: {out}"


@pytest.mark.timeout(420)
def test_module_web_install_repo_download_end_to_end(
    redcap_stack: RedcapStack,
    installed_a_snapshot: tuple[str, bytes],
) -> None:
    """
    Run REDCap's own ExternalModules::downloadModule() as www-data, as the
    Control Center does. Disabled: it fails with the misleading "duplicate
    request" message (the reported bug). Enabled: the module is downloaded and
    can be deleted again with deleteModuleDirectory().
    """
    vol, dump = installed_a_snapshot
    redcap_stack.start_from_snapshot(vol, dump, _BASE_ENV)
    redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)

    result = _em_cli(redcap_stack, "download", str(REPO_MODULE_ID))
    assert DUPLICATE_REQUEST_MSG in result, (
        "With the web UI installer disabled, downloadModule() should fail with "
        f"REDCap's 'duplicate request' message, got: {result!r}"
    )

    redcap_stack.restart_redcap(_WEB_INSTALL_ENV)
    redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)

    result = _em_cli(redcap_stack, "download", str(REPO_MODULE_ID))
    assert result == "success", f"downloadModule() as www-data failed: {result!r}"

    rows = redcap_stack.db_query(
        "SELECT module_name FROM redcap_external_modules_downloads "
        "WHERE module_id = %s ORDER BY time_downloaded DESC LIMIT 1",
        (REPO_MODULE_ID,),
    )
    assert rows, "downloadModule() reported success but recorded no download"
    folder = rows[0][0] if isinstance(rows[0], (tuple, list)) else rows[0]["module_name"]
    module_path = f"{MODULES_DIR}/{folder}"

    owner, _, _ = redcap_stack.stat_path(module_path)
    assert owner == "www-data", f"{module_path} should be owned by www-data, got {owner!r}"
    code, out = redcap_stack.exec_run(f"test -f {module_path}/config.json")
    assert code == 0, f"downloaded module has no config.json at {module_path}: {out}"
    code, out = redcap_stack.exec_run(f"ls -d {MODULES_DIR}/*_tmp 2>/dev/null")
    assert not out.strip(), f"downloadModule() left a lock directory behind: {out}"

    result = _em_cli(redcap_stack, "delete", folder)
    code, _ = redcap_stack.exec_run(f"test -e {module_path}")
    assert code != 0, f"deleteModuleDirectory() as www-data did not remove {module_path}: {result!r}"
