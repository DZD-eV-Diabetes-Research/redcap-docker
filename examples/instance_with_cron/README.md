# Cron container

REDCap relies on a cron job to send scheduled emails, fire surveys, process queued operations, and run system checks. This example runs a dedicated cron container alongside the web container using the same image.

**What this example shows**

- `CRON_MODE: "true"` — puts the container into cron-only mode (no web server)
- `CRON_INTERVAL` — how often to trigger the REDCap cron script (cron syntax)
- `CRON_RUN_JOB_ON_START: "true"` — (optional) run cron immediately on container start, don't wait for the first scheduled interval
- Both containers share the same database and email config
- Email via MSMTP (see [instance_with_mail](../instance_with_mail/) for the full MSMTP reference)

## Quick start

```bash
git clone https://github.com/DZD-eV-Diabetes-Research/redcap-docker.git
cd redcap-docker/examples/instance_with_cron
```

Place your REDCap source in `./data/redcap/`, fill in the `MSMTP_*` values, then:

```bash
docker compose up -d
docker compose logs -f   # watch both containers
```

## How it works

The cron container runs the same `dzdde/redcap-docker` image as the web container. When `CRON_MODE=true` the entrypoint skips Apache and instead runs `busybox crond`, which invokes `redcap-cron` on the configured `CRON_INTERVAL`.

The cron container mounts the same REDCap files as the web container (`./data/redcap:/var/www/html`) so both share the same codebase. Only the web container exposes a port.

## Cron interval examples

| `CRON_INTERVAL` | Frequency |
|---|---|
| `*/1 * * * *` | Every minute |
| `*/5 * * * *` | Every 5 minutes (REDCap default recommendation) |
| `0 * * * *` | Every hour |

REDCap's own documentation recommends running the cron every minute or every few minutes.
