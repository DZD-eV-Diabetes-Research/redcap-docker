# Project provisioning

Create REDCap projects on container start from REDCap project XML files. Each project is created once; later starts skip it.

**What this example shows**

- `ENABLE_PROJECT_PROV: "true"` activates project provisioning
- [projects/projects.yaml](projects/projects.yaml), mounted to `/opt/redcap-docker/projects`, lists the projects to create:
  - `study-template`: the XML as it is, owned by the provisioned user `study.lead`
  - `training`: the same XML as an empty project (`metadata_only`) with its own title and no users
- user provisioning creates the owner first, so the owner can log in and use the project right away

For the full spec format, URL downloads and checksums, see [PROJECT_PROV.md](../../PROJECT_PROV.md).

## Quick start

```bash
git clone https://github.com/DZD-eV-Diabetes-Research/redcap-docker.git
cd redcap-docker/examples/instance_with_project_prov
```

1. Place your REDCap source in `./data/redcap/`.
2. Export a project from any REDCap instance as XML (*Project Setup* → *Other Functionality* → *Download metadata only (XML)*) and save it as `./projects/study-template.xml`.
3. Start:

```bash
docker compose pull             # pull the latest image, we ship fixes frequently
docker compose up -d
docker compose logs -f redcap
```

Watch the logs for `[PROJECT PROVISIONING]` lines, then log in as `study.lead` (password from the compose file) to see the project.

## Notes

- Provisioning runs once REDCap is installed; on a fresh auto install it runs on the same boot, after installation.
- Changing the XML or the spec later does not change an existing project. A deleted project is not created again, see [PROJECT_PROV.md](../../PROJECT_PROV.md#already-provisioned-and-deleted-projects).
- Change the example password and `DB_SALT` before using this anywhere but your workstation.
