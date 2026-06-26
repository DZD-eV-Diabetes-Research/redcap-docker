# Auto-install (Beta)

> 🧪 This example uses beta features. See [Beta Channel](../../README.md#beta-channel).

The container downloads REDCap directly from the community portal on first boot, installs the database schema, and starts serving. No manual file copying needed. On subsequent boots it skips the download and starts immediately.

**What this example shows**

- `REDCAP_VERSION` — desired REDCap version; triggers download if files aren't present yet
- `REDCAP_COMMUNITY_USER` / `REDCAP_COMMUNITY_PASSWORD` — portal credentials for the download
- `REDCAP_AUTO_UPGRADE` — (optional) automatically upgrade when you bump `REDCAP_VERSION`
- A `backups/` volume — pre-upgrade database backups land here

## Quick start

```bash
# 1. Clone or copy this directory
git clone https://github.com/DZD-eV-Diabetes-Research/redcap-docker.git
cd redcap-docker/examples/auto_install

# 2. Fill in your credentials and desired version in docker-compose.yaml
#    REDCAP_VERSION, REDCAP_COMMUNITY_USER, REDCAP_COMMUNITY_PASSWORD

# 3. Start
docker compose pull             # pull the latest image — we ship fixes frequently
docker compose up -d
docker compose logs -f redcap   # watch the download + install (first boot only)
```

Visit `http://localhost` once both containers show `(healthy)`.

## Upgrading

To upgrade REDCap, bump `REDCAP_VERSION` and restart:

```bash
# Edit docker-compose.yaml: change REDCAP_VERSION to the new version
docker compose pull             # pull the latest image — we ship fixes frequently
docker compose up -d
```

- With `REDCAP_AUTO_UPGRADE: "false"` (default): the container logs a warning and starts on the existing version. Run `docker compose exec redcap redcap-upgrade` to upgrade interactively.
- With `REDCAP_AUTO_UPGRADE: "true"`: the container upgrades automatically, creates a DB backup first, and rolls back if the upgrade fails.

See [REDCAP_UPGRADE.md](../../REDCAP_UPGRADE.md) for full upgrade documentation.
