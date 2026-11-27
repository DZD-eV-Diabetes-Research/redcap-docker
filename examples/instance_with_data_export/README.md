# Periodic data export

Builds on the [cron example](../instance_with_cron/): the cron container also exports project data on a schedule, through the REDCap API, into `./data/exports/`. Full documentation: [DATA_EXPORT.md](../../DATA_EXPORT.md).

**What this example shows**

- `RCCONF_api_enabled: "1"` on the web container, the export needs the API
- `ENABLE_DATA_EXPORT: "true"` and `EXPORT_API_URL` on the cron container
- Export jobs in [`export-jobs/jobs.yaml`](export-jobs/jobs.yaml): nightly CSV, hourly JSON with labels, weekly project XML
- Retention via `EXPORT_DEFAULT_KEEP` and per job `keep`

## Quick start

```bash
git clone https://github.com/DZD-eV-Diabetes-Research/redcap-docker.git
cd redcap-docker/examples/instance_with_data_export
```

Place your REDCap source in `./data/redcap/`, fill in the `MSMTP_*` values, set the project IDs in `export-jobs/jobs.yaml`, then:

```bash
docker compose up -d
docker compose logs -f redcap-cron
```

The cron container prints the scheduled export jobs on start. To run them right away instead of waiting for the schedule:

```bash
docker compose exec -u www-data redcap-cron redcap-export run-all
ls -l data/exports/*/
```

On the first run the container creates the REDCap user `redcap_docker_export` and adds it to each exported project with export rights only. See [The export service user](../../DATA_EXPORT.md#the-export-service-user).

After adding, removing or rescheduling jobs, restart the cron container: `docker compose restart redcap-cron`.
