# These tests require real REDCap community portal credentials.
# Run locally before each release. See tests/README.md.
#
# Both tests restore from the session-scoped snapshot instead of doing a full
# install, then exercise the redcap-upgrade CLI exec'd into the running container.
# The non-interactive upgrade test still downloads version B — unavoidable.

import pytest

from conftest import (
    B_ZIP_MOUNT,
    BOOT_TIMEOUT_FAST,
    REDCAP_COMMUNITY_PASSWORD,
    REDCAP_COMMUNITY_USER,
    REDCAP_TEST_UPGRADE_VERSION,
    REDCAP_TEST_VERSION,
    RedcapStack,
)

_CREDENTIALS = {
    "REDCAP_COMMUNITY_USER": REDCAP_COMMUNITY_USER,
    "REDCAP_COMMUNITY_PASSWORD": REDCAP_COMMUNITY_PASSWORD,
}


@pytest.mark.timeout(300)
def test_upgrade_dry_run(
    redcap_stack: RedcapStack,
    installed_a_snapshot: tuple[str, bytes],
) -> None:
    """
    redcap-upgrade --version B --dry-run must exit 0, print the upgrade plan,
    and leave the database version unchanged at A.
    """
    vol, dump = installed_a_snapshot
    redcap_stack.start_from_snapshot(
        vol, dump,
        {"REDCAP_VERSION": REDCAP_TEST_VERSION, **_CREDENTIALS},
    )
    redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)

    exit_code, output = redcap_stack.exec_run(
        f"redcap-upgrade --version {REDCAP_TEST_UPGRADE_VERSION} --dry-run"
    )

    assert exit_code == 0, (
        f"redcap-upgrade --dry-run exited {exit_code}.\nOutput:\n{output}"
    )
    assert "[DRY RUN]" in output, (
        f"Expected '[DRY RUN]' marker in output.\nOutput:\n{output}"
    )

    db_version = redcap_stack.get_redcap_version()
    assert db_version == REDCAP_TEST_VERSION, (
        f"Dry-run must not change DB version. Expected {REDCAP_TEST_VERSION!r}, "
        f"got {db_version!r}"
    )


@pytest.mark.timeout(1800)
def test_upgrade_noninteractive(
    redcap_stack: RedcapStack,
    installed_a_snapshot: tuple[str, bytes],
    b_zip_volume: tuple[str, str],
) -> None:
    """
    redcap-upgrade --zip <pre-downloaded B zip> must complete successfully,
    update the DB to version B, and create a backup file.
    The zip is downloaded once per session by the b_zip_volume fixture.
    """
    vol, dump = installed_a_snapshot
    bzip_vol_name, zip_path = b_zip_volume
    redcap_stack.start_from_snapshot(
        vol, dump,
        {"REDCAP_VERSION": REDCAP_TEST_VERSION, **_CREDENTIALS},
        extra_volumes={bzip_vol_name: B_ZIP_MOUNT},
    )
    redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)

    exit_code, output = redcap_stack.exec_run(
        f"redcap-upgrade --zip {zip_path} --version {REDCAP_TEST_UPGRADE_VERSION} --no-offline"
    )

    assert exit_code == 0, (
        f"redcap-upgrade exited {exit_code}.\nOutput:\n{output}"
    )

    db_version = redcap_stack.get_redcap_version()
    assert db_version == REDCAP_TEST_UPGRADE_VERSION, (
        f"Expected DB version={REDCAP_TEST_UPGRADE_VERSION!r} after upgrade, "
        f"got {db_version!r}"
    )

    backups = redcap_stack.list_backups()
    assert backups, "No backup files found in /opt/redcap-docker/backups/ after upgrade"
    assert any(
        REDCAP_TEST_VERSION in f or REDCAP_TEST_UPGRADE_VERSION in f for f in backups
    ), f"Backup filename {backups[0]!r} doesn't reference either version"


@pytest.mark.timeout(1800)
def test_upgrade_applies_production_safe_permissions(
    redcap_stack: RedcapStack,
    installed_a_snapshot: tuple[str, bytes],
    b_zip_volume: tuple[str, str],
) -> None:
    """
    Regression test for issue #10.

    With the default hardening (FIX_REDCAP_DIR_PERMISSIONS=true,
    REDCAP_EASY_UPGRADE_ENABLE=false), an in-place upgrade must leave the newly
    installed version directory with the same production-safe permissions the
    startup script enforces (root:www-data, 750 dirs / 640 files) — see
    SECURITY.md. The bug installed it as www-data:www-data with world-readable
    755 dirs, so www-data owned/could write the running webroot and the code
    was world-readable until the next container restart.
    """
    vol, dump = installed_a_snapshot
    bzip_vol_name, zip_path = b_zip_volume
    redcap_stack.start_from_snapshot(
        vol, dump,
        {"REDCAP_VERSION": REDCAP_TEST_VERSION, **_CREDENTIALS},
        extra_volumes={bzip_vol_name: B_ZIP_MOUNT},
    )
    redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)

    exit_code, output = redcap_stack.exec_run(
        f"redcap-upgrade --zip {zip_path} --version {REDCAP_TEST_UPGRADE_VERSION} --no-offline"
    )
    assert exit_code == 0, f"redcap-upgrade exited {exit_code}.\nOutput:\n{output}"
    assert redcap_stack.get_redcap_version() == REDCAP_TEST_UPGRADE_VERSION, (
        "Upgrade did not update the DB version — cannot trust the permission check."
    )

    new_dir = f"/var/www/html/redcap_v{REDCAP_TEST_UPGRADE_VERSION}"
    owner, group, mode = redcap_stack.stat_path(new_dir)

    # Owner must be root (webroot is read-only for www-data in the default model).
    assert owner == "root", (
        f"{new_dir} owner is {owner!r}, expected 'root'. "
        f"www-data ownership means the web-server user can write the running code (issue #10)."
    )
    assert group == "www-data", (
        f"{new_dir} group is {group!r}, expected 'www-data' so Apache can read it."
    )
    # 750: no world (other) bits — the directory must not be world-readable.
    assert mode == "750", (
        f"{new_dir} mode is {mode!r}, expected '750'. "
        f"A world-readable/world-executable webroot violates the production-safe model (issue #10)."
    )

    # Spot-check a regular file inside the new version: root:www-data, 640, not world-readable.
    f_owner, f_group, f_mode = redcap_stack.stat_path(f"{new_dir}/index.php")
    assert (f_owner, f_group, f_mode) == ("root", "www-data", "640"), (
        f"{new_dir}/index.php is {f_owner}:{f_group} {f_mode}, "
        f"expected root:www-data 640 (issue #10)."
    )
