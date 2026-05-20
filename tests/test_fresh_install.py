# These tests require real REDCap community portal credentials.
# Run locally before each release. See tests/README.md.
#
# The actual download + install happens once in the session-scoped
# installed_a_snapshot fixture; this test verifies the resulting state without
# re-downloading, satisfying the "download once per run" constraint.

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
