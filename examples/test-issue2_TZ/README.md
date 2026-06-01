# Timezone configuration

Set the container timezone so that Apache access logs, cron output, and REDCap's own timestamps all use your local time instead of UTC.

**What this example shows**

- `TZ` env var — standard POSIX timezone name (e.g. `America/Matamoros`, `Europe/Berlin`)
- Applied to both the web container and the cron container

## Quick start

```bash
git clone https://github.com/DZD-eV-Diabetes-Research/redcap-docker.git
cd redcap-docker/examples/test-issue2_TZ
```

Place your REDCap source in `./data/redcap/`, then:

```bash
docker compose up -d
docker compose logs
```

Timestamps in the logs will reflect the configured timezone:

```
redcap-1      | 127.0.0.1 - - [09/Sep/2025:09:26:58 -0500] "GET /index.php HTTP/1.1" 200 ...
redcap-cron-1 | #### Cron output (09/09/2025 09:28:00 CDT):
```

## Notes

- Use any valid [IANA timezone name](https://en.wikipedia.org/wiki/List_of_tz_database_time_zones) (e.g. `Europe/Berlin`, `Asia/Tokyo`, `UTC`).
- The timezone affects PHP (`date()`, `DateTime`), Apache logs, and the cron timestamp. REDCap's own stored timestamps in the database are determined by MySQL's timezone setting and REDCap configuration.

> Originally created as a debug fixture for [issue #2](https://github.com/DZD-eV-Diabetes-Research/redcap-docker/issues/2).
