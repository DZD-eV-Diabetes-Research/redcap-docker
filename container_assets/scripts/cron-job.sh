#!/bin/bash
# https://unix.stackexchange.com/questions/595249/what-does-the-eu-mean-in-bin-bash-eu-at-the-top-of-a-bash-script-or-a
# set -u tells the shell to treat expanding an unset parameter an error, which helps to catch e.g. typos in variable names.
# set -e tells the shell to exit if a command exits with an error (except if the exit value is tested in some other way). That can be used in some cases to abort the script on error, without explicitly testing the status of each and every command.




set -eu
. /etc/container-environment.sh
. /opt/redcap-docker/assets/scripts/catch.sh
datetime=$(date '+%d/%m/%Y %H:%M:%S %Z')
echo "#### Cron run start ($datetime)"
# run the cron job
catch stdout stderr php -f ${APACHE_DOCUMENT_ROOT}/cron.php
if [ -z "$stderr" ]; then
    echo "#### Cron output ($datetime): $stdout"
    # redirect output to process with id 1 (which docker by default shows in the "docker compose logs")
    echo "0" >$CRON_HEALTH_STATE_FILE

else
    # we have content in stderr. something went wrong
    echo "#### Cron error ($datetime): $stderr"
    echo "$stdout $stderr" >$CRON_HEALTH_STATE_FILE
fi
