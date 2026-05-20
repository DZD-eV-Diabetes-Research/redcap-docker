
# Security

This document describes the security measures built into the redcap-docker container and provides guidance for hardening production deployments.

---

- [Security](#security)
  - [Built-in hardening (defaults)](#built-in-hardening-defaults)
  - [Read-only webroot and Easy Upgrade](#read-only-webroot-and-easy-upgrade)
  - [File integrity verification](#file-integrity-verification)
  - [HTTP security headers](#http-security-headers)
  - [PHP hardening](#php-hardening)
  - [Docker Secrets — avoiding plaintext passwords](#docker-secrets--avoiding-plaintext-passwords)
  - [Container capabilities](#container-capabilities)
  - [TLS / HTTPS](#tls--https)
  - [REDCap edocs outside the webroot](#redcap-edocs-outside-the-webroot)
  - [Database security](#database-security)
  - [Startup security warnings](#startup-security-warnings)
  - [Reporting vulnerabilities](#reporting-vulnerabilities)

---

## Built-in hardening (defaults)

The following security controls are active **by default** without any configuration:

| Control | Default | Notes |
|---|---|---|
| Read-only webroot | **on** | `www-data` cannot write PHP files into the webroot |
| Apache version hiding | **on** | `ServerTokens Prod`, `ServerSignature Off` |
| HTTP security headers | **on** | `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy` |
| PHP version hiding | **on** | `expose_php = Off` |
| PHP error display | **off** | `display_errors = Off` — errors go to logs, not to the browser |
| Easy Upgrade | **disabled** | `REDCAP_EASY_UPGRADE_ENABLE=false` |

---

## Read-only webroot and Easy Upgrade

### What is REDCap Easy Upgrade?

REDCap ships a browser-based upgrade tool ("Easy Upgrade") that lets administrators upgrade REDCap by clicking a button in the admin panel. It works by having the web process write new PHP files directly into the webroot.

### Why is it disabled by default?

> *"Using Easy Upgrade on a production server is no longer recommended because of security implications. Easy Upgrade requires that the REDCap application be able to write files to the main "redcap" directory on the web server, which is not a recommended security practice for production environments because it can make your server vulnerable to certain attacks."*
> — REDCap Consortium

When the webserver process can write PHP files to the webroot, any PHP-level file-write vulnerability (injection, deserialization, path traversal) can be turned into persistent remote code execution by planting a backdoor.

### Default behaviour (production-safe)

With `REDCAP_EASY_UPGRADE_ENABLE=false` (the default), the container sets the following permissions at startup:

```
/var/www/html/           → root:www-data  750 dirs / 640 files
/var/www/html/temp/      → www-data:www-data  750  (REDCap requires write access here)
/var/www/html/edocs/     → www-data:www-data  750  (user upload storage)
```

Apache can serve all files, but `www-data` cannot create or modify PHP files in the webroot.

### The container's own upgrader is unaffected

The `redcap-upgrade` command and `REDCAP_AUTO_UPGRADE=true` run **before Apache starts**, as root, and do not require `REDCAP_EASY_UPGRADE_ENABLE=true`. You can use the full automated upgrade pipeline with the webroot locked down.

### Enabling Easy Upgrade (not recommended for production)

If you specifically need the REDCap browser-based upgrade tool:

```yaml
environment:
  REDCAP_EASY_UPGRADE_ENABLE: "true"
```

The container will print a warning at startup and grant `www-data` write access to the entire webroot. After the upgrade completes, set this back to `false` and restart.

---

## File integrity verification

The container ships a `redcap-integrity-check` command that compares the running REDCap installation against a freshly downloaded canonical copy from the REDCap community portal.

It detects:
- **Modified files** — PHP files that differ from the canonical source (potential injection)
- **Extra files** — PHP files present in the install but absent from the canonical zip (potential backdoors)
- **Extra root-level files** — PHP files in the document root outside any `redcap_v*` directory

### Running manually

```bash
docker compose exec redcap redcap-integrity-check
```

Credentials are read from `REDCAP_COMMUNITY_USER` / `REDCAP_COMMUNITY_PASSWORD` by default. To use a locally pre-downloaded zip instead:

```bash
docker compose exec redcap redcap-integrity-check --zip /var/www/html/redcap_v14.9.5.zip
```

```
[INTEGRITY] Checking REDCap v14.9.5 at /var/www/html/redcap_v14.9.5
[INTEGRITY] File extensions checked: php
[INTEGRITY] Downloading canonical REDCap v14.9.5...
[INTEGRITY] Canonical files: 4821  |  Installed files: 4821
[INTEGRITY] RESULT: Clean — no unexpected differences found.
```

### Boot-time check

To abort startup if tampering is detected:

```yaml
environment:
  REDCAP_INTEGRITY_CHECK_ON_BOOT: "true"
```

This requires community portal credentials (or `REDCAP_ZIP_PATH`) and adds a download step to every cold start. Disabled by default.

### Exit codes

| Code | Meaning |
|---|---|
| `0` | Clean |
| `1` | Tampering detected |
| `2` | Check could not be completed (missing credentials, download failure) |

### All options

```
--version <X.X.X>       Check against this version (default: auto-detect)
--zip <path>            Use a local zip instead of downloading
--community-user        Portal username (or REDCAP_COMMUNITY_USER env var)
--community-password    Portal password (or REDCAP_COMMUNITY_PASSWORD env var)
--extensions <list>     Comma-separated extensions to compare (default: php)
--report-missing        Also report files in canonical but absent from install
--help                  Show full help
```

---

## HTTP security headers

The following headers are set on every response by `20-security-headers.conf`:

| Header | Value |
|---|---|
| `X-Frame-Options` | `SAMEORIGIN` — prevents your REDCap from being embedded in iframes on other domains |
| `X-Content-Type-Options` | `nosniff` — prevents MIME-type sniffing |
| `Referrer-Policy` | `strict-origin-when-cross-origin` — limits referer leakage on cross-origin navigations |
| `Permissions-Policy` | `geolocation=(), camera=(), microphone=(), payment=()` |

Server identification headers (`X-Powered-By`, `Server`) are suppressed.

**Content-Security-Policy** is intentionally not set by default — REDCap uses inline scripts and dynamic JavaScript evaluation, making a strict CSP impractical without per-deployment tuning. If you need one, add it via the custom vhost include:

```apache
# /config/apache/custom.virtualhost
Header always set Content-Security-Policy "default-src 'self'; ..."
```

---

## PHP hardening

`91_security.ini` applies these settings unconditionally:

```ini
expose_php = Off        # removes X-Powered-By: PHP/x.x.x from responses
display_errors = Off    # error details go to logs, not to end users
log_errors = On
```

To add further restrictions (e.g. `disable_functions`) mount a custom ini:

```yaml
volumes:
  - ./my-php.ini:/config/php/custom_inis/99-custom.ini
```

> [!CAUTION]
> REDCap uses `exec()` internally (e.g. for PDF generation via Ghostscript). Disabling it will break PDF exports. Test thoroughly before adding function restrictions.

---

## Docker Secrets — avoiding plaintext passwords

Environment variables are visible via `docker inspect` and `/proc/<pid>/environ`. For production deployments, use file-backed secrets instead.

The container supports `_FILE` variants for sensitive variables. Set the variable to a path, and the container reads the secret from the file at startup:

| Variable | File variant |
|---|---|
| `DB_PASSWORD` | `DB_PASSWORD_FILE` |
| `DB_SALT` | `DB_SALT_FILE` |
| `DB_USERNAME` | `DB_USERNAME_FILE` |
| `REDCAP_COMMUNITY_PASSWORD` | `REDCAP_COMMUNITY_PASSWORD_FILE` |

### Example with Docker Secrets

```yaml
services:
  redcap:
    image: dzdde/redcap-docker
    environment:
      DB_PASSWORD_FILE: /run/secrets/db_password
      DB_SALT_FILE: /run/secrets/db_salt
    secrets:
      - db_password
      - db_salt

secrets:
  db_password:
    file: ./secrets/db_password.txt
  db_salt:
    file: ./secrets/db_salt.txt
```

---

## Container capabilities

The example compose files use a minimal Linux capability set. For your own deployments:

```yaml
security_opt:
  - no-new-privileges:true
cap_drop:
  - ALL
cap_add:
  - CHOWN          # startup uid/gid remapping and permission fixing
  - DAC_OVERRIDE   # file operations during startup
  - SETUID         # Apache drops from root to www-data
  - SETGID         # Apache drops from root to www-data
  - NET_BIND_SERVICE  # bind port 80 inside the container
```

Remove `NET_BIND_SERVICE` if you map Apache to a non-privileged host port (e.g. `"8080:80"`).

---

## TLS / HTTPS

The container speaks plain HTTP. TLS should be terminated by a reverse proxy (nginx, Traefik, Caddy, HAProxy) in front of the container. A plain HTTP deployment is only appropriate for `localhost` development.

Configure the public URL in REDCap:

```yaml
environment:
  RCCONF_redcap_base_url: "https://redcap.example.com"
```

The container will warn at startup if `RCCONF_redcap_base_url` uses `http://` and `SERVER_NAME` is not `localhost`.

---

## REDCap edocs outside the webroot

By default REDCap stores user-uploaded files in `/var/www/html/edocs` — inside the webroot. If Apache misconfiguration or a `.htaccess` bypass occurs, these files could be directly accessible via HTTP.

Move them outside the webroot:

```yaml
environment:
  RCCONF_edoc_path: /data
volumes:
  - ./data/user-files:/data
```

See the [custom edocs example](examples/local_instance_custom_edocs).

---

## Database security

- Use a strong, unique `DB_SALT` (at least 64 random hex characters). Do not reuse the example value.
- Use a dedicated database user with access only to the REDCap database — do not use the MySQL `root` account for the application.
- Enable SSL for the database connection using the `DB_SSL_*` environment variables if MySQL is not on the same host/network as the container.

---

## Startup security warnings

The container prints advisory messages at startup for common misconfigurations:

| Condition | Warning |
|---|---|
| `REDCAP_EASY_UPGRADE_ENABLE=true` | Webroot is writable — not recommended for production |
| `REDCAP_COMMUNITY_PASSWORD` set as env var | Suggests using `_FILE` variant instead |
| `SERVER_NAME=localhost` | TLS not configured |
| `RCCONF_redcap_base_url` uses `http://` (non-localhost) | Unencrypted transport |

---

## Reporting vulnerabilities

Please report security issues in this container (not in REDCap itself) by [opening a GitHub issue](https://github.com/DZD-eV-Diabetes-Research/redcap-docker/issues) or contacting the maintainer directly.

For vulnerabilities in REDCap itself, follow the [REDCap Consortium's disclosure process](https://www.project-redcap.org/).
