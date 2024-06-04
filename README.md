# redcap-docker
Yet another try to containerize REDCap


## Environment Variables

## container pathes

### REDCap php scripts aka apache document root dir

This directory will contain the REDCap php scripts.

`/var/www/html`

This dir can be changed via env var `APACHE_DOCUMENT_ROOT`

### Custom php ini config

Put any custom php ini config in this directory to be included in the php configuration. See https://www.php.net/manual/en/ini.list.php for all config variables

`/config/php/custom_inis`

This dir can be changed via env var `PHP_INI_SCAN_DIR`

### Custom apache virtual host directives

`/config/apache/custom.virtualhost`


