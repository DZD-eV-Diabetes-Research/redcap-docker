# Security integration tests for redcap-docker.
#
# All tests use the session-scoped installed_a_snapshot fixture (fast path,
# ~30s per test) — no extra downloads are performed beyond the two that the
# full suite already downloads.
#
# Coverage:
#   - Webroot permissions (read-only by default, writable with Easy Upgrade)
#   - temp/ directory always writable by www-data
#   - Startup security warnings (Easy Upgrade, plaintext password, http:// URL)
#   - PHP ini hardening (expose_php=Off, display_errors=Off)
#   - HTTP security response headers (X-Frame-Options, X-Content-Type-Options, …)
#   - Server header does not reveal Apache version
#   - Docker Secrets: DB_PASSWORD_FILE loads password from file

from __future__ import annotations

import re
import uuid

import pytest

from conftest import (
    BOOT_TIMEOUT_FAST,
    DB_PASSWORD,
    REDCAP_COMMUNITY_PASSWORD,
    REDCAP_COMMUNITY_USER,
    REDCAP_TEST_VERSION,
    RedcapStack,
)

_CREDENTIALS = {
    "REDCAP_COMMUNITY_USER": REDCAP_COMMUNITY_USER,
    "REDCAP_COMMUNITY_PASSWORD": REDCAP_COMMUNITY_PASSWORD,
}
_BASE_ENV = {
    "REDCAP_VERSION": REDCAP_TEST_VERSION,
    **_CREDENTIALS,
}


# ── Webroot permission tests ───────────────────────────────────────────────────


@pytest.mark.timeout(300)
def test_webroot_readonly_by_default(
    redcap_stack: RedcapStack,
    installed_a_snapshot: tuple[str, bytes],
) -> None:
    """
    With REDCAP_EASY_UPGRADE_ENABLE=false (the default), the webroot must be
    owned by root:www-data with mode 750 — www-data can traverse but not write.
    """
    vol, dump = installed_a_snapshot
    redcap_stack.start_from_snapshot(vol, dump, _BASE_ENV)
    redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)

    exit_code, out = redcap_stack.exec_run(
        "stat -c '%U %G %a' /var/www/html"
    )
    assert exit_code == 0, f"stat failed: {out}"
    out = out.strip()

    owner, group, mode = out.split()
    assert owner == "root", (
        f"Webroot must be owned by root (got {owner!r}). "
        "www-data as owner would allow the web process to write PHP files."
    )
    assert group == "www-data", f"Webroot group must be www-data, got {group!r}"

    # Mode 750: owner=rwx, group=r-x, others=---
    # Group must NOT have write permission (bit 4 of group octet = write).
    group_octet = int(mode[-2])  # e.g. "750" → '5'
    assert not (group_octet & 0b010), (
        f"Webroot group permission must not include write (mode={mode}). "
        "www-data is in the www-data group and would be able to create PHP files."
    )


@pytest.mark.timeout(300)
def test_webroot_temp_dir_owned_by_www_data(
    redcap_stack: RedcapStack,
    installed_a_snapshot: tuple[str, bytes],
) -> None:
    """
    The temp/ directory inside the webroot must always be owned by www-data,
    because REDCap writes temporary files there regardless of Easy Upgrade setting.
    """
    vol, dump = installed_a_snapshot
    redcap_stack.start_from_snapshot(vol, dump, _BASE_ENV)
    redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)

    # 90-fix_permissions.sh always creates temp/ with www-data ownership — no mkdir needed.
    exit_code, out = redcap_stack.exec_run(
        "stat -c '%U %G' /var/www/html/temp"
    )
    assert exit_code == 0, f"stat on temp/ failed: {out}"
    owner, group = out.strip().split()

    assert owner == "www-data", (
        f"temp/ must be owned by www-data so REDCap can write temporary files, got {owner!r}"
    )
    assert group == "www-data", f"temp/ group must be www-data, got {group!r}"


@pytest.mark.timeout(300)
def test_webroot_writable_when_easy_upgrade_enabled(
    redcap_stack: RedcapStack,
    installed_a_snapshot: tuple[str, bytes],
) -> None:
    """
    With REDCAP_EASY_UPGRADE_ENABLE=true, the webroot must be owned by www-data
    so REDCap's browser-based upgrade tool can write to it.
    """
    vol, dump = installed_a_snapshot
    redcap_stack.start_from_snapshot(
        vol, dump,
        {**_BASE_ENV, "REDCAP_EASY_UPGRADE_ENABLE": "true"},
    )
    redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)

    exit_code, out = redcap_stack.exec_run(
        "stat -c '%U' /var/www/html"
    )
    assert exit_code == 0, f"stat failed: {out}"
    assert out.strip() == "www-data", (
        f"With REDCAP_EASY_UPGRADE_ENABLE=true the webroot must be owned by www-data "
        f"(got {out.strip()!r})"
    )


# ── Startup warning tests ──────────────────────────────────────────────────────


@pytest.mark.timeout(300)
def test_easy_upgrade_security_warning_in_logs(
    redcap_stack: RedcapStack,
    installed_a_snapshot: tuple[str, bytes],
) -> None:
    """
    Enabling REDCAP_EASY_UPGRADE_ENABLE=true must print a visible security
    warning in the container startup logs.
    """
    vol, dump = installed_a_snapshot
    redcap_stack.start_from_snapshot(
        vol, dump,
        {**_BASE_ENV, "REDCAP_EASY_UPGRADE_ENABLE": "true"},
    )
    logs = redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)

    assert "REDCAP_EASY_UPGRADE_ENABLE=true" in logs, (
        "Expected security warning about REDCAP_EASY_UPGRADE_ENABLE=true in startup logs."
        f"\nLogs:\n{logs}"
    )
    assert "SECURITY WARNING" in logs or "[SECURITY]" in logs, (
        "Expected a security warning label in startup logs when Easy Upgrade is enabled."
        f"\nLogs:\n{logs}"
    )


@pytest.mark.timeout(300)
def test_startup_warning_plaintext_community_password(
    redcap_stack: RedcapStack,
    installed_a_snapshot: tuple[str, bytes],
) -> None:
    """
    Setting REDCAP_COMMUNITY_PASSWORD as a plain env var (without the _FILE
    counterpart) must emit a security advisory in the startup logs.
    """
    vol, dump = installed_a_snapshot
    redcap_stack.start_from_snapshot(vol, dump, _BASE_ENV)
    logs = redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)

    # _CREDENTIALS always passes REDCAP_COMMUNITY_PASSWORD as plain env var.
    assert "REDCAP_COMMUNITY_PASSWORD" in logs, (
        "Expected advisory about REDCAP_COMMUNITY_PASSWORD being a plain env var."
        f"\nLogs:\n{logs}"
    )
    assert "[SECURITY]" in logs, (
        "Expected a [SECURITY] label in logs when community password is a plain env var."
        f"\nLogs:\n{logs}"
    )


@pytest.mark.timeout(300)
def test_startup_warning_http_base_url_non_localhost(
    redcap_stack: RedcapStack,
    installed_a_snapshot: tuple[str, bytes],
) -> None:
    """
    An http:// base URL combined with a non-localhost SERVER_NAME must produce
    a security advisory about unencrypted transport.
    """
    vol, dump = installed_a_snapshot
    redcap_stack.start_from_snapshot(
        vol, dump,
        {
            **_BASE_ENV,
            "SERVER_NAME": "redcap.example.com",
            "RCCONF_redcap_base_url": "http://redcap.example.com",
            "APPLY_RCCONF_VARIABLES": "true",
        },
    )
    logs = redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)

    assert "[SECURITY]" in logs, (
        "Expected a [SECURITY] advisory when http:// URL is used with a non-localhost server name."
        f"\nLogs:\n{logs}"
    )
    assert "http://" in logs, (
        "Expected the advisory to mention the http:// URL."
        f"\nLogs:\n{logs}"
    )


# ── PHP hardening tests ────────────────────────────────────────────────────────


@pytest.mark.timeout(300)
def test_php_expose_php_off(
    redcap_stack: RedcapStack,
    installed_a_snapshot: tuple[str, bytes],
) -> None:
    """
    expose_php must be Off so that the PHP version is not revealed in the
    X-Powered-By response header.
    """
    vol, dump = installed_a_snapshot
    redcap_stack.start_from_snapshot(vol, dump, _BASE_ENV)
    redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)

    exit_code, out = redcap_stack.exec_run(
        "php -r \"echo ini_get('expose_php') ? 'ON' : 'OFF';\""
    )
    assert exit_code == 0, f"php -r failed: {out}"
    assert out.strip() == "OFF", (
        f"expose_php must be Off to suppress the X-Powered-By header, got {out.strip()!r}"
    )


@pytest.mark.timeout(300)
def test_php_display_errors_off(
    redcap_stack: RedcapStack,
    installed_a_snapshot: tuple[str, bytes],
) -> None:
    """
    display_errors must be Off so that PHP errors are never exposed to end
    users (they go to the error log instead).
    """
    vol, dump = installed_a_snapshot
    redcap_stack.start_from_snapshot(vol, dump, _BASE_ENV)
    redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)

    exit_code, out = redcap_stack.exec_run(
        "php -r \"echo ini_get('display_errors') ? 'ON' : 'OFF';\""
    )
    assert exit_code == 0, f"php -r failed: {out}"
    assert out.strip() == "OFF", (
        f"display_errors must be Off to prevent leaking error details, got {out.strip()!r}"
    )


# ── HTTP response header tests ─────────────────────────────────────────────────


def _fetch_response_headers(redcap_stack: RedcapStack) -> str:
    """
    Make a HEAD request to http://localhost/ from inside the container and
    return the raw response header text.  Uses wget (always available in the
    image) with --server-response; stderr is merged so exec_run captures it.
    """
    exit_code, out = redcap_stack.exec_run(
        "wget -q --server-response -O /dev/null http://localhost/ 2>&1 || true"
    )
    return out


@pytest.mark.timeout(300)
def test_http_security_headers_present(
    redcap_stack: RedcapStack,
    installed_a_snapshot: tuple[str, bytes],
) -> None:
    """
    Apache must send the required HTTP security headers on every response:
    X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy.
    """
    vol, dump = installed_a_snapshot
    redcap_stack.start_from_snapshot(vol, dump, _BASE_ENV)
    redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)

    headers = _fetch_response_headers(redcap_stack).lower()

    assert "x-frame-options:" in headers, (
        "Missing X-Frame-Options header — clickjacking protection not in place."
        f"\nResponse headers:\n{headers}"
    )
    assert "x-content-type-options:" in headers, (
        "Missing X-Content-Type-Options header."
        f"\nResponse headers:\n{headers}"
    )
    assert "referrer-policy:" in headers, (
        "Missing Referrer-Policy header."
        f"\nResponse headers:\n{headers}"
    )
    assert "permissions-policy:" in headers, (
        "Missing Permissions-Policy header."
        f"\nResponse headers:\n{headers}"
    )


@pytest.mark.timeout(300)
def test_http_security_header_values(
    redcap_stack: RedcapStack,
    installed_a_snapshot: tuple[str, bytes],
) -> None:
    """
    Verify the specific values of the critical HTTP security headers.
    """
    vol, dump = installed_a_snapshot
    redcap_stack.start_from_snapshot(vol, dump, _BASE_ENV)
    redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)

    headers = _fetch_response_headers(redcap_stack).lower()

    assert "x-frame-options: sameorigin" in headers, (
        "X-Frame-Options must be SAMEORIGIN."
        f"\nResponse headers:\n{headers}"
    )
    assert "x-content-type-options: nosniff" in headers, (
        "X-Content-Type-Options must be nosniff."
        f"\nResponse headers:\n{headers}"
    )


@pytest.mark.timeout(300)
def test_server_header_does_not_reveal_version(
    redcap_stack: RedcapStack,
    installed_a_snapshot: tuple[str, bytes],
) -> None:
    """
    The Server response header must not reveal the Apache version string.
    With ServerTokens Prod, it should contain only "Apache" (no version number).
    """
    vol, dump = installed_a_snapshot
    redcap_stack.start_from_snapshot(vol, dump, _BASE_ENV)
    redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)

    headers = _fetch_response_headers(redcap_stack)

    server_line = next(
        (l for l in headers.splitlines() if l.strip().lower().startswith("server:")),
        None,
    )
    if server_line is None:
        # No Server header at all is also acceptable (mod_headers unset it)
        return

    assert not re.search(r"Apache/\d", server_line, re.IGNORECASE), (
        f"Server header must not expose the Apache version (ServerTokens Prod). "
        f"Got: {server_line.strip()!r}"
    )


@pytest.mark.timeout(300)
def test_x_powered_by_header_absent(
    redcap_stack: RedcapStack,
    installed_a_snapshot: tuple[str, bytes],
) -> None:
    """
    The X-Powered-By header must not be present in responses.
    It can reveal PHP version details; suppressed by expose_php=Off and
    mod_headers unset directive.
    """
    vol, dump = installed_a_snapshot
    redcap_stack.start_from_snapshot(vol, dump, _BASE_ENV)
    redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)

    headers = _fetch_response_headers(redcap_stack).lower()

    assert "x-powered-by:" not in headers, (
        "X-Powered-By header must be suppressed to avoid revealing PHP version details."
        f"\nResponse headers:\n{headers}"
    )


# ── Docker Secrets tests ───────────────────────────────────────────────────────


@pytest.mark.timeout(300)
def test_docker_secret_db_password_file(
    redcap_stack: RedcapStack,
    installed_a_snapshot: tuple[str, bytes],
    docker_client,
) -> None:
    """
    DB_PASSWORD_FILE must be read by 05_load_secrets.sh and used in place of
    DB_PASSWORD.  The container must boot successfully when DB_PASSWORD is
    empty and the real password is supplied only via a mounted secrets file.
    """
    secret_vol_name = f"rctest_secret_{uuid.uuid4().hex[:8]}"
    secret_vol = docker_client.volumes.create(secret_vol_name)

    try:
        # Write the DB password into the volume using a transient Alpine container.
        docker_client.containers.run(
            "alpine",
            command=["sh", "-c", f"echo -n '{DB_PASSWORD}' > /secrets/db_password"],
            volumes={secret_vol_name: {"bind": "/secrets", "mode": "rw"}},
            remove=True,
        )

        vol, dump = installed_a_snapshot
        # Pass DB_PASSWORD="" to force the secret-file path through 05_load_secrets.sh.
        # extra_env keys override the defaults in _create_redcap.
        redcap_stack.start_from_snapshot(
            vol, dump,
            {
                **_BASE_ENV,
                "DB_PASSWORD": "",                   # intentionally empty
                "DB_PASSWORD_FILE": "/run/secrets/db_password",
            },
            extra_volumes={secret_vol_name: "/run/secrets"},
        )

        logs = redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)

        # The secret loader must confirm it read the file.
        assert "Loaded DB_PASSWORD from /run/secrets/db_password" in logs, (
            "Expected '[SECRETS] Loaded DB_PASSWORD from ...' message in startup logs, "
            "indicating the password was read from the file.\n"
            f"Logs:\n{logs}"
        )

    finally:
        try:
            secret_vol.remove(force=True)
        except Exception:
            pass


@pytest.mark.timeout(300)
def test_docker_secret_missing_file_warns_but_continues(
    redcap_stack: RedcapStack,
    installed_a_snapshot: tuple[str, bytes],
) -> None:
    """
    If DB_PASSWORD_FILE points to a non-existent file, the container must log
    a warning but still continue booting (using whatever DB_PASSWORD is set to).
    The secret loader must not abort startup on a missing file.
    """
    vol, dump = installed_a_snapshot
    redcap_stack.start_from_snapshot(
        vol, dump,
        {
            **_BASE_ENV,
            # Real password so the container can actually connect to MySQL
            "DB_PASSWORD": DB_PASSWORD,
            # Points to a path that does not exist inside the container
            "DB_PASSWORD_FILE": "/run/secrets/nonexistent_file",
        },
    )

    logs = redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)

    assert "nonexistent_file" in logs, (
        "Expected a warning mentioning the missing secret file path."
        f"\nLogs:\n{logs}"
    )
    assert "WARNING" in logs, (
        "Expected a WARNING in logs for a missing _FILE path."
        f"\nLogs:\n{logs}"
    )
