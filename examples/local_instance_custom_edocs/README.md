# Custom edocs path (uploads outside the webroot)

By default REDCap stores user-uploaded files in `/var/www/html/edocs`, inside the Apache document root. This example moves that storage to a separate volume outside the webroot.

**Why this matters:** if a `.htaccess` bypass or misconfiguration occurs, files stored in the webroot could be served directly over HTTP. Keeping uploads outside the webroot eliminates that risk entirely.

**What this example shows**

- `RCCONF_edoc_path: /data` — tells REDCap to write uploaded files to `/data` instead of the webroot
- A separate `./data/user-files` volume mounted at `/data`

See [SECURITY.md: REDCap edocs outside the webroot](../../SECURITY.md#redcap-edocs-outside-the-webroot) for full details.

## Quick start

```bash
git clone https://github.com/DZD-eV-Diabetes-Research/redcap-docker.git
cd redcap-docker/examples/local_instance_custom_edocs
```

Place your REDCap source in `./data/redcap/`, then:

```bash
docker compose pull             # pull the latest image — we ship fixes frequently
docker compose up -d
docker compose logs -f redcap
```

Uploaded files will appear in `./data/user-files/` on the host, outside the web server's document root.

## Notes

- The `/data` volume must be writable by `www-data`. The container sets the correct permissions at startup.
- You can use any host path or Docker named volume instead of `./data/user-files`.
- Combine this with [docker_secrets](../docker_secrets/) for a more hardened setup.
