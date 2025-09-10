get_php_ini_dirs() {
    # return all ini dirs scanned by php and return them in a `PHP_INI_SCAN_DIR` friendly format.
    # also sort the in a way that all dir starting with /usr/ or /etc/ will be first. this way we ensure that custom ini dir will overwrite defaults.
    php --ini 2>/dev/null | awk '
    /Configuration File.*Path:/ { gsub(/.*Path: */, ""); if ($0 != "(none)") dirs[$0] = 1 }
    /Loaded Configuration File:/ { gsub(/.*File: */, ""); if ($0 != "(none)") { gsub(/\/[^\/]*$/, ""); dirs[$0] = 1 } }
    /Scan for additional.*in:/ { gsub(/.*in: */, ""); gsub(/:/, " "); for (i=1; i<=NF; i++) if ($i != "(none)" && $i != "") dirs[$i] = 1 }
    /Additional.*parsed:/ { in_files = 1; next }
    in_files && /^ *[^:]*\.ini/ { gsub(/,/, "\n"); gsub(/^ */, ""); gsub(/\/[^\/]*$/, ""); if ($0 != "") dirs[$0] = 1 }
    END { 
        for (d in dirs) if (d ~ /^\/usr/) printf "%s:", d
        for (d in dirs) if (d !~ /^\/usr/) printf "%s:", d
        print "" 
    }
    ' | sed 's/:$//'
}

get_php_ini_dirs_OLD_BUGGY() {
    local dirs=()
    
    while IFS= read -r line; do
        case "$line" in
            "Configuration File"*"Path:"*)
                dir=${line#*Path: }
                [[ "$dir" != "(none)" ]] && dirs+=("$dir")
                ;;
            "Loaded Configuration File:"*)
                file=${line#*File: }
                [[ "$file" != "(none)" ]] && dirs+=("$(dirname "$file")")
                ;;
            "Scan for additional"*"in:"*)
                scan_dirs=${line#*in: }
                IFS=':' read -ra scan_array <<< "$scan_dirs"
                for d in "${scan_array[@]}"; do
                    d=$(echo "$d" | xargs)  # trim whitespace
                    [[ -n "$d" && "$d" != "(none)" ]] && dirs+=("$d")
                done
                ;;
            *".ini"*)
                # Additional ini files - extract directory from each file
                echo "$line" | tr ',' '\n' | while read -r file; do
                    file=$(echo "$file" | xargs)  # trim whitespace
                    [[ -n "$file" ]] && dirs+=("$(dirname "$file")")
                done
                ;;
        esac
    done < <(php --ini 2>/dev/null)
    
    # Remove duplicates and join with colons
    printf '%s\n' "${dirs[@]}" | sed 's/^[[:space:]]*//' | sort -u | paste -sd ':'
}

