# External module provisioning

Install (and optionally enable) REDCap external modules declaratively, via
environment variables and/or a YAML file. Module files are fetched and placed
into REDCap's `/modules` directory on container start.

**What this example shows**

- `ENABLE_MODULE_PROV: "true"` — activate module provisioning
- `MODULE_PROV_DEFAULT_ENABLE: "false"` — global policy: install only (an admin
  enables modules from the control center). Set to `"true"` to auto-enable.
- `MODULE_PROV_1` — an inline JSON module definition via an env var
- `modules.yaml` — a mounted file with a full module list and multiple sources

Modules are defined in [modules.yaml](modules.yaml). Additional modules can be
added inline via numbered `MODULE_PROV_N` env vars (see the compose file).

For the full module data schema and every source type (`github`, `url`,
`local`, `repo`), see [MODULE_PROV.md](../../MODULE_PROV.md).

## Quick start

```bash
git clone https://github.com/DZD-eV-Diabetes-Research/redcap-docker.git
cd redcap-docker/examples/instance_with_modules
```

Place your REDCap source in `./data/redcap/`, then:

```bash
docker compose up -d
docker compose logs -f redcap
```

Watch the logs for `[MODULE PROVISIONING]` lines. Provisioned modules then
appear under **Control Center → External Modules**.

## Notes

- Pin explicit versions for reproducible deployments — omitting `version` for a
  `github` source pulls in whatever the upstream maintainer last released.
- Provisioning only executes once REDCap is installed; on a fresh auto-install
  it runs on the same boot, after installation.
- Provisioning a module runs third-party PHP in your webroot — only provision
  modules you trust. See [SECURITY.md](../../SECURITY.md).
