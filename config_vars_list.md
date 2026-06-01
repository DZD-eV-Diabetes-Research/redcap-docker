
- [REDCap source download](#redcap-source-download)
- [DEBUG](#debug)
- [PHP](#php)
- [Apache](#apache)
- [www-data user and group ID](#www-data-user-and-group-id)
- [Run custom or upgrade SQL Scripts at boot](#run-custom-or-upgrade-sql-scripts-at-boot)
- [Fix REDCap source files/directory permissions](#fix-redcap-source-filesdirectory-permissions)
- [REDCap database connection environment variable](#redcap-database-connection-environment-variable)
  - [REDCap Data Transfer Services](#redcap-data-transfer-services)
- [User provisioning](#user-provisioning)
- [REDCap installation](#redcap-installation)
  - [Option 1 - automated installation](#option-1---automated-installation)
  - [Option 2 - Installation with bring-your-own SQL install Script](#option-2---installation-with-bring-your-own-sql-install-script)
- [REDCap upgrade](#redcap-upgrade)
- [REDCap Basic Admin tasks](#redcap-basic-admin-tasks)
  - [suspend site\_admin](#suspend-site_admin)
- [MSMTP](#msmtp)
- [Cron](#cron)
- [REDCap Application Config vars](#redcap-application-config-vars)
  - [APPLY\_RCCONF\_VARIABLES](#apply_rcconf_variables)
  - [Type reference](#type-reference)
  - [General \& Identity](#general--identity)
  - [System Status \& Maintenance](#system-status--maintenance)
  - [Authentication](#authentication)
    - [Global Authentication Method](#global-authentication-method)
    - [Table-based Authentication](#table-based-authentication)
    - [Shibboleth Authentication](#shibboleth-authentication)
    - [OpenID Connect](#openid-connect)
    - [Azure Active Directory (OAuth2)](#azure-active-directory-oauth2)
    - [Google OAuth2](#google-oauth2)
    - [AAF (Australian Access Federation)](#aaf-australian-access-federation)
    - [SAMS Authentication](#sams-authentication)
  - [Two-Factor Authentication (2FA)](#two-factor-authentication-2fa)
  - [UI \& Branding](#ui--branding)
  - [Email \& Notifications](#email--notifications)
    - [General Email Settings](#general-email-settings)
    - [Alerts Settings](#alerts-settings)
    - [Mailgun](#mailgun)
    - [Mandrill](#mandrill)
    - [SendGrid](#sendgrid)
    - [Azure Communication Services](#azure-communication-services)
  - [User Management](#user-management)
    - [Project \& Account Creation](#project--account-creation)
    - [User Activity \& Suspension](#user-activity--suspension)
    - [User Access Dashboard](#user-access-dashboard)
    - [User Sponsor](#user-sponsor)
    - [User Profile](#user-profile)
    - [Messaging \& Statistics](#messaging--statistics)
  - [Project Settings \& Workflow](#project-settings--workflow)
  - [File Storage](#file-storage)
    - [General File Upload Settings](#general-file-upload-settings)
    - [File Repository](#file-repository)
    - [Local Storage](#local-storage)
    - [Amazon S3](#amazon-s3)
    - [Azure Blob Storage](#azure-blob-storage)
    - [Google Cloud Storage](#google-cloud-storage)
    - [File Upload Vault (WebDAV/SFTP)](#file-upload-vault-webdavsftp)
    - [PDF eConsent Vault (WebDAV/SFTP)](#pdf-econsent-vault-webdavsftp)
    - [Record Locking PDF Vault (WebDAV/SFTP)](#record-locking-pdf-vault-webdavsftp)
  - [PDF eConsent System](#pdf-econsent-system)
  - [Modules \& Features](#modules--features)
    - [REDCap API](#redcap-api)
    - [REDCap Mobile App \& MyCap](#redcap-mobile-app--mycap)
    - [Surveys \& Data Collection](#surveys--data-collection)
    - [Data Visualization](#data-visualization)
    - [Rich Text Editor Features](#rich-text-editor-features)
    - [External Modules](#external-modules)
    - [Randomization](#randomization)
    - [REDCap Shared Library](#redcap-shared-library)
    - [E-Signature](#e-signature)
    - [Field Bank](#field-bank)
    - [Calendar \& Feed](#calendar--feed)
    - [Data Transfer Services (DTS)](#data-transfer-services-dts)
    - [Send-It](#send-it)
    - [Data Entry](#data-entry)
    - [Ontology / BioPortal](#ontology--bioportal)
    - [PROMIS / CATs](#promis--cats)
    - [MTB](#mtb)
  - [Twilio SMS/Voice](#twilio-smsvoice)
  - [Mosio SMS](#mosio-sms)
  - [FHIR / EHR Integration (DDP)](#fhir--ehr-integration-ddp)
    - [Connection Settings](#connection-settings)
    - [Display \& Access](#display--access)
    - [Data Fetch Behavior](#data-fetch-behavior)
    - [Custom Mapping \& Auth](#custom-mapping--auth)
    - [Break-the-Glass (Emergency Access)](#break-the-glass-emergency-access)
  - [Real-time Webservice (Legacy DDP)](#real-time-webservice-legacy-ddp)
  - [Publication Matching](#publication-matching)
  - [REDCap Updates \& Versioning](#redcap-updates--versioning)


# REDCap source download

When `REDCAP_VERSION` is set the container manages the REDCap source files automatically.
See [README — Quick Start](README.md#quick-start) and [REDCAP_UPGRADE.md — Method 1](REDCAP_UPGRADE.md#method-1--automatic-via-redcap_version) for full documentation.

| Variable | Default | Description |
| --- | --- | --- |
| `REDCAP_VERSION` | `` | Desired REDCap version (e.g. `14.9.5`). When set, the container auto-installs on first boot and optionally auto-upgrades on subsequent boots. |
| `REDCAP_AUTO_UPGRADE` | `false` | Set to `true` to allow the container to upgrade automatically when `REDCAP_VERSION` is bumped. Default is conservative — logs a warning but does not upgrade. |
| `REDCAP_COMMUNITY_USER` | `` | REDCap community portal username used for downloading REDCap. Also read by `redcap-upgrade` and `redcap-install`. |
| `REDCAP_COMMUNITY_PASSWORD` | `` | REDCap community portal password. |


# DEBUG

| Variable | Default | Description                                            |
| -------- | ------- | ------------------------------------------------------ |
| `DEBUG`  | `false` | Enables debug mode. Set to `true` for verbose logging. |


This creates some more verbose log messages in the docker scripts if set to true. Its primary intended for developement of this repo.


# PHP

| Variable           | Default                   | Description                                                                     |
| ------------------ | ------------------------- | ------------------------------------------------------------------------------- |
| `TZ`               | `UTC`                     | The timezone of the PHP process. This influences the timestamps of the logfiles |
| `PHP_MEMORY_LIMIT` | `2048M`                   | Maximum memory allowed for PHP scripts.                                         |
| `PHP_INI_SCAN_DIR` | `/config/php/custom_inis` | Which dirs to look for config inis.                                             |


# Apache

| Variable               | Default         | Description                             |
| ---------------------- | --------------- | --------------------------------------- |
| `SERVER_NAME`          | `localhost`     | Maximum memory allowed for PHP scripts. |
| `SERVER_ADMIN`         | `root`          | ...                                     |
| `SERVER_ALIAS`         | `localhost`     | ...                                     |
| `APACHE_RUN_HOME`      | `/var/www`      | ...                                     |
| `APACHE_DOCUMENT_ROOT` | `/var/www/html` | ...                                     |
| `APACHE_ERROR_LOG`     | `/dev/stdout`   | ...                                     |
| `APACHE_ACCESS_LOG`    | `/dev/stdout`   | ...                                     |

# www-data user and group ID

| Variable       | Default | Description |
| -------------- | ------- | ----------- |
| `WWW_DATA_UID` | `33`    | ...         |
| `WWW_DATA_GID` | `33`    | ...         |

By default the system user that runs the apache process is www-data with the uid/gid `33`/`33`.
You can changes this with the two env vars `WWW_DATA_UID` and `WWW_DATA_GID`
This way you could give the REDCap files the same ownership as your host system user.


# Run custom or upgrade SQL Scripts at boot

| Variable                                | Default                                   | Description |
| --------------------------------------- | ----------------------------------------- | ----------- |
| `AT_BOOT_RUN_SQL_SCRIPTS_FROM_LOCATION` | `/opt/redcap-docker/sql_scripts_run_once` | ...         |



With the env var `AT_BOOT_RUN_SQL_SCRIPTS_FROM_LOCATION` you can set a location to a local single file, a local directory or a remote http file or directory.

If the file(s) end with the extension `sql` they will be pickd up and run at the mysql database at next (re-)boot.  

Each SQL file(s) will be remembered (by hash) and not run again at the following (re-)boot.

This can be handy for RedCap upgrade procedures, that need you to run a sql script. see the document [REDCAP_UPGRADE.md](/REDCAP_UPGRADE.md) for more info
  
It defaults to `/opt/redcap-docker/sql_scripts_run_once`




# Fix REDCap source files/directory permissions

| Variable                     | Default | Description |
| ---------------------------- | ------- | ----------- |
| `FIX_REDCAP_DIR_PERMISSIONS` | `true`  | ...         |



Set `FIX_REDCAP_DIR_PERMISSIONS` to false, if the container should not apply the user `www-data` to be the owner of the REDCap source directory and files on startup.

Defaults to `true`

> [!TIP]
> If you are not happy with the UID/GID of the user www-data have a look at [`WWW_DATA_UID`/`WWW_DATA_GID`](#www-data-user-and-group-id).



# REDCap database connection environment variable


| Variable               | Default | Description                                                                          |
| ---------------------- | ------- | ------------------------------------------------------------------------------------ |
| `DB_PORT`              | `null`  | ...                                                                                  |
| `DB_HOSTNAME`          | ``      | ...                                                                                  |
| `DB_NAME`              | ``      | ...                                                                                  |
| `DB_USERNAME`          | ``      | ...                                                                                  |
| `DB_PASSWORD`          | ``      | ...                                                                                  |
| `DB_SSL_KEY_PATH`      | ``      | The path name to the key file                                                        |
| `DB_SSL_CERT_PATH`     | ``      | The path name to the certificate file.                                               |
| `DB_SSL_CA_FILE_PATH`  | ``      | ...                                                                                  |
| `DB_SSL_CA_DIR_PATH`   | ``      | The pathname to a directory that contains trusted SSL CA certificates in PEM format. |
| `DB_SSL_ALGOS`         | `null`  | A list of allowable ciphers to use for SSL encryption.                               |
| `DB_SALT`              | ``      | ...                                                                                  |
| `DB_SSL_VERIFY_SERVER` | `false` | ...                                                                                  |




## REDCap Data Transfer Services

| Variable       | Default | Description |
| -------------- | ------- | ----------- |
| `DTS_HOSTNAME` | ``      | ...         |
| `DTS_DB`       | ``      | ...         |
| `DTS_USERNAME` | ``      | ...         |
| `DTS_PASSWORD` | ``      | ...         |

# User provisioning

| Variable       | Default | Description |
| -------------- | ------- | ----------- |
| `ENABLE_USER_PROV` | `true`      | switch to false to disable user provisoning completly         |
| `USER_PROV_FILE_DIR`       | `/opt/redcap-docker/users`      | A path that will be scanned for json or yaml files with user data for the user provisioning         |
| `USER_PROV_OVERWRITE_EXISTING` | `false`      | if set top true existing users in the REDCap database with the same username will be overwriten         |
| `USER_PROV` | `[]`      | Multiple users data as json. see [User provisioning](USER_PROV.md) for details and format         |
| `USER_PROV_*` | `null`      | Single User data as indexed json. e.g. `USER_PROV_1={"username": "user12"...` see [User provisioning](USER_PROV.md) for details and format         |


This container image can prefill the database with table users. for more details have a look at [User provisioning](USER_PROV.md)


# REDCap installation

This image tries to automate the "installation" of REDCap. In REDCap context "installation" means: Deploying the database schema and inserting some basic data.
We try to extract the SQL scripts from the REDCap source you provided. A second option is that you provide the generated SQL Script yourself (like in a classic REDCap installation).

## Option 1 - automated installation 

The default option to install REDCap. Runs the build-in install script, from the mounted redcap source, if there is not a `redcap_config`-table in the existing database. 
(If a `redcap_config`-table is existing, this container makes the assumption REDCap is allready installed. Which may not true in all cases, in which case you have to install manually)

`REDCAP_INSTALL_ENABLE`

can be `true` or `false`
defaults to `true`

## Option 2 - Installation with bring-your-own SQL install Script

With this docker image you can provide the installation script generated by REDCaps `/install.php` via a path defined in the env var

`REDCAP_INSTALL_SQL_SCRIPT_PATH`

It defaults to `/config/redcap/install/install.sql` 

**Hint**: You still need to set `REDCAP_INSTALL_ENABLE` to true

# REDCap upgrade

See [REDCAP_UPGRADE.md](REDCAP_UPGRADE.md) for full documentation.

| Variable | Default | Description |
| --- | --- | --- |
| `REDCAP_UPGRADE_BACKUP_DIR` | `/opt/redcap-docker/backups` | Directory where `redcap-upgrade` stores pre-upgrade database backups. |
| `REDCAP_UPGRADE_BACKUP_DB_USER` | `` | Optional elevated database user for backups (e.g. `root`). Use when the application DB user lacks `mysqldump` privileges. |
| `REDCAP_UPGRADE_BACKUP_DB_PASSWORD` | `` | Password for `REDCAP_UPGRADE_BACKUP_DB_USER`. |

# REDCap Basic Admin tasks

## suspend site_admin

```env
REDCAP_SUSPEND_SITE_ADMIN # Default: True
```


# MSMTP

You can set all msmtp config vars via environment. Just prefix the msmpt config commands/params `MSMTP_`

For a list of all config commands see
* https://marlam.de/msmtp/msmtp.html#General-commands
* https://marlam.de/msmtp/msmtp.html#Authentication-commands
* https://marlam.de/msmtp/msmtp.html#TLS-commands
* https://marlam.de/msmtp/msmtp.html#Commands-specific-to-sendmail-mode

Example for sending mails via a Hetzner mail account: 

```env
MSMTP_from=redcap-system@wy-corp.earth
MSMTP_host=mail.your-server.de
MSMTP_port=587
MSMTP_auth=on
MSMTP_user=redcap-system@wy-corp.earth
MSMTP_password=mytotalsecretpassword
MSMTP_tls=on
MSMTP_tls_starttls=on
MSMTP_syslog=on
RCCONF_from_email=redcap-system@wy-corp.earth
```
# Cron

`CRON_MODE` - default `false`  
If you set `CRON_MODE` to true the container will not start the REDCap webserver but run the REDCap cron job in an intervall.
  
`CRON_INTERVAL` - default `*/5 * * * *`  
With `CRON_INTERVAL` you can define the interval how often the REDCap cronjob should run. Only available with `CRON_MODE=true`


`CRON_RUN_JOB_ON_START` - default `false`  
If you want to run the job as soon the container starts you set this to true. Only available with `CRON_MODE=true`


see the [cron example](examples/instance_with_cron) for a docker compose exmaple.

# REDCap Application Config vars

This container enables you to set any configuration variable from the `redcap_config` database table — everything configurable in the REDCap web UI under **Control Center** — as a `RCCONF_*` environment variable.

The environment variable name is always `RCCONF_` + the `field_name` from `redcap_config`.  
Example: the database field `auth_meth_global` becomes the environment variable `RCCONF_auth_meth_global`.

## APPLY_RCCONF_VARIABLES

```env
APPLY_RCCONF_VARIABLES # Default: false
```

If set to `true`, all supplied `RCCONF_*` environment variables are written to the database on **every container start**, overwriting any changes made via the web interface since the last boot.

Set to `false` after initial setup if you want the configuration to be applied only once.

## Type reference

| Type | Meaning |
|---|---|
| `boolean` | `0` = disabled/false, `1` = enabled/true |
| `integer` | A whole number |
| `string` | Free-form text |
| `enum` | One of a fixed set of values (see *Allowed Values* column) |
| `URL` | A full URL (e.g. `https://example.com/path/`) |
| `path` | A filesystem path on the container/server |
| `email` | A single email address |
| `secret` | Sensitive credential (key, password, token) — handle with care |
| `JSON` | A JSON-encoded string |

---

## General & Identity

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_institution` | string | `` | Free text | Name of the institution running REDCap, displayed across the UI. |
| `RCCONF_site_org_type` | string | `` | Free text | Organization type/sub-unit within the institution (optional). |
| `RCCONF_redcap_base_url` | URL | `` | e.g. `https://redcap.example.com/redcap/` | Public base URL of this REDCap installation; used in links sent by email and in the API. |
| `RCCONF_redcap_base_url_display_error_on_mismatch` | boolean | `1` | `0`, `1` | Show a warning in the Control Center when the detected request URL differs from `redcap_base_url`. |
| `RCCONF_redcap_survey_base_url` | URL | `` | Full URL | Alternate base URL for survey links when surveys are served from a different domain. |
| `RCCONF_is_development_server` | boolean | `0` | `0`, `1` | Marks this instance as a dev/test server. May affect certain behaviors and display a banner. |
| `RCCONF_language_global` | string | `English` | Language name (e.g. `English`, `Deutsch`) | Global UI language for all REDCap pages. |
| `RCCONF_project_language` | string | `English` | Language name | Default language for newly created projects. |
| `RCCONF_project_encoding` | enum | `` | `` (default ANSI/UTF-8), `Shift_JIS` | Character encoding for PDF and CSV exports in projects. |
| `RCCONF_default_datetime_format` | enum | `M/D/Y_12` | `M-D-Y_24`, `M-D-Y_12`, `M/D/Y_24`, `M/D/Y_12`, `M.D.Y_24`, `M.D.Y_12`, `D-M-Y_24`, `D-M-Y_12`, `D/M/Y_24`, `D/M/Y_12`, `D.M.Y_24`, `D.M.Y_12`, `Y-M-D_24`, `Y-M-D_12`, `Y/M/D_24`, `Y/M/D_12`, `Y.M.D_24`, `Y.M.D_12` | Default date/time display format for new users. |
| `RCCONF_default_number_format_decimal` | enum | `.` | `.` or `,` | Default decimal separator for numbers. |
| `RCCONF_default_number_format_thousands_sep` | enum | `,` | `,`, `.`, `` (none) | Default thousands separator for numbers. |
| `RCCONF_default_csv_delimiter` | enum | `,` | `,`, `;`, `\t` (tab) | Default column delimiter for CSV exports. |

---

## System Status & Maintenance

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_system_offline` | boolean | `0` | `0`, `1` | Put REDCap in offline mode — normal users are denied access to all pages; admins can still log in. |
| `RCCONF_system_offline_message` | string | `` | HTML allowed | Custom message displayed to users when the system is offline. |
| `RCCONF_enable_http_compression` | boolean | `1` | `0`, `1` | Enable gzip HTTP compression of HTML pages, making pages load 2–5× faster. |
| `RCCONF_page_hit_threshold_per_minute` | integer | `600` | e.g. `600` | Rate limiter: maximum web requests per minute from a single IP. Exceeding this bans the IP and notifies admins. |
| `RCCONF_allow_outbound_http` | boolean | `1` | `0`, `1` | Allow REDCap to make outbound HTTP/HTTPS requests (required by FHIR endpoints, API integrations, etc.). |
| `RCCONF_override_system_bundle_ca` | boolean | `1` | `0`, `1` | Use REDCap's bundled CA store instead of the server's system CA bundle when verifying TLS certificates for outbound requests. |
| `RCCONF_cross_domain_access_control` | string | `` | Comma-separated domains | Domains added to `Access-Control-Allow-Origin` for CORS. Leave blank to disable. |
| `RCCONF_clickjacking_prevention` | boolean | `0` | `0`, `1` | Adds `X-Frame-Options: SAMEORIGIN` response header to prevent clickjacking. |
| `RCCONF_allow_kill_mysql_process` | boolean | `0` | `0`, `1` | Allow REDCap to forcibly terminate long-running MySQL queries. |
| `RCCONF_db_binlog_format` | enum | `` | `ROW`, `MIXED`, `STATEMENT`, `` (server default) | MySQL binary log format used by REDCap. |
| `RCCONF_db_collation` | string | `utf8mb4_unicode_ci` | MySQL collation name | Database collation used by REDCap. |
| `RCCONF_db_character_set` | string | `utf8mb4` | MySQL charset name | Database character set used by REDCap. |
| `RCCONF_read_replica_enable` | boolean | `0` | `0`, `1` | Enable read-replica database support (requires additional DB configuration). |
| `RCCONF_proxy_hostname` | string | `` | `[http://]host:port` | Outbound HTTP proxy server. Append a colon and port number; prepend `http://` or `https://`. |
| `RCCONF_proxy_username_password` | secret | `` | `username:password` | Credentials for the outbound HTTP proxy (if required). |
| `RCCONF_cache_storage_system` | enum | `file` | `file`, `redis` | Caching backend for REDCap internal caches. |
| `RCCONF_cache_files_filesystem_path` | path | `` | Server path | Directory for file-based cache storage when `cache_storage_system=file`. |
| `RCCONF_hook_functions_file` | path | `` | Full server path | Full path to the PHP file containing REDCap hook functions. |
| `RCCONF_auto_report_stats` | boolean | `1` | `0`, `1` | Automatically send anonymous usage statistics to the REDCap Consortium. |
| `RCCONF_report_stats_url` | URL | `` | Full URL | URL endpoint to which REDCap submits consortium statistics. |
| `RCCONF_send_emails_admin_tasks` | boolean | `1` | `0`, `1` | Send email notifications to administrators for routine admin tasks (e.g. cron errors). |
| `RCCONF_autologout_timer` | integer | `30` | Minutes; `0` = disabled | Auto-logout users after this many minutes of inactivity (with a 2-minute warning). |

---

## Authentication

### Global Authentication Method

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_auth_meth_global` | enum | `none` | `none`, `table`, `ldap`, `ldap_table`, `shibboleth`, `shibboleth_table`, `openid_google`, `oauth2_azure_ad`, `oauth2_azure_ad_table`, `rsa`, `sams`, `aaf`, `aaf_table`, `openid_connect`, `openid_connect_table` | Global authentication method. `none` = no login required. `table` = built-in username/password. `*_table` variants allow a local-login fallback. |
| `RCCONF_config_settings_key` | secret | `` | Arbitrary string | Encryption key used to protect sensitive config values stored in the database. |

### Table-based Authentication

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_password_algo` | enum | `md5` | `md5`, `phpass` | Password hashing algorithm. `phpass` (bcrypt) is strongly recommended over `md5`. |
| `RCCONF_password_length` | integer | `9` | ≥ 1 | Minimum required password length for table-based accounts. |
| `RCCONF_password_complexity` | boolean | `1` | `0`, `1` | Require mixed-case letters, numbers, and symbols in passwords. |
| `RCCONF_password_history_limit` | integer | `0` | `0` = disabled | Prevent reuse of the N most recent passwords. |
| `RCCONF_password_reset_duration` | integer | `0` | Days; `0` = disabled | Force users to change their password every N days. |
| `RCCONF_password_recovery_custom_text` | string | `` | HTML | Custom text shown on the password recovery page. |
| `RCCONF_login_autocomplete_disable` | boolean | `0` | `0`, `1` | Disable browser autocomplete on username/password fields (increases security on shared computers). |
| `RCCONF_logout_fail_limit` | integer | `5` | `0` = disabled | Number of consecutive failed login attempts before the account is temporarily locked. |
| `RCCONF_logout_fail_window` | integer | `15` | Minutes | Duration of the lockout period after too many failed login attempts. |
| `RCCONF_enable_user_allowlist` | boolean | `0` | `0`, `1` | When enabled, only users on an explicit allowlist may access REDCap. |
| `RCCONF_email_domain_allowlist` | string | `` | One domain per line (e.g. `vanderbilt.edu`) | Restrict user account email addresses to only the listed domains. |

### Shibboleth Authentication

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_shibboleth_username_field` | string | `none` | Apache env-var name, or `none` | Name of the Apache/Shibboleth environment variable that contains the authenticated username (e.g. `REMOTE_USER`). |
| `RCCONF_shibboleth_logout` | URL | `` | Full URL | Redirect target for single-logout with the Shibboleth IdP. |
| `RCCONF_shibboleth_table_config` | JSON | *(complex default)* | JSON object | Configures the mixed Shibboleth + table-based login splash screen (institution list, login options). |

### OpenID Connect

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_openid_connect_name` | string | `` | Free text | Display name for the OpenID Connect provider shown on the login page. |
| `RCCONF_openid_connect_provider_url` | URL | `` | Full URL | OpenID Connect issuer URL. |
| `RCCONF_openid_connect_metadata_url` | URL | `` | Full URL | URL to the provider's `.well-known/openid-configuration` document. |
| `RCCONF_openid_connect_client_id` | string | `` | OAuth2 client ID | Client ID registered with the OpenID Connect provider. |
| `RCCONF_openid_connect_client_secret` | secret | `` | OAuth2 client secret | Client secret registered with the OpenID Connect provider. |
| `RCCONF_openid_connect_username_attribute` | string | `username` | JWT/userinfo claim name | Claim used as the REDCap username. |
| `RCCONF_openid_connect_additional_scope` | string | `` | Space-separated scope names | Additional OAuth2 scopes to request beyond `openid`. |
| `RCCONF_openid_connect_response_type` | enum | `query` | `query`, `form_post` | How the authorization code is returned from the provider. |
| `RCCONF_openid_connect_primary_admin` | string | `` | REDCap username | Username that is automatically granted super-user rights when logging in via this provider. |
| `RCCONF_openid_connect_secondary_admin` | string | `` | REDCap username | Secondary username granted admin rights via this provider. |
| `RCCONF_openid_provider_url` | URL | `` | Full URL | Legacy OpenID 2.0 provider URL (distinct from OpenID Connect). |
| `RCCONF_openid_provider_name` | string | `` | Free text | Display name for the legacy OpenID 2.0 provider. |

### Azure Active Directory (OAuth2)

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_oauth2_azure_ad_name` | string | `` | Free text | Display name for the Azure AD login button on the login page. |
| `RCCONF_oauth2_azure_ad_tenant` | string | `common` | Tenant GUID, domain, or `common` | Azure AD tenant. Use `common` for multi-tenant apps or a specific tenant ID/domain. |
| `RCCONF_oauth2_azure_ad_client_id` | string | `` | UUID | Azure AD application (client) ID. |
| `RCCONF_oauth2_azure_ad_client_secret` | secret | `` | String | Azure AD application client secret. |
| `RCCONF_oauth2_azure_ad_username_attribute` | string | `userPrincipalName` | Claim name | Azure AD claim used as the REDCap username (e.g. `userPrincipalName`, `mail`). |
| `RCCONF_oauth2_azure_ad_endpoint_version` | enum | `V1` | `V1`, `V2` | Azure AD OAuth2 endpoint version. Use `V2` for modern app registrations. |
| `RCCONF_oauth2_azure_ad_primary_admin` | string | `` | REDCap username | Username automatically granted admin rights when logging in via Azure AD. |
| `RCCONF_oauth2_azure_ad_secondary_admin` | string | `` | REDCap username | Secondary username granted admin rights via Azure AD. |

### Google OAuth2

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_google_oauth2_client_id` | string | `` | OAuth2 client ID | Google OAuth2 client ID for `openid_google` authentication. |
| `RCCONF_google_oauth2_client_secret` | secret | `` | OAuth2 client secret | Google OAuth2 client secret. |

### AAF (Australian Access Federation)

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_aafAccessUrl` | URL | `` | Full URL | AAF Rapid Connect access URL. |
| `RCCONF_aafAud` | string | `` | URL | AAF audience (`aud` claim) — typically this application's base URL. |
| `RCCONF_aafIss` | string | `` | URL | AAF issuer URL (`iss` claim). |
| `RCCONF_aafPrimaryField` | string | `` | Claim name | AAF attribute used as the REDCap username. |
| `RCCONF_aafScopeTarget` | string | `` | String | AAF scope target. |
| `RCCONF_aafAllowLocalsCreateDB` | boolean | `` | `0`, `1` | Allow locally authenticated (non-AAF) users to create projects. |
| `RCCONF_aafDisplayOnEmailUsers` | boolean | `` | `0`, `1` | Show the AAF login option to email-identified users. |

### SAMS Authentication

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_sams_logout` | URL | `` | Full URL | URL for the SAMS single-logout endpoint. |

---

## Two-Factor Authentication (2FA)

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_two_factor_auth_enabled` | boolean | `0` | `0`, `1` | Enable two-factor authentication globally. |
| `RCCONF_two_factor_auth_enforce_table_users_only` | boolean | `0` | `0`, `1` | Enforce 2FA only for table-based (local) users, not external-auth users. |
| `RCCONF_two_factor_auth_esign_pin` | boolean | `0` | `0`, `1` | Require 2FA verification for e-signature actions. |
| `RCCONF_two_factor_auth_email_enabled` | boolean | `1` | `0`, `1` | Allow users to use email OTP for 2FA. |
| `RCCONF_two_factor_auth_authenticator_enabled` | boolean | `1` | `0`, `1` | Allow users to use a TOTP authenticator app for 2FA. |
| `RCCONF_two_factor_auth_ip_check_enabled` | boolean | `0` | `0`, `1` | Skip 2FA for connections from trusted IP ranges (defined by the fields below). |
| `RCCONF_two_factor_auth_ip_range` | string | `` | CIDR ranges, one per line | Primary set of IP ranges (CIDR notation) exempt from 2FA. |
| `RCCONF_two_factor_auth_ip_range_alt` | string | `` | CIDR ranges, one per line | Secondary set of IP ranges exempt from 2FA (with a separate trust period). |
| `RCCONF_two_factor_auth_ip_range_include_private` | boolean | `0` | `0`, `1` | Automatically exempt all RFC 1918 private IP ranges from 2FA. |
| `RCCONF_two_factor_auth_trust_period_days` | integer | `0` | Days; `0` = ask every login | Days a browser/device is trusted before 2FA is required again (primary IP range). |
| `RCCONF_two_factor_auth_trust_period_days_alt` | integer | `0` | Days; `0` = ask every login | Trust period for the alternate IP range. |
| `RCCONF_two_factor_auth_twilio_enabled` | boolean | `0` | `0`, `1` | Allow users to use Twilio SMS/voice for 2FA. |
| `RCCONF_two_factor_auth_twilio_account_sid` | string | `` | Twilio Account SID | Twilio account SID for 2FA SMS delivery. |
| `RCCONF_two_factor_auth_twilio_auth_token` | secret | `` | Twilio Auth Token | Twilio authentication token for 2FA SMS delivery. |
| `RCCONF_two_factor_auth_twilio_from_number` | string | `` | E.164 format (e.g. `+15551234567`) | Twilio number used as the SMS sender for 2FA codes. |
| `RCCONF_two_factor_auth_twilio_from_number_voice_alt` | string | `` | E.164 format | Alternative Twilio number used for voice-call 2FA delivery. |
| `RCCONF_two_factor_auth_duo_enabled` | boolean | `0` | `0`, `1` | Enable Duo Security for 2FA. |
| `RCCONF_two_factor_auth_duo_ikey` | string | `` | Duo Integration Key | Duo integration key (from the Duo Admin Panel). |
| `RCCONF_two_factor_auth_duo_skey` | secret | `` | Duo Secret Key | Duo secret key. |
| `RCCONF_two_factor_auth_duo_hostname` | string | `` | e.g. `api-XXXXXXXX.duosecurity.com` | Duo API hostname. |

---

## UI & Branding

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_headerlogo` | URL | `` | Full URL (max 650 px wide) | Logo image displayed in the REDCap page header. |
| `RCCONF_login_logo` | URL | `` | Full URL (max 750 px wide) | Logo image displayed on the login page. |
| `RCCONF_login_custom_text` | string | `` | HTML | Custom HTML displayed above the login form (below the login logo, if set). |
| `RCCONF_homepage_custom_text` | string | `` | HTML | Custom HTML shown on the REDCap home page. |
| `RCCONF_homepage_announcement` | string | `` | HTML | Announcement text at the top of the Home and My Projects pages. Useful for downtime notices, training announcements, etc. |
| `RCCONF_homepage_announcement_login` | boolean | `1` | `0`, `1` | Also show the homepage announcement on the login page. |
| `RCCONF_homepage_contact` | string | `` | Free text | Contact person name displayed on the home page. |
| `RCCONF_homepage_contact_email` | email | `` | Email address | Contact email address on the home page. |
| `RCCONF_homepage_contact_url` | URL | `` | Full URL | URL for the contact link on the home page. |
| `RCCONF_homepage_grant_cite` | string | `` | Free text | Grant citation text shown on the home page. |
| `RCCONF_grant_cite` | string | `` | Free text | Global grant citation (fallback when no project-level value is set). |
| `RCCONF_contact_admin_button_url` | URL | `` | Full URL | Destination for the "Contact REDCap Administrator" button. If blank, defaults to composing an email. |
| `RCCONF_helpfaq_custom_text` | string | `` | HTML | Custom text shown at the top of the Help & FAQ page. |
| `RCCONF_footer_links` | string | `` | One `URL,Link text` per line | Links displayed at the bottom of project pages. |
| `RCCONF_footer_text` | string | `` | HTML | Custom text displayed below the footer links. |
| `RCCONF_certify_text_create` | string | `` | HTML | Pop-up certification text that users must read before creating or copying a project. |
| `RCCONF_certify_text_prod` | string | `` | HTML | Pop-up certification text that users must read before moving a project to production. |
| `RCCONF_google_translate_enabled` | boolean | `0` | `0`, `1` | Show the Google Translate widget on REDCap pages. |
| `RCCONF_googlemap_key` | secret | `` | Google Maps API key | API key for Google Maps integration (used by mapping-enabled fields). |
| `RCCONF_display_project_logo_institution` | boolean | `0` | `0`, `1` | Display the institution logo and name at the top of every project page. |
| `RCCONF_display_today_now_button` | boolean | `1` | `0`, `1` | Show a "Today" / "Now" quick-fill button next to date and time fields. |
| `RCCONF_enable_url_shortener` | boolean | `1` | `0`, `1` | Enable URL shortening for survey and project links within projects. |
| `RCCONF_enable_url_shortener_redcap` | boolean | `1` | `0`, `1` | Enable the URL Shortener page in the Control Center (site-wide shortener). Separate from per-project shortening. |

---

## Email & Notifications

### General Email Settings

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_from_email` | email | `` | Email address | The global "From" address for all system emails sent by REDCap. |
| `RCCONF_from_email_domain_exclude` | string | `` | Comma-separated domains | Email domains blocked from being used as the "From" address in alerts/invitations. |
| `RCCONF_use_email_display_name` | boolean | `1` | `0`, `1` | Include a display name (e.g. "John Smith via REDCap") in the From header of outgoing emails. |
| `RCCONF_dkim_private_key` | secret | `` | PEM-encoded RSA private key | DKIM private key for signing outgoing emails, improving deliverability and reducing spam-folder placement. |
| `RCCONF_email_logging_enable_global` | boolean | `1` | `0`, `1` | Log all emails sent by REDCap to the email log visible in the Control Center. |
| `RCCONF_email_logging_install_time` | string | `now()` | MySQL datetime | Timestamp when email logging was first enabled. Set automatically at install time. |
| `RCCONF_protected_email_mode_global` | boolean | `1` | `0`, `1` | Enable "Protected Email Mode" — certain automated emails are suppressed or redirected to protect participant privacy. |
| `RCCONF_email_alerts_converter_enabled` | boolean | `0` | `0`, `1` | Enable the automatic conversion of legacy email alerts to the newer Alerts & Notifications system. |

### Alerts Settings

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_alerts_allow_email_variables` | boolean | `1` | `0`, `1` | Allow alerts to use a project field value as the recipient email address (via piping). |
| `RCCONF_alerts_allow_email_freeform` | boolean | `1` | `0`, `1` | Allow alerts to send to arbitrary freeform email addresses. |
| `RCCONF_alerts_email_freeform_domain_allowlist` | string | `` | Comma-separated domains | When set, freeform alert recipient addresses must match one of these domains. |
| `RCCONF_alerts_allow_phone_variables` | boolean | `1` | `0`, `1` | Allow alerts to use a field value as the SMS recipient phone number. |
| `RCCONF_alerts_allow_phone_freeform` | boolean | `1` | `0`, `1` | Allow alerts to send SMS to arbitrary freeform phone numbers. |

### Mailgun

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_mailgun_api_key` | secret | `` | Mailgun API key | API key for sending transactional email via Mailgun. |
| `RCCONF_mailgun_api_endpoint` | URL | `` | e.g. `https://api.mailgun.net/` or `https://api.eu.mailgun.net/` | Mailgun API base URL. Use the EU endpoint for EU data-residency requirements. |
| `RCCONF_mailgun_domain_name` | string | `` | e.g. `mg.example.com` | Mailgun sending domain. |

### Mandrill

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_mandrill_api_key` | secret | `` | Mandrill API key | API key for sending email via Mandrill (Mailchimp Transactional Email). |

### SendGrid

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_sendgrid_enabled_global` | boolean | `1` | `0`, `1` | Enable SendGrid as an email delivery option globally. |
| `RCCONF_sendgrid_enabled_by_super_users_only` | boolean | `0` | `0`, `1` | Restrict SendGrid usage to projects enabled by super users only. |
| `RCCONF_sendgrid_display_info_project_setup` | boolean | `0` | `0`, `1` | Show SendGrid setup information on the Project Setup page. Requires `sendgrid_enabled_by_super_users_only=1`. |
| `RCCONF_sendgrid_api_key` | secret | `` | SendGrid API key | API key for sending email via SendGrid. |

### Azure Communication Services

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_azure_comm_api_endpoint` | URL | `` | Full URL | Azure Communication Services endpoint URL for email delivery. |
| `RCCONF_azure_comm_api_key` | secret | `` | Access key | Azure Communication Services access key. |

---

## User Management

### Project & Account Creation

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_allow_create_db_default` | boolean | `1` | `0`, `1` | Default permission for new users to create projects. Can be overridden per user. |
| `RCCONF_superusers_only_create_project` | boolean | `0` | `0`, `1` | Restrict project creation to administrators/super-users only. Regular users get a "Request" option. |
| `RCCONF_superusers_only_move_to_prod` | boolean | `1` | `0`, `1` | Restrict moving projects to production to administrators only. Regular users get a "Request" option. |
| `RCCONF_new_form_default_prod_user_access` | boolean | `1` | `0`, `1` | When a new instrument is added to a production project, automatically grant all existing users access to it. |
| `RCCONF_admin_email_external_user_creation` | boolean | `0` | `0`, `1` | Email an admin notification when a new user account is created via external authentication. |
| `RCCONF_user_welcome_email_external_user_creation` | boolean | `0` | `0`, `1` | Send a welcome email to the user when their account is created via external authentication. |

### User Activity & Suspension

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_suspend_users_inactive_type` | enum | `` | `` (disabled), `suspend`, `delete` | Action to take on accounts that have been inactive for the configured number of days. |
| `RCCONF_suspend_users_inactive_days` | integer | `180` | Days | Inactivity threshold (in days) that triggers the suspension/deletion action. |
| `RCCONF_suspend_users_inactive_send_email` | boolean | `1` | `0`, `1` | Notify the user by email before their account is suspended or deleted. |

### User Access Dashboard

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_user_access_dashboard_enable` | boolean | `1` | `0`, `1` | Enable the User Access Dashboard in the Control Center. |
| `RCCONF_user_access_dashboard_custom_notification` | string | `` | HTML | Custom notification message displayed inside the User Access Dashboard. |

### User Sponsor

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_user_sponsor_dashboard_enable` | boolean | `1` | `0`, `1` | Enable the User Sponsor feature, allowing designated users to approve (sponsor) other users. |
| `RCCONF_user_sponsor_set_expiration_days` | integer | `365` | Days | Default account expiration (days from now) applied when a sponsor approves a user account. |

### User Profile

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_my_profile_enable_edit` | boolean | `1` | `0`, `1` | Allow users to edit their first and last name on the My Profile page. |
| `RCCONF_my_profile_enable_primary_email_edit` | boolean | `1` | `0`, `1` | Allow users to change their primary email address on the My Profile page. |

### Messaging & Statistics

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_user_messaging_enabled` | boolean | `1` | `0`, `1` | Enable the REDCap Messenger (internal direct messaging between users). |
| `RCCONF_user_messaging_prevent_admin_messaging` | boolean | `0` | `0`, `1` | Prevent administrators from sending messages via the REDCap Messenger. |

---

## Project Settings & Workflow

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_auto_prod_changes` | enum | `4` | `1` = never auto-approve; `2` = only if new fields added; `3` = no records or only new fields; `4` = no critical issues; `5` = no records or no critical issues | Auto-approve Draft Mode changes in production projects when certain conditions are met (from most strict to most lenient). |
| `RCCONF_auto_prod_changes_check_identifiers` | boolean | `0` | `0`, `1` | Also check for identifier fields when auto-approving production Draft Mode changes. |
| `RCCONF_enable_edit_prod_events` | boolean | `1` | `0`, `1` | Allow normal users to add or modify events/arms on the Define My Events page of longitudinal production projects. |
| `RCCONF_enable_edit_prod_repeating_setup` | boolean | `1` | `0`, `1` | Allow normal users to edit repeating instrument/event configuration in production projects. |
| `RCCONF_enable_edit_survey_response` | boolean | `1` | `0`, `1` | Allow users with appropriate rights to edit survey responses already submitted by respondents. |
| `RCCONF_enable_projecttype_forms` | boolean | `1` | `0`, `1` | Enable the "Data Collection with Forms" project type. |
| `RCCONF_enable_projecttype_singlesurvey` | boolean | `1` | `0`, `1` | Enable the "Survey" (single-survey) project type. |
| `RCCONF_enable_projecttype_singlesurveyforms` | boolean | `1` | `0`, `1` | Enable the "Traditional Data Collection + Survey" project type. |
| `RCCONF_survey_pid_create_project` | integer | `` | REDCap project ID | PID of the project serving as the "Request a New Project" survey. |
| `RCCONF_survey_pid_move_to_prod_status` | integer | `` | REDCap project ID | PID of the project serving as the "Request to Move to Production" survey. |
| `RCCONF_survey_pid_move_to_analysis_status` | integer | `` | REDCap project ID | PID of the project serving as the "Request to Move to Analysis" survey. |
| `RCCONF_survey_pid_mark_completed` | integer | `` | REDCap project ID | PID of the project used for survey-based project completion marking. |
| `RCCONF_project_contact_name` | string | `` | Free text | Default administrator contact name shown to users in projects. |
| `RCCONF_project_contact_email` | email | `` | Email address | Default administrator contact email shown to users in projects. |
| `RCCONF_identifier_keywords` | string | `name, street, address, ...` | Comma-separated keywords | Keywords used to flag potentially identifying fields in the de-identification/codebook module. |
| `RCCONF_allow_auto_variable_naming` | enum | `2` | `0` = off, `1` = on (silent), `2` = prompt user | Whether REDCap auto-generates variable names from field labels in the Online Designer. |
| `RCCONF_field_comment_log_enabled_default` | boolean | `1` | `0`, `1` | Enable the Field Comment Log by default for new projects. |

---

## File Storage

### General File Upload Settings

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_edoc_field_option_enabled` | boolean | `1` | `0`, `1` | Enable the "File Upload" field type for use in data-collection forms and surveys. |
| `RCCONF_edoc_upload_max` | integer | `` | MB; blank = PHP server default | Maximum file size (MB) for uploads in "File Upload" fields. |
| `RCCONF_file_attachment_upload_max` | integer | `` | MB; blank = PHP server default | Maximum file size (MB) for file attachments in the rich text editor. |
| `RCCONF_restricted_upload_file_types` | string | *(long list)* | Comma-separated extensions (without dot) | File extensions blocked from upload (e.g. `exe, bat, php`). Default list covers common dangerous types. |
| `RCCONF_file_upload_versioning_enabled` | boolean | `1` | `0`, `1` | Enable file version history at the project level (project admins can toggle per-field). |
| `RCCONF_file_upload_versioning_global_enabled` | boolean | `1` | `0`, `1` | Master switch for file version history. Must be enabled before individual projects can use it. |
| `RCCONF_drw_upload_option_enabled` | boolean | `1` | `0`, `1` | Enable the "Draw / Signature" upload option in File Upload fields. |

### File Repository

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_file_repository_enabled` | boolean | `1` | `0`, `1` | Enable the File Repository module in projects. |
| `RCCONF_file_repository_upload_max` | integer | `` | MB; blank = PHP server default | Maximum file size (MB) for files uploaded to the File Repository. |
| `RCCONF_file_repository_allow_public_link` | boolean | `1` | `0`, `1` | Allow File Repository files to be shared via public (unauthenticated) links. |
| `RCCONF_file_repository_total_size` | integer | `` | MB; blank = no limit | Maximum total size (MB) of all files in a project's File Repository. |

### Local Storage

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_edoc_storage_option` | enum | `0` | `0` = local filesystem, `1` = WebDAV, `2` = Amazon S3, `3` = Azure Blob Storage, `4` = Google Cloud Storage | Storage backend for all uploaded files in REDCap. |
| `RCCONF_edoc_path` | path | `` | Server path | Custom directory for local file storage. Defaults to the `edocs` folder in the REDCap web root. Should be outside the web root for security. |

### Amazon S3

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_amazon_s3_key` | secret | `` | AWS Access Key ID | AWS IAM access key ID for S3 storage. |
| `RCCONF_amazon_s3_secret` | secret | `` | AWS Secret Access Key | AWS IAM secret access key for S3 storage. |
| `RCCONF_amazon_s3_bucket` | string | `` | Bucket name | S3 bucket name where REDCap stores files. |
| `RCCONF_amazon_s3_endpoint` | URL | `` | Full URL | S3 service endpoint (leave blank for AWS; set for S3-compatible providers). |
| `RCCONF_amazon_s3_endpoint_url` | URL | `` | Full URL | Alternative endpoint URL for S3-compatible object storage. |

### Azure Blob Storage

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_azure_app_name` | string | `` | Azure storage account name | Azure Blob Storage account name. |
| `RCCONF_azure_app_secret` | secret | `` | Storage account key | Azure Blob Storage account key. |
| `RCCONF_azure_container` | string | `` | Container name | Azure Blob Storage container where files are stored. |
| `RCCONF_azure_quickstart` | boolean | `0` | `0`, `1` | Enable the Azure quickstart wizard in the Control Center. |

### Google Cloud Storage

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_google_cloud_storage_api_bucket_name` | string | `` | Bucket name | Google Cloud Storage bucket for REDCap file storage. |
| `RCCONF_google_cloud_storage_api_project_id` | string | `` | GCP project ID | Google Cloud Platform project ID. |
| `RCCONF_google_cloud_storage_api_service_account` | JSON | `` | JSON service account key object | GCP service account credentials JSON string. |
| `RCCONF_google_cloud_storage_api_use_project_subfolder` | boolean | `1` | `0`, `1` | Store files in a per-project subfolder within the GCS bucket. |
| `RCCONF_google_cloud_storage_edocs_bucket` | string | `` | Bucket name | Separate GCS bucket for edoc files (if different from the main bucket). |
| `RCCONF_google_cloud_storage_temp_bucket` | string | `` | Bucket name | GCS bucket for temporary files. |

### File Upload Vault (WebDAV/SFTP)

External filesystem vault for files uploaded via "File Upload" field types.

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_file_upload_vault_filesystem_type` | enum | `` | `webdav`, `sftp` | Protocol for the external file vault. |
| `RCCONF_file_upload_vault_filesystem_host` | URL | `` | Full URL or hostname | Host address of the external file vault server. |
| `RCCONF_file_upload_vault_filesystem_username` | string | `` | Username | Authentication username for the vault. |
| `RCCONF_file_upload_vault_filesystem_password` | secret | `` | Password | Authentication password for the vault. |
| `RCCONF_file_upload_vault_filesystem_path` | path | `` | Remote path | Base path on the remote server where files are stored. |
| `RCCONF_file_upload_vault_filesystem_private_key_path` | path | `` | Server path | Path to the SSH private key file for SFTP key-based authentication. |
| `RCCONF_file_upload_vault_filesystem_authtype` | enum | `AUTH_DIGEST` | `AUTH_DIGEST`, `AUTH_BASIC` | HTTP authentication type used for WebDAV connections. |
| `RCCONF_file_upload_vault_filesystem_container` | string | `` | Container/bucket name | Container name for cloud-backed WebDAV. |

### PDF eConsent Vault (WebDAV/SFTP)

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_pdf_econsent_filesystem_type` | enum | `` | `webdav`, `sftp` | Protocol for the PDF eConsent external vault. |
| `RCCONF_pdf_econsent_filesystem_host` | URL | `` | Full URL or hostname | Host address of the PDF eConsent vault. |
| `RCCONF_pdf_econsent_filesystem_username` | string | `` | Username | Authentication username for the PDF eConsent vault. |
| `RCCONF_pdf_econsent_filesystem_password` | secret | `` | Password | Authentication password for the PDF eConsent vault. |
| `RCCONF_pdf_econsent_filesystem_path` | path | `` | Remote path | Base path for PDF eConsent files on the remote server. |
| `RCCONF_pdf_econsent_filesystem_private_key_path` | path | `` | Server path | SSH private key path for SFTP access to the PDF eConsent vault. |
| `RCCONF_pdf_econsent_filesystem_authtype` | enum | `AUTH_DIGEST` | `AUTH_DIGEST`, `AUTH_BASIC` | HTTP authentication type for WebDAV access. |
| `RCCONF_pdf_econsent_filesystem_container` | string | `` | Container/bucket name | Container name for the PDF eConsent cloud vault. |

### Record Locking PDF Vault (WebDAV/SFTP)

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_record_locking_pdf_vault_filesystem_type` | enum | `` | `webdav`, `sftp` | Protocol for the record-locking PDF vault. |
| `RCCONF_record_locking_pdf_vault_filesystem_host` | URL | `` | Full URL or hostname | Host address of the record-locking PDF vault. |
| `RCCONF_record_locking_pdf_vault_filesystem_username` | string | `` | Username | Authentication username for the record-locking PDF vault. |
| `RCCONF_record_locking_pdf_vault_filesystem_password` | secret | `` | Password | Authentication password for the record-locking PDF vault. |
| `RCCONF_record_locking_pdf_vault_filesystem_path` | path | `` | Remote path | Base path for record-locking PDF files on the remote server. |
| `RCCONF_record_locking_pdf_vault_filesystem_private_key_path` | path | `` | Server path | SSH private key path for SFTP access to the record-locking PDF vault. |
| `RCCONF_record_locking_pdf_vault_filesystem_authtype` | enum | `AUTH_DIGEST` | `AUTH_DIGEST`, `AUTH_BASIC` | HTTP authentication type for WebDAV access. |
| `RCCONF_record_locking_pdf_vault_filesystem_container` | string | `` | Container/bucket name | Container name for the record-locking PDF cloud vault. |

---

## PDF eConsent System

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_pdf_econsent_system_enabled` | boolean | `1` | `0`, `1` | Enable the PDF eConsent system globally. |
| `RCCONF_pdf_econsent_system_ip` | boolean | `1` | `0`, `1` | Log the participant's IP address in the PDF eConsent record. |
| `RCCONF_pdf_econsent_system_custom_text` | string | `` | HTML | Custom text added to PDF eConsent documents (e.g. institutional disclosure). |
| `RCCONF_display_inline_pdf_in_pdf` | boolean | `1` | `0`, `1` | Render inline/embedded PDFs within eConsent PDF files. Requires ImageMagick (`convert`) on the server. |

---

## Modules & Features

### REDCap API

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_api_enabled` | boolean | `1` | `0`, `1` | Enable the REDCap API globally, allowing programmatic data access by external applications. |
| `RCCONF_api_token_request_type` | enum | `admin_approve` | `admin_approve`, `auto_approve` | Whether API token requests from users require administrator approval or are granted automatically. |

### REDCap Mobile App & MyCap

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_mobile_app_enabled` | boolean | `1` | `0`, `1` | Enable the REDCap Mobile App for offline data collection. |
| `RCCONF_mycap_enabled_global` | boolean | `1` | `0`, `1` | Enable MyCap (participant-facing mobile app) globally. |
| `RCCONF_mycap_enable_type` | enum | `admin` | `admin`, `auto` | Who may enable MyCap per-project: `admin` = only admins; `auto` = any project manager. |

### Surveys & Data Collection

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_enable_survey_text_to_speech` | boolean | `1` | `0`, `1` | Enable the text-to-speech accessibility service on surveys. |
| `RCCONF_google_recaptcha_site_key` | string | `` | reCAPTCHA v2 site key | Google reCAPTCHA v2 site key; protects public surveys from bot submissions. |
| `RCCONF_google_recaptcha_secret_key` | secret | `` | reCAPTCHA v2 secret key | Google reCAPTCHA v2 secret key. |

### Data Visualization

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_enable_plotting` | enum | `2` | `0` = disabled, `2` = enabled | Enable graphical charts and statistical summaries in project data views. |
| `RCCONF_enable_plotting_survey_results` | boolean | `1` | `0`, `1` | Allow survey respondents to view aggregate survey results (charts) after completing a survey. |

### Rich Text Editor Features

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_rich_text_image_embed_enabled` | boolean | `1` | `0`, `1` | Allow images to be embedded directly in the rich text editor. |
| `RCCONF_rich_text_attachment_embed_enabled` | boolean | `1` | `0`, `1` | Allow file attachments to be embedded in the rich text editor. |
| `RCCONF_enable_field_attachment_video_url` | boolean | `1` | `0`, `1` | Allow YouTube/Vimeo video URLs to be embedded in Descriptive fields. |

### External Modules

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_external_modules_allow_activation_user_request` | boolean | `1` | `0`, `1` | Show a "Request Activation" button in the External Module list, allowing users to request that a module be enabled for their project. |
| `RCCONF_external_module_alt_paths` | path | `` | Pipe-separated paths (e.g. `/var/www/redcap/modules2/\|/opt/redcap_modules/`) | Additional directories where REDCap searches for External Modules. |
| `RCCONF_external_modules_project_custom_text` | string | `` | HTML | Custom informational text shown on the project-level External Modules page. |
| `RCCONF_external_modules_updates_available` | string | `` | Internal, auto-managed | Cached JSON list of available External Module updates. Updated automatically by cron. |
| `RCCONF_external_modules_updates_available_last_check` | string | `` | MySQL timestamp, auto-managed | Timestamp of the last External Module update check. Updated automatically by cron. |

### Randomization

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_randomization_global` | boolean | `1` | `0`, `1` | Enable the Randomization module globally. When disabled, it is completely hidden in all projects. |

### REDCap Shared Library

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_shared_library_enabled` | boolean | `1` | `0`, `1` | Allow users to download instrument templates from the REDCap Shared Library. |

### E-Signature

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_esignature_enabled_global` | boolean | `1` | `0`, `1` | Enable the e-signature feature globally. |

### Field Bank

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_field_bank_enabled` | boolean | `1` | `0`, `1` | Enable the Field Bank, allowing users to browse and reuse fields from other projects they have access to. |

### Calendar & Feed

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_calendar_feed_enabled_global` | boolean | `1` | `0`, `1` | Enable iCal calendar feed subscriptions for REDCap project calendars. |

### Data Transfer Services (DTS)

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_dts_enabled_global` | boolean | `0` | `0`, `1` | Enable the REDCap DTS module, which allows data to be pushed from external systems (e.g. EMR) into REDCap with an adjudication workflow. |

### Send-It

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_sendit_enabled` | boolean | `1` | `0`, `1` | Enable the Send-It application for secure file sharing between users. |
| `RCCONF_sendit_upload_max` | integer | `` | MB; blank = PHP server default | Maximum file size (MB) for Send-It uploads. |

### Data Entry

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_data_entry_trigger_enabled` | boolean | `1` | `0`, `1` | Enable the Data Entry Trigger feature — REDCap sends an HTTP POST to a configured URL whenever a record is saved. |
| `RCCONF_database_query_tool_enabled` | boolean | `0` | `0`, `1` | Enable the Database Query Tool in the Control Center, which runs arbitrary SQL queries. Use with caution. |
| `RCCONF_display_project_xml_backup_option` | boolean | `1` | `0`, `1` | Show the "Project XML" backup/export option on the project setup page. |
| `RCCONF_new_form_default_prod_user_access` | boolean | `1` | `0`, `1` | Automatically grant all users access to a newly added instrument in a production project. |

### Ontology / BioPortal

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_enable_ontology_auto_suggest` | boolean | `1` | `0`, `1` | Enable ontology auto-suggest on form and survey fields using the BioPortal API. |
| `RCCONF_bioportal_api_url` | URL | `https://data.bioontology.org/` | Full URL | BioPortal API endpoint. |
| `RCCONF_bioportal_api_token` | secret | `` | BioPortal API token | API token for BioPortal (required for ontology auto-suggest). Register at bioportal.bioontology.org. |
| `RCCONF_bioportal_ontology_list` | string | `` | Comma-separated ontology IDs | Restrict the ontology selector to only the listed ontologies (e.g. `SNOMEDCT,ICD10`). |
| `RCCONF_bioportal_ontology_list_cache_time` | integer | `` | Hours | How long (hours) the ontology list from BioPortal is cached locally. |

### PROMIS / CATs

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_promis_enabled` | boolean | `1` | `0`, `1` | Enable integration with PROMIS/CATs (computerized adaptive testing via the NIH Assessment Center API). |
| `RCCONF_promis_api_base_url` | URL | `https://www.redcap-cats.org/promis_api/` | Full URL | Base URL for the PROMIS/CATs API. |
| `RCCONF_promis_registration_id` | string | `` | Registration ID | Institution registration identifier for the PROMIS/Assessment Center. |
| `RCCONF_promis_token` | secret | `` | API token | PROMIS/Assessment Center API token. |

### MTB

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_mtb_enabled` | boolean | `0` | `0`, `1` | Enable the Multi-Language Management (MTB) module for multi-language survey/form support. |

---

## Twilio SMS/Voice

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_twilio_enabled_global` | boolean | `1` | `0`, `1` | Enable Twilio for SMS/voice survey invitations and alert notifications globally. |
| `RCCONF_twilio_enabled_by_super_users_only` | enum | `0` | `0` = all projects, `1` = super-user projects only, `2` = super-user only + display restricted | Controls which projects may use Twilio. |
| `RCCONF_twilio_display_info_project_setup` | boolean | `0` | `0`, `1` | Show Twilio setup instructions on the Project Setup page. Only effective when `twilio_enabled_by_super_users_only` is `1`. |

---

## Mosio SMS

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_mosio_enabled_global` | boolean | `1` | `0`, `1` | Enable Mosio for SMS survey delivery globally. |
| `RCCONF_mosio_enabled_by_super_users_only` | enum | `0` | `0` = all projects, `1` = super-user only, `2` = super-user only + display restricted | Controls which projects may use Mosio. |
| `RCCONF_mosio_display_info_project_setup` | boolean | `0` | `0`, `1` | Show Mosio setup information on the Project Setup page. Requires `mosio_enabled_by_super_users_only=1`. |

---

## FHIR / EHR Integration (DDP)

### Connection Settings

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_fhir_ddp_enabled` | boolean | `0` | `0`, `1` | Enable the FHIR-based Dynamic Data Pull (DDP) module. |
| `RCCONF_fhir_endpoint_base_url` | URL | `` | Full URL | Base URL of the FHIR R4 API endpoint. |
| `RCCONF_fhir_endpoint_authorize_url` | URL | `` | Full URL | OAuth2 authorization endpoint for FHIR authentication. |
| `RCCONF_fhir_endpoint_token_url` | URL | `` | Full URL | OAuth2 token exchange endpoint for FHIR authentication. |
| `RCCONF_fhir_client_id` | string | `` | OAuth2 client ID | Client ID registered with the FHIR authorization server. |
| `RCCONF_fhir_client_secret` | secret | `` | OAuth2 client secret | Client secret for the FHIR OAuth2 registration. |
| `RCCONF_fhir_identity_provider` | string | `` | Identity provider name or URL | Name/URL of the FHIR identity provider. |
| `RCCONF_fhir_ehr_mrn_identifier` | string | `` | FHIR identifier system URL | FHIR identifier system used for the patient MRN (e.g. `urn:oid:2.16.840.1.113883.4.1`). |
| `RCCONF_fhir_standalone_authentication_flow` | enum | `standalone_launch` | `standalone_launch`, `ehr_launch` | SMART on FHIR launch context. `standalone_launch` = patient-facing; `ehr_launch` = provider-facing from EHR. |

### Display & Access

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_fhir_source_system_custom_name` | string | `EHR` | Free text | Custom display name for the FHIR source system in the UI (e.g. `Epic`, `Cerner`). |
| `RCCONF_fhir_custom_text` | string | `` | HTML | Custom text shown on the FHIR/DDP section of the Project Setup page. |
| `RCCONF_fhir_display_info_project_setup` | boolean | `1` | `0`, `1` | Show FHIR integration setup information on the Project Setup page. |
| `RCCONF_fhir_user_rights_super_users_only` | boolean | `1` | `0`, `1` | Restrict FHIR/DDP user-rights management to super users only. |
| `RCCONF_fhir_url_user_access` | URL | `` | Full URL | URL where users can request access to the EHR/FHIR system. |
| `RCCONF_fhir_include_email_address` | boolean | `0` | `0`, `1` | Include the user's email address in FHIR patient-matching requests. |

### Data Fetch Behavior

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_fhir_data_fetch_interval` | integer | `24` | Hours | How often (hours) REDCap automatically polls the FHIR endpoint for updated patient data. |
| `RCCONF_fhir_stop_fetch_inactivity_days` | integer | `7` | Days | Stop auto-fetching FHIR data for a project after this many days without any user activity. |
| `RCCONF_fhir_convert_timestamp_from_gmt` | boolean | `0` | `0`, `1` | Convert timestamps returned by the FHIR endpoint from UTC/GMT to the server's local timezone. |
| `RCCONF_fhir_data_mart_create_project` | boolean | `0` | `0`, `1` | Allow FHIR data to be automatically pulled into a "data mart" project. |
| `RCCONF_fhir_cdp_allow_auto_adjudication` | boolean | `1` | `0`, `1` | Allow auto-adjudication of incoming FHIR data (bypasses the manual review step for new data). |

### Custom Mapping & Auth

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_fhir_custom_mapping_file_id` | integer | `` | edoc file ID | ID of a custom FHIR-to-REDCap field mapping file stored in the database. |
| `RCCONF_fhir_custom_auth_params` | string | `` | `key=value` pairs | Additional parameters appended to FHIR authorization requests. |

### Break-the-Glass (Emergency Access)

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_fhir_break_the_glass_enabled` | boolean | `` | `0`, `1` | Enable the "Break the Glass" emergency access override for FHIR data retrieval. |
| `RCCONF_fhir_break_the_glass_ehr_usertype` | string | `SystemLogin` | EHR user type value | EHR user type string that identifies a break-the-glass access attempt. |
| `RCCONF_fhir_break_the_glass_token_usertype` | string | `EMP` | Token user type | User type identifier used when exchanging tokens for break-the-glass access. |
| `RCCONF_fhir_break_the_glass_token_username` | string | `` | Username | Username for token-based break-the-glass authentication. |
| `RCCONF_fhir_break_the_glass_token_password` | secret | `` | Password | Password for token-based break-the-glass authentication. |
| `RCCONF_fhir_break_the_glass_username_token_base_url` | URL | `` | Full URL | Base URL for the token exchange endpoint in the break-the-glass flow. |
| `RCCONF_fhir_break_the_glass_department_type` | string | `` | Department identifier | Department type used to identify authorized break-the-glass users. |
| `RCCONF_fhir_break_the_glass_patient_type` | string | `` | Patient type identifier | Patient type used when filtering break-the-glass access. |

---

## Real-time Webservice (Legacy DDP)

The older, non-FHIR Dynamic Data Pull integration. For new deployments, prefer the FHIR-based DDP above.

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_realtime_webservice_global_enabled` | boolean | `0` | `0`, `1` | Enable the legacy real-time webservice (DDP) globally. |
| `RCCONF_realtime_webservice_url_metadata` | URL | `` | Full URL | Webservice endpoint for fetching field/metadata definitions. |
| `RCCONF_realtime_webservice_url_data` | URL | `` | Full URL | Webservice endpoint for fetching patient data. |
| `RCCONF_realtime_webservice_url_user_access` | URL | `` | Full URL | Webservice endpoint for checking user access to patient records. |
| `RCCONF_realtime_webservice_data_fetch_interval` | integer | `24` | Hours | How often (hours) REDCap polls the webservice for new/updated data. |
| `RCCONF_realtime_webservice_stop_fetch_inactivity_days` | integer | `7` | Days | Stop auto-fetching data for a project after this many days without user activity. |
| `RCCONF_realtime_webservice_convert_timestamp_from_gmt` | boolean | `0` | `0`, `1` | Convert timestamps from the webservice from UTC/GMT to local time. |
| `RCCONF_realtime_webservice_source_system_custom_name` | string | `` | Free text | Custom display name for the DDP source system shown in the UI. |
| `RCCONF_realtime_webservice_custom_text` | string | `` | HTML | Custom information text shown on the project DDP setup section. |
| `RCCONF_realtime_webservice_display_info_project_setup` | boolean | `1` | `0`, `1` | Show DDP setup information on the Project Setup page. |
| `RCCONF_realtime_webservice_user_rights_super_users_only` | boolean | `1` | `0`, `1` | Restrict DDP user-rights management to super users only. |

---

## Publication Matching

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_pub_matching_enabled` | boolean | `0` | `0`, `1` | Enable the Publication Matching module, which helps match PubMed publications to REDCap projects. |
| `RCCONF_pub_matching_institution` | string | `Vanderbilt\nMeharry` | Newline-separated institution names | Institution names used as search terms for PubMed publication matching. |
| `RCCONF_pub_matching_emails` | boolean | `0` | `0`, `1` | Send email notifications to project owners about newly matched publications. |
| `RCCONF_pub_matching_email_days` | integer | `7` | Days | How often (days) publication match notification emails are sent. |
| `RCCONF_pub_matching_email_limit` | integer | `3` | Count | Maximum number of publication match emails sent per interval. |
| `RCCONF_pub_matching_email_subject` | string | `` | Free text | Subject line for publication match notification emails. |
| `RCCONF_pub_matching_email_text` | string | `` | HTML | Body text for publication match notification emails. |

---

## REDCap Updates & Versioning

| Variable | Type | Default | Allowed Values / Notes | Purpose |
|---|---|---|---|---|
| `RCCONF_redcap_updates_user` | string | `` | REDCap Community username | Username used to check for REDCap software updates from the community portal. |
| `RCCONF_redcap_updates_password` | secret | `` | Password | Password for the REDCap Community portal (used for update checks). |
| `RCCONF_redcap_updates_password_encrypted` | boolean | `1` | `0`, `1` | Whether the stored `redcap_updates_password` is encrypted at rest. Managed automatically. |
| `RCCONF_redcap_updates_available` | string | `` | Internal, auto-managed | Cached update availability data. Updated automatically by cron. |
| `RCCONF_redcap_updates_available_last_check` | string | `` | MySQL timestamp, auto-managed | Timestamp of the last update availability check. Updated automatically by cron. |
| `RCCONF_aws_quickstart` | boolean | `0` | `0`, `1` | Enable the AWS quickstart wizard in the Control Center (simplified AWS configuration). |
