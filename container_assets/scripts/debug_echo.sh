debug_echo() {
    local debug_flag="${REDCAP_DOCKER_SCRIPTS_DEBUG:-}"

    case "$debug_flag" in
        "true"|"TRUE"|"True"|"1")
            # Prefix first line with [DEBUG REDCAP DOCKER], align subsequent lines
            local prefix="[DEBUG REDCAP DOCKER] "
            local indent="$(printf "%*s" ${#prefix} "")"

            printf "%s\n" "$*" | sed "1s/^/${prefix}/; 2,\$s/^/${indent}/"
            ;;
    esac
}