# Developer Guide

This document covers the project structure, where to implement changes, and how to run the integration tests.

> [!WARNING]
> **The integration tests download REDCap from the community portal.** Each full test run performs exactly 2 downloads (version A and version B). Do not run the full suite repeatedly in a short period — automated mass downloads may conflict with the REDCap Consortium's usage policies and could get your account throttled or blocked. Run targeted tests during development; reserve full runs for pre-release verification.

---

## Repository layout

```
redcap-docker/
├── Dockerfile                        # Container image definition
├── container_assets/
│   ├── config/
│   │   ├── apache2/                  # Apache virtual-host and conf-enabled snippets
│   │   ├── php/                      # php.ini and conf.d overrides
│   │   └── redcap/                   # database.php template, default install.sql
│   └── scripts/
│       ├── container_start.sh        # Container entry point (runs startup scripts, starts Apache)
│       ├── redcap_upgrader.php        # `redcap-upgrade` CLI implementation
│       ├── redcap_upgrade.sh          # Thin wrapper that calls redcap_upgrader.php
│       ├── redcap_installer.php       # `redcap-install` CLI implementation
│       ├── redcap_install.sh          # Thin wrapper that calls redcap_installer.php
│       └── startup-scripts/           # Scripts run once at every container boot (in order)
│           ├── 030_*.sh / 040_*.sh …  # Bash setup scripts (uid fixup, permissions, …)
│           ├── 080-redcap-version-reconciler.php  # Downloads/upgrades REDCap if REDCAP_VERSION is set
│           ├── 100-run-install-script.php          # Runs the REDCap install SQL on first boot
│           ├── 102-run-sql-scripts.php             # Runs one-off SQL scripts from the run-once dir
│           ├── 110-set_redcap_config.php           # Writes RCCONF_* env vars → redcap_config table
│           ├── 120-suspend_default-admin.php       # Optionally suspends the default admin
│           ├── 130_user_provisioning.php           # Creates/updates users from USER_PROV / YAML files
│           └── php_helpers/                        # Shared PHP libraries used by startup scripts
│               ├── db.php
│               ├── redcap_community_downloader.php  # Downloads from community portal
│               ├── redcap_info.php                  # Version detection helpers
│               └── …
├── tests/                            # Integration test suite (see tests/README.md)
│   ├── conftest.py                   # Fixtures + RedcapStack helper class
│   ├── test_fresh_install.py
│   ├── test_reconciler.py
│   ├── test_upgrade.py
│   ├── test_config.py
│   ├── Dockerfile                    # Test runner image (python + docker CLI)
│   ├── docker-compose.test.yml
│   ├── requirements.txt
│   └── .env.example                  # Copy to .env and fill in credentials
├── run-tests.sh                      # Primary test entry point (builds image, runs suite)
├── examples/                         # Annotated docker-compose examples for end users
├── dev_compose/                      # Local development compose stack
├── config_vars_list.md               # Full environment variable reference
├── REDCAP_UPGRADE.md                 # Upgrade feature documentation
└── USER_PROV.md                      # User provisioning documentation
```

---

## Where to implement what

### New startup behaviour

Add a numbered PHP or Bash file to `container_assets/scripts/startup-scripts/`. Files are executed in lexicographic order by `container_start.sh`. PHP scripts run as root; the database is available from step 100 onward.

- **060–079** — system / Apache / PHP setup (Bash)
- **080** — version reconciler (download / upgrade)
- **100–139** — REDCap DB and application setup (PHP)

### New environment variable

1. Add a default with `ENV MY_VAR=default` in `Dockerfile` (near the other ENV declarations).
2. Document it in `config_vars_list.md`.
3. Read it inside the relevant startup script or helper.

### New `redcap-upgrade` flag or behaviour

Edit `container_assets/scripts/redcap_upgrader.php`. The argument parser is in `parse_args()`, non-interactive upgrade logic is in `run_upgrade()`, and the interactive wizard is in `run_wizard()`.

### New end-user example

Add a subdirectory under `examples/` with a `docker-compose.yaml` and a short `README.md`.

---

## Running the integration tests

> Full details are in [tests/README.md](tests/README.md).

### Prerequisites

- Docker Engine 24+ or Docker Desktop
- A valid REDCap community portal account
- No Python installation required — the runner is containerised

### Setup (once)

```bash
cp tests/.env.example tests/.env
# Edit tests/.env and fill in REDCAP_COMMUNITY_USER, REDCAP_COMMUNITY_PASSWORD,
# REDCAP_TEST_VERSION, and REDCAP_TEST_UPGRADE_VERSION
```

### Run the full suite

```bash
./run-tests.sh
```

`run-tests.sh` builds the local `dzdde/redcap-docker:test-local` image from the working tree before running, so you always test your current code.

### Live container log output

```bash
./run-tests.sh -v
```

### Run a single test or file

```bash
./run-tests.sh tests/test_reconciler.py
./run-tests.sh tests/test_reconciler.py::test_reconciler_already_at_version
```

### How many downloads per run

| Fixture / test | Downloads |
|---|---|
| `installed_a_snapshot` (session) | 1 × version A |
| `b_zip_volume` (session) | 1 × version B |
| All other tests | 0 — use the session snapshots |

**Total: 2 downloads per full run.** The session fixtures are shared across all tests in the suite, so individual tests never hit the portal again.

### Cleaning up after an interrupted run

If tests are killed mid-run (`Ctrl-C`), stale Docker resources may remain:

```bash
docker ps -a | grep rctest          # list leftover containers
docker network ls | grep rctest     # list leftover networks
docker volume  ls | grep rctest     # list leftover volumes

docker network prune
docker volume prune
```

---

## Testing philosophy

- **No mocking.** Tests spin up real MySQL and REDCap containers via the host Docker socket (Docker-out-of-Docker).
- **No CI.** Community portal credentials are private; automated runs would risk ToS violations. Run manually before each release.
- **Download once.** The `installed_a_snapshot` session fixture does the version-A install once. The `b_zip_volume` fixture downloads version B once. All 8 tests reuse these artefacts.
- **Fast per-test setup.** Snapshot-using tests clone a Docker volume + restore a DB dump (~30 s) instead of re-downloading and re-installing (~5 min).
