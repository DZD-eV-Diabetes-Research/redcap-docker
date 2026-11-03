# These tests require real REDCap community portal credentials.
# Run locally before each release. See tests/README.md.
#
# Both tests restore from the session-scoped snapshot. RCCONF_ vars and
# USER_PROV are applied by startup scripts on every boot, so they work
# correctly against a pre-installed DB without a full reinstall.

import json
import time
import uuid

import pytest

from conftest import (
    BOOT_TIMEOUT_FAST,
    REDCAP_COMMUNITY_PASSWORD,
    REDCAP_COMMUNITY_USER,
    REDCAP_DOCKER_IMAGE,
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


_ADMIN_FLAGS = [
    "super_user",
    "account_manager",
    "access_system_config",
    "access_system_upgrade",
    "access_external_module_install",
    "admin_rights",
    "access_admin_dashboards",
]


def _user_row(stack: RedcapStack, username: str, columns: list[str]) -> dict | None:
    rows = stack.db_query(
        f"SELECT {', '.join(columns)} FROM redcap_user_information WHERE username = %s",
        (username,),
    )
    return dict(zip(columns, rows[0])) if rows else None


def _password_hash(stack: RedcapStack, username: str) -> str | None:
    rows = stack.db_query("SELECT password FROM redcap_auth WHERE username = %s", (username,))
    return rows[0][0] if rows else None


@pytest.mark.timeout(400)
def test_user_provisioning(
    redcap_stack: RedcapStack,
    installed_a_snapshot: tuple[str, bytes],
) -> None:
    """
    USER_PROV users must end up in redcap_user_information after the
    130_user_provisioning.php startup step:
    - numeric 0 values are written and not replaced by the column default (#20)
    - values with quotes are stored verbatim (#21)
    - new users get their password although USER_PROV_OVERWRITE_EXISTING is false
    - a user with an invalid value is skipped, the remaining users are still created
    - with USER_PROV_OVERWRITE_EXISTING=true existing users and passwords are updated
    """
    password = "ChangeMe123!"
    test_user = {
        "username": "provisioned_user",
        "user_firstname": "Prov",
        "user_lastname": "Isioned",
        "user_email": "provisioned@example.local",
        "password": password,
        "super_user": 0,
        # REDCap's column default is 1
        "allow_create_db": 0,
    }
    quoted_user = {
        "username": "s.oneill",
        "user_firstname": "Sean",
        "user_lastname": "O'Neill",
        "user_email": "s.oneill@example.local",
        "user_comments": 'He said "hi" \\ 100%',
    }
    invalid_user = {
        "username": "invalid_user",
        "user_firstname": "In",
        "user_lastname": "Valid",
        "user_email": "invalid@example.local",
        "super_user": "yes",
    }
    last_user = {
        "username": "last_user",
        "user_firstname": "Last",
        "user_lastname": "User",
        "user_email": "last@example.local",
    }

    vol, dump = installed_a_snapshot
    redcap_stack.start_from_snapshot(
        vol, dump,
        {
            "REDCAP_VERSION": REDCAP_TEST_VERSION,
            "RCCONF_auth_meth_global": "table",
            "USER_PROV": json.dumps([test_user, quoted_user, invalid_user, last_user]),
            **_CREDENTIALS,
        },
    )

    logs = redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)

    row = redcap_stack.get_user("provisioned_user")
    assert row is not None, "User 'provisioned_user' not found in redcap_user_information"
    assert row["user_firstname"] == "Prov"
    assert row["user_lastname"] == "Isioned"
    assert row["user_email"] == "provisioned@example.local"
    flags = _user_row(redcap_stack, "provisioned_user", ["super_user", "allow_create_db"])
    assert flags == {"super_user": 0, "allow_create_db": 0}, flags
    first_hash = _password_hash(redcap_stack, "provisioned_user")
    assert first_hash, "New user 'provisioned_user' was created without a password"

    quoted = _user_row(redcap_stack, "s.oneill", ["user_lastname", "user_comments"])
    assert quoted == {"user_lastname": "O'Neill", "user_comments": quoted_user["user_comments"]}, quoted

    assert redcap_stack.get_user("invalid_user") is None, "User with invalid super_user value was created"
    assert "'super_user' expects an integer" in logs, logs
    assert redcap_stack.get_user("last_user") is not None, "Users after an invalid one must still be created"
    assert password not in logs, "User password leaked into the container log"

    # Reboot with changed data and overwriting enabled
    test_user.update({"user_lastname": "O'Brien", "allow_create_db": 1, "password": "Changed456!"})
    redcap_stack.restart_redcap(
        {
            "REDCAP_VERSION": REDCAP_TEST_VERSION,
            "RCCONF_auth_meth_global": "table",
            "USER_PROV": json.dumps([test_user]),
            "USER_PROV_OVERWRITE_EXISTING": "true",
            **_CREDENTIALS,
        },
    )
    redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)

    updated = _user_row(redcap_stack, "provisioned_user", ["user_lastname", "allow_create_db"])
    assert updated == {"user_lastname": "O'Brien", "allow_create_db": 1}, updated
    second_hash = _password_hash(redcap_stack, "provisioned_user")
    assert second_hash and second_hash != first_hash, "Password was not updated with USER_PROV_OVERWRITE_EXISTING=true"


@pytest.mark.timeout(400)
def test_suspend_site_admin(
    redcap_stack: RedcapStack,
    installed_a_snapshot: tuple[str, bytes],
) -> None:
    """
    REDCAP_SUSPEND_SITE_ADMIN=true must suspend site_admin and revoke all its admin flags (#19).
    Without another active admin the container log warns about it; with a provisioned
    super user it does not, because suspension runs after user provisioning.
    """
    vol, dump = installed_a_snapshot
    redcap_stack.start_from_snapshot(
        vol, dump,
        {
            "REDCAP_VERSION": REDCAP_TEST_VERSION,
            "REDCAP_SUSPEND_SITE_ADMIN": "true",
            **_CREDENTIALS,
        },
    )
    logs = redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)

    site_admin = _user_row(redcap_stack, "site_admin", _ADMIN_FLAGS + ["user_suspended_time"])
    assert site_admin is not None, "site_admin missing in the snapshot"
    suspended_time = site_admin.pop("user_suspended_time")
    assert suspended_time is not None, "site_admin was not suspended"
    assert site_admin == {flag: 0 for flag in _ADMIN_FLAGS}, site_admin
    assert "no other active REDCap user with admin rights" in logs, logs

    admin = {
        "username": "prov_admin",
        "user_firstname": "Prov",
        "user_lastname": "Admin",
        "user_email": "admin@example.local",
        "super_user": 1,
    }
    redcap_stack.restart_redcap(
        {
            "REDCAP_VERSION": REDCAP_TEST_VERSION,
            "REDCAP_SUSPEND_SITE_ADMIN": "true",
            "USER_PROV": json.dumps([admin]),
            **_CREDENTIALS,
        },
    )
    logs = redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)
    assert "no other active REDCap user with admin rights" not in logs, logs
    after_reboot = _user_row(redcap_stack, "site_admin", ["user_suspended_time"])
    assert after_reboot == {"user_suspended_time": suspended_time}, "Reboot must keep the original suspension time"


@pytest.mark.timeout(180)
def test_php_timezone_in_cron_mode(docker_client) -> None:
    """
    TZ must set PHP's date.timezone in cron mode too, like in web mode (#18). Otherwise
    cron.php computes alerts and survey invitations in UTC. Needs no database: cron mode
    boots crond regardless.
    """
    suffix = uuid.uuid4().hex[:8]
    container = docker_client.containers.run(
        REDCAP_DOCKER_IMAGE,
        name=f"rctest_crontz_{suffix}",
        environment={"CRON_MODE": "true", "TZ": "Europe/Berlin"},
        detach=True,
    )
    try:
        # Same environment as the cron job gets from busybox crond
        cmd = ["bash", "-c", ". /etc/container-environment.sh && php -r 'echo date_default_timezone_get();'"]
        deadline = time.monotonic() + 60
        php_tz = ""
        while time.monotonic() < deadline:
            if "Start cron service..." in container.logs().decode("utf-8", "replace"):
                output = container.exec_run(cmd).output.decode("utf-8", "replace").strip()
                # PHP startup warnings can precede the result
                php_tz = output.splitlines()[-1] if output else ""
                break
            time.sleep(2)
        assert php_tz == "Europe/Berlin", (
            f"Expected PHP timezone 'Europe/Berlin' in cron mode, got {php_tz!r}"
            f"\nLogs:\n{container.logs().decode('utf-8', 'replace')}"
        )
    finally:
        container.remove(force=True)
