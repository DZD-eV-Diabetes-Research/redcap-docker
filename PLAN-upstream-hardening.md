# Plan: upstream / base image hardening

Drafted 2026-11-19 from the upstream security review (session that also handled CVE-2026-90817).
Committed on the feature branch only as a hand-off between machines: **delete this file before merging**.

## Status (end of 2026-11-19)

All items (3, 4, 4-stretch, 2, 5, 1) are implemented on `chore/upstream-hardening`, one commit each, plus
`7efbdbd` (upgrader: `mysqldump | gzip` now under `bash -o pipefail`, a failed dump no longer reports success).
`./run-tests.sh`: 38/38 passed (upgrade 17.1.4 → 17.4.4 against `mysql:lts`).
Decisions taken: weekly rebuild pushes `latest` + `X.Y.Z` (overwrite) + `X.Y.Z-YYYYMMDD`; `-dev` packages purged.

Deviations from the plan below:
- PECL extensions are built with `tar` + `phpize` + `make PHP= PHP_EXECUTABLE=` instead of `pecl install <tgz>`:
  `pecl install` changed mtimes, so `make` ran `gen_stub.php`, which downloads PHP-Parser without a checksum.
- No `magick -list policy` build check (the image has no IM CLI). Instead
  `container_assets/scripts/imagemagick_policy_check.php` runs at build time and in `test_imagemagick_policy`.
- MariaDB 11.8 client did break against MySQL TLS: the upgrader writes `skip-ssl` when `DB_SSL_CA_FILE_PATH`
  is unset, otherwise `ssl`/`ssl-ca`/... + `ssl-verify-server-cert` from `DB_SSL_VERIFY_SERVER`.
- Optional reusable workflow (item 1.3) skipped: tag logic differs per workflow, not simpler.

Trivy (OS packages, CRITICAL/HIGH fixable): bookworm 5 C / 155 H → trixie 4 C / 9 H.

### Open / next steps
1. **Decision:** add `apt-get upgrade` to the first `RUN`? Without it the weekly rebuild only gets Debian fixes
   when Docker Hub rebuilds `php:8.4.27-apache-trixie`; that stops once PHP 8.4.28 ships. The 4 remaining
   fixable CRITICALs (apache2 2.4.69 → 2.4.71, linux-libc-dev) come from the base image. Recommended: yes.
2. Manual browser test: record PDF export with an inline PDF attachment, and an eConsent PDF with a PDF
   consent form (REDCap uses Imagick only in `PDF::convertPdfToImages()`; CLI test already passed).
3. Repo settings: code scanning enabled; Docker Hub account may overwrite `latest` / `X.Y.Z`; org policy
   allows `security-events: write`.
4. Delete this file, push, open the PR. Release notes: Debian 13 base; `vim`, `ssh`, `wget`, `git` removed
   (edit files via `docker cp` or a bind mount); ImageMagick 7 with stricter policy; extra IM coders gone.
5. Separate issue (pre-existing): `redcap-install` dies with "Cannot redeclare function redcap_is_installed()"
   (`redcap_installer.php:133` and `php_helpers/redcap_info.php:56`).
6. On a new machine, `tests/.env` (git-ignored) needs `REDCAP_TEST_UPGRADE_VERSION=17.4.4` to run the full suite.

Suggested branch: `chore/upstream-hardening`. Release as a minor version (base OS change + removed tools).

Order of work: **3 → 4 → 2 → 5 → 1** (Dockerfile changes first, with build-time checks; CI last, so
the weekly rebuild starts from the hardened Dockerfile).

---

## 3. Pin PECL extensions (Imagick, yaml)

Facts (checked 2026-11-19):
- Imagick is built from the tip of `master` of github.com/Imagick/imagick, not a release. The running
  image reports `imagick module version => @PACKAGE_VERSION@`, so we can't even tell which commit we ship.
- PECL stable: **imagick 3.8.1**, **yaml 2.3.0**. 3.8.x supports PHP 8.4 and ImageMagick 7.
- `yaml` is enabled via `extension=yaml.so` in `container_assets/config/php/php.ini`; Imagick via
  `docker-php-ext-enable imagick`.

Steps:
1. Add `ARG PECL_IMAGICK_VERSION=3.8.1` and `ARG PECL_YAML_VERSION=2.3.0` near the top of the Dockerfile.
2. Replace the whole "Install imagick from source" `RUN` block with
   `pecl install imagick-${PECL_IMAGICK_VERSION} yaml-${PECL_YAML_VERSION} && docker-php-ext-enable imagick`.
   - Stronger option: download the `.tgz` files from pecl.php.net, check them against a pinned
     `sha256sum` in the Dockerfile, then `pecl install /tmp/imagick-3.8.1.tgz`. `pecl` itself does not
     verify signatures. Recommended.
3. Add build-time checks that fail the build:
   `php -m | grep -qx imagick && php -m | grep -qx yaml` and
   `php --ri imagick | grep -q "module version => ${PECL_IMAGICK_VERSION}"`.

## 4. Remove unused packages

Facts: no container script uses `ssh`, `vim`, `wget` or `git` (grep over `container_assets/scripts`).
`curl` (healthcheck) comes from the php base image. `ssh` is the metapackage that pulls in **openssh-server**
as well as the client. `git` is installed in the first `RUN` and purged in a later layer, so it still
sits in a lower layer. The only doc mentions are `git clone` of this repo on the host (fine).

Steps:
1. Drop `ssh`, `vim`, `wget`, `git` from the `apt-get install` list (item 3 already removes the second git install).
   ⚠️ `tests/test_security.py::_fetch_response_headers` uses **`wget`** inside the container. Switch it to
   `curl -sI http://localhost/` (the CVE guard tests already use curl) before removing wget.
2. Check REDCap's SFTP vault features (`RCCONF_*_filesystem_private_key_path`) don't shell out to
   `ssh`/`sftp`: grep the downloaded REDCap source for `exec(`/`proc_open(` + `ssh`/`sftp`. Expectation:
   REDCap uses phpseclib (pure PHP), so nothing to keep.
3. Release note: "`vim` is no longer in the image; use `docker cp` or mount the file to edit it."
4. Stretch (optional, separate commit): purge `-dev` build packages (`libmagickwand-dev`, `libpng-dev`,
   `libzip-dev`, `libyaml-dev`) after compiling extensions, keeping the runtime libs via `apt-mark manual`.
   This cuts image size and CVE noise a lot, but test it carefully.

## 2. Switch base image to Debian 13 (trixie)

Facts (checked 2026-11-19 in `php:8.4.27-apache-trixie`):
- PHP 8.4.27 is the current stable patch (8.4.28 is RC1). `8.4.27-apache` (no suffix) is already trixie.
- `libmagickwand-dev` → **ImageMagick 7.1.1.43** (`libmagickwand-7.q16-dev`). Policy file moves to
  **`/etc/ImageMagick-7/policy.xml`**.
- ⚠️ The Debian IM7 `policy.xml` has **no `pattern="PDF"` rule at all**. Our current
  `sed .../ImageMagick-6/policy.xml` would (a) fail to find the file / silently match nothing, and
  (b) leave PDF **read+write** allowed, which is looser than today's IM6 setup (`rights="read"` for PDF).
  The "deny all coders" lines in that file are inside a comment (example policy), not active.
- Other packages: ghostscript 10.05.1, msmtp-mta 1.8.28, busybox-static 1.37.0, libyaml-dev 0.2.5,
  `default-mysql-client` → MariaDB client.

Steps:
1. `FROM php:8.4.27-apache-trixie` (keep the pinned patch and the CVE comment).
2. Replace the `sed` with an explicit, fail-loud policy step, e.g.:
   ```
   RUN POLICY=/etc/ImageMagick-7/policy.xml && test -f "$POLICY" && \
       sed -i 's#</policymap>#  <policy domain="coder" rights="read" pattern="PDF" />\n  <policy domain="coder" rights="none" pattern="{PS,PS2,PS3,EPS,EPI,EPSI,EPSF,XPS}" />\n</policymap>#' "$POLICY" && \
       magick -list policy | grep -q 'pattern: PDF'
   ```
   Check the order/semantics of IM7 policies (later rules win) and confirm with `magick -list policy`.
3. Find out which REDCap feature actually uses Imagick + Ghostscript (grep REDCap source for
   `new Imagick` / `readImage` / `gs `), and test that feature by hand on the new image (e.g. PDF
   preview / inline PDF in a survey / eConsent).
4. ⚠️ **MariaDB client vs MySQL server**: newer MariaDB clients (11.4+) turn TLS on and verify the
   server cert by default. The upgrader's `mysqldump`/`mysql` calls (`redcap_upgrader.php`, defaults
   file written around L410/L460) may then fail against a MySQL 8.4 server with a self-signed cert or
   no TLS. Test backup + rollback; if it breaks, add `ssl-verify-server-cert=0` (or `skip-ssl` when
   `DB_SSL_*` is unset) to the generated defaults file, matching `DB_SSL_VERIFY_SERVER`.
5. Grep the repo for other bookworm/IM6 assumptions (`ImageMagick-6`, `bookworm`) in docs and tests.

## 5. Ghostscript (keep, contain)

REDCap needs it for PDF handling (SECURITY.md L183), so it stays. It has a long RCE history. It's covered by:
- the explicit IM7 policy from item 2 (PDF read-only, PostScript family denied);
- the weekly rebuild + scan from item 1 (Debian security fixes for ghostscript land within a week).
No separate code change, beyond a short SECURITY.md note on why ghostscript is present and how it's restricted.

## 1. Scheduled weekly rebuild + push in CI

Problem: Apache, ghostscript, ImageMagick, OpenSSL etc. come from Debian packages. The image is only built
on a GitHub release, so the published `latest` goes stale between releases.

Steps:
1. New workflow `.github/workflows/rebuild-weekly.yaml`:
   - `on: schedule: - cron: "0 4 * * 1"` + `workflow_dispatch`.
   - Resolve the newest **non-prerelease** release tag (`gh release list --exclude-pre-releases -L 1`
     or the API) and `actions/checkout` **that tag**, never `main` (main may hold unreleased changes).
   - `docker/build-push-action` with `pull: true` and `no-cache: true` so apt layers really refresh.
   - Tags: the event is `schedule`, so `metadata-action`'s default release tags won't apply. Set them
     explicitly: `latest`, `X.Y.Z` (from the resolved tag) and a dated `X.Y.Z-YYYYMMDD` so people
     can pin an exact rebuild. **Decision needed:** is overwriting `X.Y.Z` acceptable (as the official
     Docker images do), or only `latest` + dated tag?
2. Add an image scan (Trivy, `aquasecurity/trivy-action` pinned to a commit hash) to this workflow and to
   the release workflow: upload SARIF to GitHub code scanning; start report-only, later fail on
   fixable CRITICAL.
3. Optional: factor the shared build steps into a reusable workflow (`workflow_call`) used by release,
   beta and weekly.
4. Note: GitHub disables scheduled workflows after 60 days without repo activity. A `workflow_dispatch`
   + an occasional commit, or a keepalive, avoids silent stops.

---

## Verification (before merging)

- `tests/.env` currently has **no `REDCAP_TEST_UPGRADE_VERSION`**, so the suite skips everything.
  Add one (must be > `REDCAP_TEST_VERSION`=17.1.4; prefer CVE-2026-90817-fixed versions, e.g.
  17.3.10 → 17.4.4 if the licence allows), then run `./run-tests.sh`.
- Build checks from items 2 and 3 pass (`docker build` fails otherwise).
- `php --ri imagick` shows 3.8.1 + ImageMagick 7.1.1; `magick -list policy` shows the PDF/PS rules.
- `dpkg -l | grep -E 'openssh|vim|wget|^ii  git '` is empty.
- Upgrade path tested: backup + rollback with the MariaDB client against `mysql:lts`.
- Manual: the REDCap PDF feature from item 2.3 works.
- Trivy report before/after attached to the PR.

## Out of scope here (from the same review)

- Item 6: pin GitHub Actions to commit hashes, add `.github/dependabot.yml` (docker, github-actions, pip),
  Docker Hub access token instead of password, SBOM/provenance. Good follow-up PR.
- Item 7: pin test image bases (`docker:cli`, `python:3.12-slim`).
- CVE-2026-90817 mitigation: separate work, started in the 2026-11-19 session.
