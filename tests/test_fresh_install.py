# These tests require real REDCap community portal credentials.
# Run locally before each release. See tests/README.md.
#
# The actual download + install happens once in the session-scoped
# installed_a_snapshot fixture; this test verifies the resulting state without
# re-downloading, satisfying the "download once per run" constraint.

import docker
import pytest

from conftest import (
    BOOT_TIMEOUT_FAST,
    REDCAP_COMMUNITY_PASSWORD,
    REDCAP_COMMUNITY_USER,
    REDCAP_TEST_VERSION,
    RedcapStack,
)

_CREDENTIALS = {
    "REDCAP_COMMUNITY_USER": REDCAP_COMMUNITY_USER,
    "REDCAP_COMMUNITY_PASSWORD": REDCAP_COMMUNITY_PASSWORD,
}


@pytest.mark.timeout(300)
def test_fresh_install_via_version_env(
    redcap_stack: RedcapStack,
    installed_a_snapshot: tuple[str, bytes],
) -> None:
    """
    REDCAP_VERSION + credentials with no pre-existing files triggers the reconciler
    to download and install REDCap. The installed_a_snapshot session fixture performs
    that exact fresh install once per session. This test boots from the resulting
    snapshot and verifies the post-install state (reconciler ran, DB populated).
    """
    vol, dump = installed_a_snapshot
    redcap_stack.start_from_snapshot(
        vol, dump,
        {
            "REDCAP_VERSION": REDCAP_TEST_VERSION,
            **_CREDENTIALS,
        },
    )

    logs = redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)

    assert "[RECONCILER]" in logs, "Reconciler did not run — check boot log"
    assert "nothing to do" in logs, (
        "Expected reconciler 'nothing to do' — version should already be installed in snapshot.\n"
        f"Logs:\n{logs}"
    )

    assert redcap_stack.table_exists("redcap_config"), (
        "redcap_config table missing — DB install did not run"
    )

    installed_version = redcap_stack.get_redcap_version()
    assert installed_version == REDCAP_TEST_VERSION, (
        f"Expected redcap_version={REDCAP_TEST_VERSION!r}, got {installed_version!r}"
    )


@pytest.mark.timeout(120)
def test_fresh_install_first_boot_webroot_is_read_only(
    docker_client: docker.DockerClient,
    installed_a_snapshot: tuple[str, bytes],
) -> None:
    """
    The snapshot volume is the webroot exactly as the fresh-install boot left
    it, before any restart. 90-fix_permissions.sh runs before the reconciler
    deploys the files, so the reconciler must apply the permissions again:
    otherwise Apache serves the very first boot with a webroot www-data can
    write to (root:www-data 750/640 is the target, www-data has UID/GID 33).
    """
    vol, _ = installed_a_snapshot
    out = docker_client.containers.run(
        "alpine",
        command=[
            "sh", "-c",
            "cd /v && f=$(ls -d redcap_v* | head -1) && "
            "stat -c '%n %u %g %a' . index.php \"$f\" \"$f/index.php\" modules temp",
        ],
        volumes={vol: {"bind": "/v", "mode": "ro"}},
        remove=True,
    ).decode()
    got = {}
    for line in out.strip().splitlines():
        name, uid, gid, mode = line.split()
        got[name] = (uid, gid, mode)
    version_dir = next(n for n in got if n.startswith("redcap_v") and "/" not in n)
    expected = {
        ".": ("0", "33", "750"),
        "index.php": ("0", "33", "640"),
        version_dir: ("0", "33", "750"),
        f"{version_dir}/index.php": ("0", "33", "640"),
        "modules": ("0", "33", "750"),
        "temp": ("33", "33", "750"),
    }
    for name, want in expected.items():
        assert got.get(name) == want, (
            f"After the fresh-install boot {name!r} must be uid/gid/mode {want}, "
            f"got {got.get(name)}.\nAll:\n{out}"
        )
