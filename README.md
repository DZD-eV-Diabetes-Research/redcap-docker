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




# Troubleshooting

## I get a "permission denied" error, when trying to visit my new REDCap instance

Check and adapt the permissions of your REDCap source files.

First try to give ownership of the REDCap files to www-data:
```bash
docker compose exec redcap /bin/bash -c 'chown -R www-data ${APACHE_DOCUMENT_ROOT}'
```

If you still get permission problem try to set the permission for all directories and files with the following two commands:
```bash
docker compose exec redcap /bin/bash -c 'find ${APACHE_DOCUMENT_ROOT} -type d -exec chmod 755 {} \;'
```
```bash
docker compose exec redcap /bin/bash -c 'chmod -R 644 *.php'
```
