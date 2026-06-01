# Docker Secrets

Keeps database password, salt, and community portal password out of environment variables. Instead of `DB_PASSWORD=plaintext`, the container reads each value from a file mounted at a known path.

**Why this matters:** environment variables are visible via `docker inspect` and `/proc/<pid>/environ`. File-backed secrets are not.

**What this example shows**

- `DB_PASSWORD_FILE` / `DB_SALT_FILE` — load credentials from Docker Secret files
- `REDCAP_COMMUNITY_PASSWORD_FILE` — portal password for auto-install, also via secret
- `REDCAP_INTEGRITY_CHECK_ON_BOOT: "true"` — 🧪 abort startup if PHP files have been tampered with

The full list of `_FILE` variants: `DB_PASSWORD_FILE`, `DB_SALT_FILE`, `DB_USERNAME_FILE`, `REDCAP_COMMUNITY_PASSWORD_FILE`. See [SECURITY.md](../../SECURITY.md#docker-secrets--avoiding-plaintext-passwords).

## Quick start

```bash
# 1. Copy this directory
git clone https://github.com/DZD-eV-Diabetes-Research/redcap-docker.git
cd redcap-docker/examples/docker_secrets

# 2. Write your secrets into the placeholder files
echo -n "your_db_password"       > secrets/db_password.txt
echo -n "your_long_random_salt"  > secrets/db_salt.txt
echo -n "your_portal_password"   > secrets/community_password.txt

# 3. Edit docker-compose.yaml: set REDCAP_VERSION and REDCAP_COMMUNITY_USER

# 4. Start
docker compose up -d
docker compose logs -f redcap
```

> [!CAUTION]
> The `secrets/` directory contains real credentials. Add it to `.gitignore` before committing.

## Notes

- `MYSQL_PASSWORD` in the `db` service is still a plain env var. That is fine because it never leaves the internal Docker network. Only the REDCap container (the one exposed to the outside) uses file-backed secrets.
- The integrity check (`REDCAP_INTEGRITY_CHECK_ON_BOOT`) downloads a canonical copy on every cold start and compares it to what is on disk. It requires community portal credentials (or pre-set `REDCAP_ZIP_PATH`). Run it manually at any time: `docker compose exec redcap redcap-integrity-check`.
