# These tests require real REDCap community portal credentials.
# Run locally before each release. See tests/README.md.
#
# Each test restores from the session-scoped installed_a_snapshot fixture
# (volume clone + DB restore, ~30s) instead of doing a full install each time.
# Only the auto-upgrade test downloads version B.

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
def test_reconciler_already_at_version(
    redcap_stack: RedcapStack,
    installed_a_snapshot: tuple[str, bytes],
) -> None:
    """
    Files for version A are already on the volume and REDCAP_VERSION=A.
    Reconciler must log 'nothing to do' and skip any download.
    """
    vol, dump = installed_a_snapshot
    redcap_stack.start_from_snapshot(
        vol, dump,
        {"REDCAP_VERSION": REDCAP_TEST_VERSION, **_CREDENTIALS},
    )

    logs = redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)

    assert "nothing to do" in logs, (
        f"Expected reconciler 'nothing to do' message.\nLogs:\n{logs}"
    )


@pytest.mark.timeout(300)
def test_reconciler_upgrade_warning_no_auto_upgrade(
    redcap_stack: RedcapStack,
    installed_a_snapshot: tuple[str, bytes],
) -> None:
    """
    Version A is installed, REDCAP_VERSION is set to B (> A), but
    REDCAP_AUTO_UPGRADE is not enabled.
    Reconciler must log a warning and leave the DB at version A.
    """
    vol, dump = installed_a_snapshot
    redcap_stack.start_from_snapshot(
        vol, dump,
        {
            "REDCAP_VERSION": REDCAP_TEST_UPGRADE_VERSION,
            # REDCAP_AUTO_UPGRADE intentionally absent (defaults to false)
            **_CREDENTIALS,
        },
    )

    logs = redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)

    assert "[RECONCILER] WARNING" in logs, (
        f"Expected reconciler warning about version mismatch.\nLogs:\n{logs}"
    )
    assert "REDCAP_AUTO_UPGRADE" in logs, (
        f"Expected reconciler to mention REDCAP_AUTO_UPGRADE in its warning.\nLogs:\n{logs}"
    )

    installed = redcap_stack.get_redcap_version()
    assert installed == REDCAP_TEST_VERSION, (
        f"DB version should still be {REDCAP_TEST_VERSION!r} (no upgrade ran), "
        f"got {installed!r}"
    )


@pytest.mark.timeout(1800)
def test_reconciler_auto_upgrade(
    redcap_stack: RedcapStack,
    installed_a_snapshot: tuple[str, bytes],
    b_zip_volume: tuple[str, str],
) -> None:
    """
    Version A is installed, REDCAP_VERSION=B, REDCAP_AUTO_UPGRADE=true.
    Reconciler uses the pre-downloaded B zip (via REDCAP_ZIP_PATH) to upgrade
    without hitting the community portal.
    """
    vol, dump = installed_a_snapshot
    bzip_vol_name, zip_path = b_zip_volume
    redcap_stack.start_from_snapshot(
        vol, dump,
        {
            "REDCAP_VERSION": REDCAP_TEST_UPGRADE_VERSION,
            "REDCAP_AUTO_UPGRADE": "true",
            "REDCAP_ZIP_PATH": zip_path,
            **_CREDENTIALS,
        },
        extra_volumes={bzip_vol_name: B_ZIP_MOUNT},
    )

    logs = redcap_stack.assert_booted(timeout=900)

    assert "REDCAP_AUTO_UPGRADE=true" in logs, (
        f"Expected reconciler auto-upgrade message.\nLogs:\n{logs}"
    )

    upgraded_version = redcap_stack.get_redcap_version()
    assert upgraded_version == REDCAP_TEST_UPGRADE_VERSION, (
        f"Expected DB version={REDCAP_TEST_UPGRADE_VERSION!r} after auto-upgrade, "
        f"got {upgraded_version!r}"
    )
