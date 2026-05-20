#!/usr/bin/env bash
# Load _FILE secrets into the environment before handing off to PHP,
# so REDCAP_COMMUNITY_PASSWORD_FILE etc. work when invoked via docker exec.
. /opt/redcap-docker/assets/scripts/startup-scripts/05_load_secrets.sh
exec php -f /opt/redcap-docker/assets/scripts/redcap_upgrader.php -- "$@"
