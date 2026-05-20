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
