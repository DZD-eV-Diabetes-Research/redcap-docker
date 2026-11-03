# DZD Docker REDCap Periodic Data Export

- [How it works](#how-it-works)
- [Quick start](#quick-start)
- [Environment variables](#environment-variables)
- [Job definition](#job-definition)
  - [Content types and formats](#content-types-and-formats)
  - [Options](#options)
  - [Examples](#examples)
- [Provide job definitions](#provide-job-definitions)
- [Output files and retention](#output-files-and-retention)
- [The export service user](#the-export-service-user)
- [Running and checking jobs manually](#running-and-checking-jobs-manually)
- [Monitoring](#monitoring)
- [Security notes](#security-notes)

The cron container can export project data on a schedule: records, saved reports or the full project XML (metadata plus data). The exports go to a directory you mount, so a backup tool, a sync job or an analysis pipeline can pick them up.

# How it works

* Exports use the official [REDCap API](https://redcap.vumc.org/api/help/), so REDCap applies its usual export logic and writes an `Export data (API)` entry to the project's logging for every run.
* Each job gets its own line in the cron container's crontab and runs on its own schedule.
* The container manages the API tokens itself. It creates a dedicated REDCap user (the [export service user](#the-export-service-user)), adds it to every project that has an export job, with export rights only, and creates a token per project. You do not need to create or rotate tokens.

# Quick start

Add the export settings to the cron container (see the [cron example](examples/instance_with_cron) for the whole setup) and mount a directory for the exports:

```yaml
  redcap-cron:
    image: dzdde/redcap-docker
    environment:
      CRON_MODE: "true"
      # ... same DB_* vars as the web container ...
      ENABLE_DATA_EXPORT: "true"
      # how the cron container reaches the web container's API
      EXPORT_API_URL: "http://redcap/api/"
    volumes:
      - ./data/redcap:/var/www/html
      - ./export-jobs:/opt/redcap-docker/export-jobs:ro
      - ./exports:/opt/redcap-docker/exports
```

`./export-jobs/jobs.yaml`:

```yaml
REDCapExportJobList:
  - name: study-a-nightly
    project_id: 12
    schedule: "0 2 * * *"
    keep: 14
```

After the next container start, project 12 is exported as CSV every night at 02:00 (container time zone) and the 14 newest exports are kept.

> [!IMPORTANT]
> The REDCap API has to be enabled (Control Center, or `RCCONF_api_enabled=1` on the web container). The export refuses to run otherwise.

# Environment variables

These are read by the cron container. Job definitions are read when the container starts (to build the crontab) and again on every job run.

| Variable | Default | Description |
| --- | --- | --- |
| `ENABLE_DATA_EXPORT` | `false` | Switch the export on. |
| `EXPORT_API_URL` | (empty) | **Required.** The REDCap API URL as reachable from the cron container, e.g. `http://redcap/api/` (compose service name). Prefer the internal URL over the public one. |
| `EXPORT_DIR` | `/opt/redcap-docker/exports` | Where the export files are written, one subdirectory per job. Mount this. |
| `EXPORT_JOBS_FILE_DIR` | `/opt/redcap-docker/export-jobs` | Scanned for `*.yaml`, `*.yml` and `*.json` job files. |
| `EXPORT_JOBS` | (empty) | Job definitions as a JSON list. |
| `EXPORT_JOBS_<n>` | (empty) | One job definition as a JSON object, e.g. `EXPORT_JOBS_1`. |
| `EXPORT_DEFAULT_KEEP` | `0` | Number of exports kept per job when the job sets no `keep`. `0` keeps all. |
| `EXPORT_SERVICE_USERNAME` | `redcap_docker_export` | Username of the [export service user](#the-export-service-user). |
| `EXPORT_API_TIMEOUT` | `3600` | Maximum seconds for one API export. |
| `EXPORT_API_VERIFY_TLS` | `true` | Verify the TLS certificate if `EXPORT_API_URL` is `https://`. |
| `EXPORT_STATE_DIR` | `/tmp/redcap-export-state` | Last result and lock file per job, used by the healthcheck. |

# Job definition

| Property | Required | Default | Description |
| --- | --- | --- | --- |
| `name` | yes | | Unique job name. Letters, digits, `_` and `-`, at most 64 characters. Used for the output directory and file names. |
| `project_id` | yes | | REDCap project ID (the `pid` in REDCap URLs). |
| `schedule` | yes | | Cron expression with 5 fields, e.g. `0 2 * * *`. |
| `content` | no | `record` | What to export: `record`, `report` or `project_xml`. |
| `format` | no | `csv` (`xml` for `project_xml`) | Output format, see below. |
| `report_id` | for `report` | | ID of the saved report to export. Shown in the report list of the project. |
| `options` | no | | Additional REDCap API parameters, see [Options](#options). |
| `keep` | no | `EXPORT_DEFAULT_KEEP` | Number of exports to keep for this job. `0` keeps all. |

Unknown properties, unknown options and invalid values are logged as errors and the job is skipped. The other jobs still run.

## Content types and formats

| `content` | What you get | `format` |
| --- | --- | --- |
| `record` | All (or the selected) records | `csv`, `json`, `xml`, `odm` |
| `report` | The rows and columns of a saved report. The project team controls fields and filters in the REDCap UI. | `csv`, `json`, `xml` |
| `project_xml` | CDISC ODM XML with the full project definition and, unless `returnMetadataOnly` is set, all data. Can be used to recreate the project on another instance. | `xml` |

## Options

`options` is passed to the API as is. The supported parameters per content type are:

* `record`: `type`, `records`, `fields`, `forms`, `events`, `rawOrLabel`, `rawOrLabelHeaders`, `exportCheckboxLabel`, `exportSurveyFields`, `exportDataAccessGroups`, `filterLogic`, `dateRangeBegin`, `dateRangeEnd`, `csvDelimiter`, `decimalCharacter`, `exportBlankForGrayFormStatus`
* `report`: `rawOrLabel`, `rawOrLabelHeaders`, `exportCheckboxLabel`, `csvDelimiter`, `decimalCharacter`
* `project_xml`: `returnMetadataOnly`, `records`, `fields`, `events`, `exportSurveyFields`, `exportDataAccessGroups`, `filterLogic`, `exportFiles`

The meaning of each parameter is described in the API documentation of your REDCap instance (`https://<your-redcap>/api/help/`). Lists (e.g. `fields`) can be given as YAML/JSON lists.

## Examples

```yaml
REDCapExportJobList:
  # Nightly CSV of all records, keep two weeks
  - name: study-a-nightly
    project_id: 12
    schedule: "0 2 * * *"
    keep: 14

  # Hourly JSON with labels instead of raw codes, only a few fields
  - name: study-a-dashboard
    project_id: 12
    schedule: "0 * * * *"
    format: json
    keep: 24
    options:
      rawOrLabel: label
      fields: [record_id, consent_date, randomization_group]

  # A saved report, maintained by the study team
  - name: study-b-monitoring
    project_id: 31
    schedule: "30 6 * * 1-5"
    content: report
    report_id: 118
    keep: 10

  # Weekly full project backup incl. uploaded files
  - name: study-b-backup
    project_id: 31
    schedule: "0 3 * * 0"
    content: project_xml
    keep: 8
    options:
      exportSurveyFields: true
      exportDataAccessGroups: true
      exportFiles: true
```

# Provide job definitions

Like [user provisioning](USER_PROV.md), jobs can come from three places. All of them are combined. A job name may only be used once; later duplicates are skipped with an error.

**Option 1, JSON list in `EXPORT_JOBS`:**

```env
EXPORT_JOBS='[{"name": "study-a-nightly", "project_id": 12, "schedule": "0 2 * * *"}]'
```

**Option 2, one job per indexed variable:**

```env
EXPORT_JOBS_1='{"name": "study-a-nightly", "project_id": 12, "schedule": "0 2 * * *"}'
EXPORT_JOBS_2='{"name": "study-b-backup", "project_id": 31, "schedule": "0 3 * * 0", "content": "project_xml"}'
```

**Option 3, YAML or JSON files** in `EXPORT_JOBS_FILE_DIR` with the root key `REDCapExportJobList` (see [Examples](#examples)). This is the most readable option for more than a couple of jobs.

The crontab is built when the cron container starts. After adding, removing or rescheduling jobs, restart the cron container. Changes to other job properties apply from the next run.

# Output files and retention

Every run writes one file:

```
<EXPORT_DIR>/<job name>/<job name>_<UTC timestamp>.<ext>
e.g. /opt/redcap-docker/exports/study-a-nightly/study-a-nightly_20261103T020000Z.csv
```

* The file is written under a temporary name and renamed when the download is complete, so a file with the final name is always complete.
* Files are owned by `www-data` (see `WWW_DATA_UID` / `WWW_DATA_GID`) with mode `640`, directories `750`.
* With `keep: N` the oldest files of the job are deleted after a successful run until `N` remain. Only files that match the naming pattern above are touched. A failed run deletes nothing.

# The export service user

On the first run, the container creates the REDCap user `redcap_docker_export` (see `EXPORT_SERVICE_USERNAME`) and, for each project with an export job:

* adds the user to the project with **API Export** and **full data set export** rights on every instrument,
* removes every other right (no data viewing, no API import, no user rights, no design, no record deletion, no DAG),
* creates a project API token for it.

These rights are reset on every run, so a newly added instrument is included automatically and manual changes to the user's rights are undone. The user has no password and can not log in with table-based authentication. Project members see it in the project's User Rights page.

* **Stop all exports at once:** suspend the user in the Control Center. The jobs then fail with a clear message until you unsuspend it.
* **Remove a project from the export:** remove the job, then remove the service user from the project in its User Rights page.
* **Existing username:** if a user with that name already exists and was not created by the export, the export refuses to use it.

> [!WARNING]
> With LDAP, Shibboleth or OpenID Connect login, a directory account with the same username as the service user could log in as it. Choose an `EXPORT_SERVICE_USERNAME` that can not exist in your directory.

# Running and checking jobs manually

```bash
# list the valid jobs (invalid ones are reported as errors)
docker compose exec redcap-cron redcap-export list
# run jobs now, independent of their schedule
docker compose exec -u www-data redcap-cron redcap-export run study-a-nightly
docker compose exec -u www-data redcap-cron redcap-export run-all
```

Run them as `www-data`, otherwise the files are owned by root. A job that is still running from its previous schedule is skipped, never run twice in parallel.

# Monitoring

* Every run logs its result to the container log (`docker compose logs redcap-cron`), with the file name and size, or the error. Errors from the API (e.g. a deleted report) are included as REDCap returns them.
* The cron container's healthcheck turns **unhealthy** when the last run of any job failed, and healthy again after that job succeeds. Restarting the container resets the state.

# Security notes

* Export files are full copies of research data, often including identifiers. Treat the export directory like the database: restrict access on the host, include it in your data protection concept, and use `keep` so data does not pile up.
* The service user has export rights on every instrument. Use `fields`, `forms` or a `report` to limit what lands on disk.
* `EXPORT_API_URL` should point to the internal address of the web container. If it goes over a network, use `https://` and keep `EXPORT_API_VERIFY_TLS=true`.
