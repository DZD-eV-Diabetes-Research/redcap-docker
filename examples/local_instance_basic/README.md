# Minimal setup: bring your own REDCap files

The simplest possible deployment. You supply the REDCap source files; the container handles database installation, configuration, and startup.

**What this example shows**

- Mounting your own REDCap files at `/var/www/html`
- Database auto-install on first boot (`REDCAP_INSTALL_ENABLE`)
- Setting `redcap_config` table values via `RCCONF_*` env vars
- Recommended container security options (`cap_drop`, `no-new-privileges`)

> If you'd rather have the container download REDCap for you, see [auto_install](../auto_install/).

## Quick start

```bash
git clone https://github.com/DZD-eV-Diabetes-Research/redcap-docker.git
cd redcap-docker/examples/local_instance_basic
```

Place your REDCap source files inside `./data/redcap/` so that `index.php` lives at `./data/redcap/index.php`.

```bash
docker compose pull
docker compose up -d
docker compose logs -f redcap   # watch startup progress
```

Both containers should reach `(healthy)` status. Visit `http://localhost`.

```bash
docker compose ps
# NAME                 STATUS
# ...-redcap-1         Up (healthy)
# ...-db-1             Up (healthy)
```

## Notes

- `DB_SALT` must be a long, unique random hex string. Never reuse the example value in production.
- Drop `NET_BIND_SERVICE` from `cap_add` if you map Apache to a non-privileged host port (e.g. `"8080:80"`).
- Any `.sql` file placed in `./sql_scripts/` runs once on next boot, then never again. Handy for one-off migrations.
