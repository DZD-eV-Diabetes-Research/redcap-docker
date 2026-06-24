# Integration Tests

Local-only integration tests for `redcap-docker`. They spin up real Docker containers, download from the actual REDCap community portal, and make assertions against a live MySQL database.

> [!WARNING]
> **These tests download REDCap from the community portal.** Each full run performs exactly 2 downloads (one per version). Do not run the full suite repeatedly in a short period — automated downloads may conflict with the REDCap Consortium's usage policies. Run targeted tests during development; use the full suite for pre-release verification only.

---

## Why no CI?

Three reasons, all intentional:

1. **Real credentials required.** Tests download REDCap directly from `community.projectredcap.org`. Those credentials are private and cannot be embedded in a public CI pipeline.

2. **Terms of service.** Repeated automated downloads against the REDCap portal could conflict with the consortium's usage policies. These tests are meant to be run manually by the maintainer, not on every commit.

3. **Single-maintainer scope.** This is an institutional tool maintained by one person. The right tradeoff is: run tests locally before each release, not on every push.

---

## Prerequisites

- Docker Engine 24+ or Docker Desktop
- A valid REDCap community portal account

No Python installation needed — the test runner itself runs inside Docker.
The `run-tests.sh` script **always builds the image from the local working tree** before running — you are always testing your current code, never the published Docker Hub image.

---

## Setup (once)

Copy the example env file and fill in your credentials:

```bash
cp tests/.env.example tests/.env
```

Edit `tests/.env`:

```env
REDCAP_COMMUNITY_USER=your_username
REDCAP_COMMUNITY_PASSWORD=your_password
REDCAP_TEST_VERSION=14.9.4
REDCAP_TEST_UPGRADE_VERSION=14.9.5
```

`tests/.env` is gitignored and must never be committed.

---

## Running the tests

Use `run-tests.sh` from the repo root. It builds the local image first, then runs the suite — no manual image build step needed.

### Run all tests

```bash
./run-tests.sh
```

### Stream live container logs

```bash
./run-tests.sh -v
```

### Run a single file or test

```bash
./run-tests.sh tests/test_reconciler.py
./run-tests.sh tests/test_reconciler.py::test_reconciler_already_at_version
```

### Pass extra pytest flags

Any argument that is not `-v` / `--verbose` is forwarded to pytest:

```bash
./run-tests.sh -k test_config       # keyword filter
./run-tests.sh -x                   # stop on first failure
./run-tests.sh -v -x -k test_config # combine flags
```

---

## How Docker-out-of-Docker works

The test runner runs inside a container but creates sibling containers via the
host's Docker socket (`/var/run/docker.sock`). The networking works as follows:

1. Each test creates a private Docker network (`rctest_<id>`).
2. MySQL and REDCap containers are started on that network.
3. The test runner container **joins** the test network so it can reach MySQL
   directly by container name (no published port needed).
4. After the test, the runner disconnects from the network and everything is
   removed.

If tests are interrupted mid-run (e.g. `Ctrl-C`), stale networks and volumes
may be left behind. Clean them up with:

```bash
docker ps -a   | grep rctest   # leftover containers
docker network ls | grep rctest
docker volume  ls | grep rctest

docker network prune
docker volume prune
```

---

## Download budget

The session-scoped fixtures ensure each artifact is downloaded at most once per run:

| Fixture / test | Downloads |
|---|---|
| `installed_a_snapshot` (session) | 1 × `REDCAP_TEST_VERSION` |
| `b_zip_volume` (session) | 1 × `REDCAP_TEST_UPGRADE_VERSION` |
| All individual tests | 0 — reuse the session fixtures |

---

## Test coverage

| File | Tests | Scenarios |
|---|---|---|
| `test_fresh_install.py` | 1 | Post-install state from session snapshot |
| `test_reconciler.py` | 3 | Already at version, upgrade warning, auto-upgrade |
| `test_upgrade.py` | 2 | `redcap-upgrade --dry-run`, non-interactive upgrade |
| `test_config.py` | 2 | `RCCONF_*` vars applied, user provisioning |
| `test_module_provisioning.py` | 3 | External module provisioning (`local` source): system-enable + system setting applied; install-only policy leaves the module disabled; version update switches an already-enabled module to a newer version |
| `test_security.py` | 11 | Webroot permissions (read-only default, Easy Upgrade mode, temp/ always writable), startup warnings (Easy Upgrade, plaintext password, http:// URL), PHP ini hardening (expose_php, display_errors), HTTP security headers (presence + values, Server header, X-Powered-By), Docker Secrets (`_FILE` loads from file, missing file warns and continues) |

Tests that use the session snapshot (`start_from_snapshot`) clone a Docker volume and restore a DB dump — setup takes ~30 s instead of the ~5 min a full re-install would take.

---

## When to run

Run the full suite before every release tag. The typical workflow:

```bash
# Run the full suite (builds local image automatically)
./run-tests.sh

# If all green, push the release
```
