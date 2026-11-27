# Email via MSMTP

Extends the basic setup with outbound email. The container uses [msmtp](https://marlam.de/msmtp/) as a sendmail replacement, configured entirely through `MSMTP_*` environment variables.

**What this example shows**

- `MSMTP_host` / `MSMTP_port` — SMTP server address and port
- `MSMTP_user` / `MSMTP_password` — SMTP authentication credentials
- `MSMTP_tls` / `MSMTP_tls_starttls` — TLS configuration
- `RCCONF_from_email` — sets the REDCap "from" address in the database

The example uses a fictional Hetzner mail account. Replace the `MSMTP_*` values with your actual SMTP provider settings.

## Quick start

```bash
git clone https://github.com/DZD-eV-Diabetes-Research/redcap-docker.git
cd redcap-docker/examples/instance_with_mail
```

Place your REDCap source in `./data/redcap/`, fill in the `MSMTP_*` values, then:

```bash
docker compose pull             # pull the latest image — we ship fixes frequently
docker compose up -d
docker compose logs -f redcap
```

## MSMTP variable reference

| Variable | Example | Notes |
|---|---|---|
| `MSMTP_from` | `redcap@example.com` | Envelope sender address |
| `MSMTP_host` | `mail.example.com` | SMTP server hostname |
| `MSMTP_port` | `587` | 587 = STARTTLS, 465 = implicit TLS, 25 = unencrypted |
| `MSMTP_auth` | `on` | Enable SMTP authentication |
| `MSMTP_user` | `redcap@example.com` | SMTP login username |
| `MSMTP_password` | `secret` | SMTP login password |
| `MSMTP_tls` | `on` | Enable TLS |
| `MSMTP_tls_starttls` | `on` | Use STARTTLS (port 587). Set `off` for implicit TLS (port 465) |
| `MSMTP_syslog` | `on` | Log sent mail to syslog (visible in `docker compose logs`) |

For sensitive deployments, store `MSMTP_password` in a file and reference it, or use the [docker_secrets](../docker_secrets/) example pattern.
