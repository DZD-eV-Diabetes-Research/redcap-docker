# These tests require real REDCap community portal credentials.
# Run locally before each release. See tests/README.md.
#
# Both tests restore from the session-scoped snapshot. RCCONF_ vars and
# USER_PROV are applied by startup scripts on every boot, so they work
# correctly against a pre-installed DB without a full reinstall.

import json

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
def test_rcconf_vars_applied(
    redcap_stack: RedcapStack,
    installed_a_snapshot: tuple[str, bytes],
) -> None:
    """
    RCCONF_* vars must be written to the redcap_config table during the
    110-set_redcap_config.php startup step:
    - values containing quotes or '%' are stored verbatim
    - RCCONF_database_query_tool_enabled enables the Database Query Tool (#1)
    - values of secret fields are stored but never printed to the container log
    """
    secret = "SG.rcconf-test-secret-value"
    vol, dump = installed_a_snapshot
    redcap_stack.start_from_snapshot(
        vol, dump,
        {
            "REDCAP_VERSION": REDCAP_TEST_VERSION,
            "APPLY_RCCONF_VARIABLES": "true",
            "RCCONF_institution": "Test's Org 100%",
            "RCCONF_database_query_tool_enabled": "1",
            "RCCONF_sendgrid_api_key": secret,
            **_CREDENTIALS,
        },
    )

    logs = redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)

    institution = redcap_stack.get_config("institution")
    assert institution == "Test's Org 100%", (
        f"Expected institution=\"Test's Org 100%\" in redcap_config, got {institution!r}"
    )
    query_tool = redcap_stack.get_config("database_query_tool_enabled")
    assert query_tool == "1", (
        f"Expected database_query_tool_enabled='1' in redcap_config, got {query_tool!r}"
    )
    # REDCap encrypts sensitive settings in place on first load, so the stored
    # value is either our plaintext or its ciphertext; just make sure it landed.
    assert redcap_stack.get_config("sendgrid_api_key"), "sendgrid_api_key was not written"
    assert secret not in logs, "RCCONF secret value leaked into the container log"


@pytest.mark.timeout(300)
def test_user_provisioning(
    redcap_stack: RedcapStack,
    installed_a_snapshot: tuple[str, bytes],
) -> None:
    """
    USER_PROV with a user JSON must result in the user being present in
    redcap_user_information after the 130_user_provisioning.php startup step.
    """
    test_user = {
        "username": "provisioned_user",
        "user_firstname": "Prov",
        "user_lastname": "Isioned",
        "user_email": "provisioned@example.local",
        "password": "ChangeMe123!",
        "super_user": 0,
    }

    vol, dump = installed_a_snapshot
    redcap_stack.start_from_snapshot(
        vol, dump,
        {
            "REDCAP_VERSION": REDCAP_TEST_VERSION,
            "RCCONF_auth_meth_global": "table",
            "USER_PROV": json.dumps([test_user]),
            **_CREDENTIALS,
        },
    )

    redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)

    row = redcap_stack.get_user("provisioned_user")
    assert row is not None, "User 'provisioned_user' not found in redcap_user_information"
    assert row["user_firstname"] == "Prov"
    assert row["user_lastname"] == "Isioned"
    assert row["user_email"] == "provisioned@example.local"
