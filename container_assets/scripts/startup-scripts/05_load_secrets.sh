#!/usr/bin/env bash
# Load secrets from files into environment variables.
#
# Supports the Docker Secrets / _FILE convention: if DB_PASSWORD_FILE is set
# to a path, its contents are read into DB_PASSWORD. This avoids putting
# sensitive values directly in environment variables, where they can be seen
# via `docker inspect` or /proc/<pid>/environ.
#
# Supported variables:
#   DB_PASSWORD              ← DB_PASSWORD_FILE
#   DB_SALT                  ← DB_SALT_FILE
#   DB_USERNAME              ← DB_USERNAME_FILE
#   REDCAP_COMMUNITY_PASSWORD ← REDCAP_COMMUNITY_PASSWORD_FILE

_load_secret_from_file() {
    local var_name="$1"
    local file_var="${var_name}_FILE"
    local file_path="${!file_var}"

    if [[ -z "${file_path}" ]]; then
        return 0
    fi

    if [[ ! -f "${file_path}" ]]; then
        echo "[SECRETS] WARNING: ${file_var}='${file_path}' but file does not exist — skipping."
        return 0
    fi

    local value
    value="$(cat "${file_path}")"
    export "${var_name}"="${value}"
    echo "[SECRETS] Loaded ${var_name} from ${file_path}."
}

_load_secret_from_file DB_PASSWORD
_load_secret_from_file DB_SALT
_load_secret_from_file DB_USERNAME
_load_secret_from_file REDCAP_COMMUNITY_PASSWORD
