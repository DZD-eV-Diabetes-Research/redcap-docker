# Custom PHP ini files

Mount additional PHP ini files into the container. Both the Apache process and the cron process pick them up from the same location, so your overrides apply consistently everywhere.

**What this example shows**

- Mounting a custom ini at `/config/php/custom_inis/` — add any file with a `.ini` extension
- `REDCAP_DOCKER_SCRIPTS_DEBUG: "true"` — prints loaded PHP ini files and other startup details to the log

## Quick start

```bash
git clone https://github.com/DZD-eV-Diabetes-Research/redcap-docker.git
cd redcap-docker/examples/test-issue2_parse_extra_ini
```

Place your REDCap source in `./data/redcap/`, then:

```bash
docker compose up -d
docker compose logs redcap | grep -i ini
```

You should see `customphp.ini` in the list of loaded ini files (web container and cron container):

```
[DEBUG REDCAP DOCKER] PHP_INI_SCAN_DIR: :/config/php/custom_inis
...
Additional .ini files parsed: ... /config/php/custom_inis/customphp.ini
```

## Notes

- The `customphp.ini` file in this directory is a working example. Replace its contents with your own PHP settings.
- Multiple ini files are supported; drop any number of `.ini` files into the mounted directory.
- See [SECURITY.md: PHP hardening](../../SECURITY.md#php-hardening) for security-relevant PHP settings already applied by default.

> Originally created as a debug fixture for [issue #2](https://github.com/DZD-eV-Diabetes-Research/redcap-docker/issues/2).
