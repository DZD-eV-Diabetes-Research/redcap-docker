#!/usr/bin/env bash
# Entry-point for the REDCap in-place upgrader.
# Run via:  docker compose exec redcap redcap-upgrade [OPTIONS]
exec php -f /opt/redcap-docker/assets/scripts/redcap_upgrader.php -- "$@"
