# User provisioning

Create and manage REDCap user accounts declaratively, via environment variables and/or a YAML file. Users are created (or updated) on every container start.

**What this example shows**

- `ENABLE_USER_PROV: "true"` — activate user provisioning
- `USER_PROV_OVERWRITE_EXISTING: "true"` — update existing users on every boot
- `USER_PROV_1`, `USER_PROV_2` — inline JSON user definitions via env vars
- `USER_PROV_FILE_DIR` — mount a YAML file with a full user list
- `RCCONF_auth_meth_global: table` — use table-based (local) authentication
- `REDCAP_SUSPEND_SITE_ADMIN: "true"` — disable the default admin account

Users are defined in [users.yaml](users.yaml). Additional users can be added inline via numbered `USER_PROV_N` env vars (see the compose file).

For the full user data schema (all 23+ supported fields), see [USER_PROV.md](../../USER_PROV.md).

## Quick start

```bash
git clone https://github.com/DZD-eV-Diabetes-Research/redcap-docker.git
cd redcap-docker/examples/local_instance_with_user_prov
```

Place your REDCap source in `./data/redcap/`, then:

```bash
docker compose pull             # pull the latest image — we ship fixes frequently
docker compose up -d
docker compose logs -f redcap
```

The provisioned users (from `users.yaml` and the `USER_PROV_*` env vars) are available at first login.

## users.yaml format

```yaml
REDCapUserList:
  - username: jane.doe
    user_firstname: Jane
    user_lastname: Doe
    user_email: jane.doe@example.com
    password: initial-password    # optional; user must change on first login
    super_user: 1                 # optional; grants superuser rights
```

See [users.yaml](users.yaml) for a complete example with different privilege levels.
