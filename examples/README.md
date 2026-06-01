# Examples

Each subdirectory is a self-contained `docker compose` setup. Copy the one closest to your use case and adjust.

> **REDCap source files are not included.** See the [Disclaimer](../README.md#disclaimer). You need to provide your own licensed copy, or let the auto-install example fetch it for you with a provided REDCap Community Login.

## Picking an example

| Example | What it shows |
|---|---|
| [local_instance_basic](local_instance_basic/) | Minimal setup. Bring your own REDCap files |
| [auto_install](auto_install/) | 🧪 Auto-download REDCap from the community portal on first boot |
| [instance_with_mail](instance_with_mail/) | Email via MSMTP |
| [instance_with_cron](instance_with_cron/) | Separate cron container for REDCap background jobs |
| [local_instance_with_user_prov](local_instance_with_user_prov/) | Provision users from env vars and a YAML file |
| [local_instance_custom_edocs](local_instance_custom_edocs/) | Store user uploads outside the webroot |
| [docker_secrets](docker_secrets/) | 🔒 Keep passwords out of env vars using Docker Secrets |

🧪 = uses beta features (see [Beta Channel](../README.md#beta-channel))  
🔒 = security-focused

## Common to all examples

Every example shares the same base structure:

```
docker-compose.yaml    ← the only file you need to edit
data/
  db/                  ← MySQL data (auto-created)
  redcap/              ← REDCap PHP files (you provide, or auto-installed)
```

Start any example with:

```bash
docker compose up -d
docker compose logs -f redcap   # watch startup progress
```

Visit `http://localhost` once both containers show `(healthy)`.

## Variable reference

All supported environment variables are documented in [config_vars_list.md](../config_vars_list.md).
