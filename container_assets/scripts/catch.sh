#/bin/bash
# Tim Bleimehl
# from https://stackoverflow.com/questions/11027679/capture-stdout-and-stderr-into-different-variables
# a function to capture stderr, stdout into variables.
# ToDo: Optionally save error directly to jounralctl logs

# SYNTAX:
#   catch STDOUT_VARIABLE STDERR_VARIABLE COMMAND [ARG1[ ARG2[ ...[ ARGN]]]]
catch() {
    {
        IFS=$'\n' read -r -d '' "${1}";
        IFS=$'\n' read -r -d '' "${2}";
        (IFS=$'\n' read -r -d '' _ERRNO_; return ${_ERRNO_});
    } < <((printf '\0%s\0%d\0' "$(((({ shift 2; "${@}"; echo "${?}" 1>&3-; } | tr -d '\0' 1>&4-) 4>&2- 2>&1- | tr -d '\0' 1>&4-) 3>&1- | exit "$(cat)") 4>&1-)" "${?}" 1>&2) 2>&1)
}


