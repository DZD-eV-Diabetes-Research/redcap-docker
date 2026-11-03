# DZD Docker REDCap Project Provisioning

- [What this does](#what-this-does)
- [Quick start](#quick-start)
- [How it works](#how-it-works)
- [Getting a project XML](#getting-a-project-xml)
- [Project spec](#project-spec)
  - [Examples](#examples)
- [Provide project specs](#provide-project-specs)
- [Owner and user rights](#owner-and-user-rights)
- [Already provisioned and deleted projects](#already-provisioned-and-deleted-projects)
- [Troubleshooting](#troubleshooting)
- [Security notes](#security-notes)
- [Environment variable reference](#environment-variable-reference)

# What this does

The container can create REDCap projects on boot from REDCap project XML files (CDISC ODM with REDCap extensions). This is the format REDCap uses to copy a project to another instance, so the XML can come from:

* the *Download metadata only (XML)* or *Download metadata & data (XML)* buttons of a project on any REDCap instance,
* the API method *Export Entire Project as REDCap XML File* (`content=project_xml`),
* the `project_xml` jobs of this image's [periodic data export](DATA_EXPORT.md).

Typical uses: roll out the same project templates to every instance, move a study to a new instance, or give a test instance the structure of a production project.

Each project is created **once**. The container never updates or deletes a provisioned project.

# Quick start

```yaml
  redcap:
    image: dzdde/redcap-docker
    environment:
      # ... DB_* and the other usual settings ...
      ENABLE_PROJECT_PROV: "true"
    volumes:
      - ./data/redcap:/var/www/html
      - ./projects:/opt/redcap-docker/projects:ro
```

`./projects/projects.yaml`:

```yaml
REDCapProjectList:
  - key: study-template
    xml: study-template.xml   # relative to the projects directory
```

Put the XML file next to it as `./projects/study-template.xml`. On the next start the container creates the project, with the title and purpose stored in the XML. See [examples/instance_with_project_prov](examples/instance_with_project_prov) for a full setup.

# How it works

* Project provisioning runs in the web container on every start, after REDCap is installed and after [user](USER_PROV.md) and [module](MODULE_PROV.md) provisioning. On a fresh auto install it runs on the same boot.
* Every spec has a unique `key`. When the project is created, the container stores `key → project id` in the `redcap_config` table (field `redcap_docker_project_prov:<key>`). A spec whose key is stored is skipped on later starts, also when the project was renamed or changed since.
* The project is created the way REDCap's API method *Create Project* with an XML file does it, with REDCap's own XML import. Unlike the API, no API token is created. Everything the XML contains is imported: instruments, events and arms, repeating instruments, survey settings, reports, user roles, data access groups, alerts, data quality rules and more, and the records if the XML holds any (set `metadata_only: true` to skip them).
* REDCap logs the creation in the project's logging as *Create project using REDCap XML file (redcap-docker project provisioning, key '...')*.
* If a project can not be created (unreadable file, checksum mismatch, invalid XML, unknown owner, ...), the error is logged, nothing is stored and the spec is retried on the next start. A project REDCap has already started to create when the import fails is deleted again, so a broken XML does not leave half a project behind.
* When several web containers start at the same time, they wait for each other (database lock), so a project is never created twice.

# Getting a project XML

On the source instance, open the project and go to *Project Setup* → *Other Functionality*:

* **Download metadata only (XML)**: the project definition without records. Good for templates.
* **Download metadata & data (XML)**: definition plus all records. Good for moving a study.

The XML contains the project title, purpose and notes, which are used unless the spec sets its own.

> [!TIP]
> Export from the same or an older REDCap version than the target instance. A newer version may write project settings the older import does not know.

# Project spec

| Property | Required | Default | Description |
| --- | --- | --- | --- |
| `key` | yes | | Unique name of this spec. Letters, digits, `_` and `-`, at most 64 characters. Do not change it after the project was created, a new key creates a new project. |
| `xml` | one of `xml`, `url` | | Path to the XML file. Relative paths are resolved against `PROJECT_PROV_FILE_DIR`. |
| `url` | one of `xml`, `url` | | `https://` URL to download the XML from. Redirects are followed, but only to `https://`. Downloaded only while the project does not exist yet. |
| `sha256` | no | | SHA-256 checksum of the XML file. The project is not created if the file does not match. Recommended for `url`. |
| `title` | no | from the XML | Project title. |
| `purpose` | no | from the XML | `0` practice/just for fun, `1` other, `2` research, `3` quality improvement, `4` operational support. Required if the XML has none. |
| `purpose_other` | no | from the XML | Description for purpose `1`, or the research area codes for purpose `2` (as in the XML). |
| `project_notes` | no | from the XML | Project notes. |
| `owner` | no | | Username of an existing user who gets full rights on the project, see [Owner and user rights](#owner-and-user-rights). |
| `metadata_only` | no | `false` | `true` skips the records of the XML and creates an empty project. |

Unknown properties and invalid values are logged as errors and the spec is skipped. The other specs are still processed.

## Examples

```yaml
REDCapProjectList:
  # A template from a mounted file, title and purpose from the XML
  - key: study-template
    xml: study-template.xml

  # The same XML once more, as an empty project with its own title and owner
  - key: training
    xml: study-template.xml
    title: Training project
    purpose: 0
    owner: trainer
    metadata_only: true

  # A study moved from another instance, downloaded once and verified
  - key: study-b
    url: https://files.example.org/redcap/study-b_20261103T030000Z.xml
    sha256: 9f2c4e1d0b8a7c6e5f4d3c2b1a0918273645546372819a0b1c2d3e4f5a6b7c8d
    owner: b.lead
```

# Provide project specs

Like [user](USER_PROV.md) and [module](MODULE_PROV.md) provisioning, specs can come from three places. All of them are combined. A key may only be used once; later duplicates are skipped with an error.

**Option 1, JSON list in `PROJECT_PROV`:**

```env
PROJECT_PROV='[{"key": "study-template", "xml": "study-template.xml"}]'
```

**Option 2, one spec per indexed variable:**

```env
PROJECT_PROV_1='{"key": "study-template", "xml": "study-template.xml"}'
PROJECT_PROV_2='{"key": "study-b", "url": "https://files.example.org/redcap/study-b.xml", "owner": "b.lead"}'
```

**Option 3, YAML or JSON files** in `PROJECT_PROV_FILE_DIR` (default `/opt/redcap-docker/projects`) with the root key `REDCapProjectList`, see [Examples](#examples). The XML files can live in the same directory; only `*.yaml`, `*.yml` and `*.json` files are read as spec files.

# Owner and user rights

* **With `owner`:** the user gets the rights REDCap gives the creator of a new project in the UI (among others user rights, project design, view-edit and export of all instruments, reports, data import). API and mobile app rights are not given and no API token is created; they can be granted later in the *User Rights* page. The user has to exist when the project is created, so create it first, for example with [user provisioning](USER_PROV.md), which runs before project provisioning. If the user does not exist, the spec fails and is retried on the next start.
* **Without `owner`:** the project has no users. A REDCap administrator adds them in the project's *User Rights* page.

User roles from the XML are created, but the XML holds no user assignments, so users of the source project are never added.

# Already provisioned and deleted projects

A spec is provisioned once. Afterwards:

* Changing the spec or the XML has no effect on the existing project.
* If an administrator deletes the project, it is **not** created again. The start log says so. To provision it anew, delete the marker in the Control Center *Database Query Tool* (or any SQL client) and restart the container:

  ```sql
  DELETE FROM redcap_config WHERE field_name = 'redcap_docker_project_prov:study-template';
  ```

* Removing a spec does not delete its project.

# Troubleshooting

Every step is logged with the prefix `[PROJECT PROVISIONING]` in the web container log (`docker compose logs redcap`). Errors REDCap reports while importing the XML are included as REDCap returns them. A failed spec does not stop the container from starting.

# Security notes

* A project XML is configuration with real effect: it can contain alerts and automated survey invitations that send emails, and with records it holds research data, often with identifiers. Only provision XML from sources you trust, and check active alerts and invitations of a copied production project before it goes live on another instance.
* Pin downloads with `sha256`, so a changed or replaced file on the server is not imported. Only `https://` URLs are accepted.
* Mount the projects directory read only (`:ro`). XML files with records belong in your data protection concept like the database itself. Downloaded and copied files are kept in a private temporary directory only while the project is created and deleted afterwards.

# Environment variable reference

| Variable | Default | Description |
| --- | --- | --- |
| `ENABLE_PROJECT_PROV` | `false` | Switch project provisioning on |
| `PROJECT_PROV` | (empty) | JSON list of project specs |
| `PROJECT_PROV_1` … `PROJECT_PROV_N` | (empty) | One project spec as a JSON object per variable |
| `PROJECT_PROV_FILE_DIR` | `/opt/redcap-docker/projects` | Scanned for `*.yaml`, `*.yml` and `*.json` spec files; base directory for relative `xml` paths |
| `PROJECT_PROV_DOWNLOAD_TIMEOUT` | `600` | Maximum seconds for downloading one XML from a `url` |
