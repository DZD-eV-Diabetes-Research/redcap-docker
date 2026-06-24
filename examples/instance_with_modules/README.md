# External module provisioning

Install (and optionally enable) REDCap external modules declaratively, via
environment variables and/or a YAML file. Module files are fetched and placed
into REDCap's `/modules` directory on container start.

This example provisions **three real modules** across two source types:

| Module | Source | Reference |
|---|---|---|
| [Instance Table](https://github.com/lsgs/redcap-instance-table) | `github` | `lsgs/redcap-instance-table` @ `1.13.4` |
| [Auto-Notify](https://github.com/IUREDCap/auto-notify-module) | `github` | `IUREDCap/auto-notify-module` @ `1.5.12` |
| Popup Alerts | `repo` | REDCap central repo, `module_id: 2919` |

**What this example shows**

- `ENABLE_MODULE_PROV: "true"` — activate module provisioning
- `MODULE_PROV_DEFAULT_ENABLE: "false"` — global policy: install only (an admin
  enables modules from the control center). Each module here sets `enabled: true`
  to override that and be system-enabled on boot.
- `modules.yaml` — a mounted file with the full module list (the active config)
- `github` source (pinned release) **and** `repo` source (REDCap central
  repository by numeric `module_id`)

Modules are defined in [modules.yaml](modules.yaml). They can equally be defined
inline via numbered `MODULE_PROV_N` env vars (see the commented example in the
compose file).

For the full module data schema, every source type (`github`, `url`, `local`,
`repo`), and how to find a `module_id`, see [MODULE_PROV.md](../../MODULE_PROV.md).

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
- The `repo` source (Popup Alerts here) downloads via REDCap's own downloader and
  needs outbound access to `redcap.vumc.org`. It does **not** require your REDCap
  community login.
- Provisioning only executes once REDCap is installed; on a fresh auto-install
  it runs on the same boot, after installation.
- Provisioning a module runs third-party PHP in your webroot — only provision
  modules you trust. See [SECURITY.md](../../SECURITY.md).
