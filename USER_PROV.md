# DZD Docker REDCap User Provisioning


- [DZD Docker REDCap User Provisioning](#dzd-docker-redcap-user-provisioning)
- [Enable or disable user provisioning](#enable-or-disable-user-provisioning)
- [Update existing user](#update-existing-user)
- [User data structure/model](#user-data-structuremodel)
  - [Example](#example)
    - [Simple User](#simple-user)
    - [Simple User with password](#simple-user-with-password)
    - [User admin user with password](#user-admin-user-with-password)
    - [Super user](#super-user)
- [Provide user data](#provide-user-data)
  - [Option 1 - Json list in environment variable](#option-1---json-list-in-environment-variable)
  - [Option 2 - json in multiple indexed environment variable](#option-2---json-in-multiple-indexed-environment-variable)
  - [Option 3 - Yaml or json files](#option-3---yaml-or-json-files)


This container images has a feature to pre fill the REDCap database with table users.
The users will be created on container boot and optionally updated if allready existent on container boot.

# Enable or disable user provisioning

You can set the environment variable `ENABLE_USER_PROV` to true or false to enable or disable the user provisioning feature.

> [!TIP]
> user provisioning only make sense when there is table based authentification system enabled in REDCap. One way to enable it, is to use this containers images environment variable `RCCONF_auth_meth_global` (e.g. `RCCONF_auth_meth_global=table` and you may want to disable the public user at the same time with `REDCAP_SUSPEND_SITE_ADMIN=true`)

# Update existing user

You can set the environment variable `USER_PROV_OVERWRITE_EXISTING` to true or false to enable or disable overwriting if existing users.

"Existing" means there is a user with the same `username` as in the user provisioning data.


# User data structure/model

The user data need to be provided in json or yaml. One user entry needs to have at least the following attributes:

* `username`
* `user_email`
* `user_firstname`
* `user_lastname`

Following attributes can be provied optionaly:

* `password`
* `user_email2`
* `user_email3`
* `user_phone`
* `user_phone_sms`
* `user_inst_id`
* `super_user`
* `account_manager`
* `access_system_config`
* `access_system_upgrade`
* `access_external_module_install`
* `admin_rights`
* `access_admin_dashboards`
* `user_sponsor`
* `user_comments`
* `allow_create_db`
* `email_verify_code`
* `email2_verify_code`
* `email3_verify_code`
* `datetime_format`
* `number_format_decimal`
* `number_format_thousands_sep`
* `csv_delimiter`
* `two_factor_auth_secret`
* `two_factor_auth_enrolled`
* `display_on_email_users`
* `two_factor_auth_twilio_prompt_phone`
* `two_factor_auth_code_expiration`
* `api_token`
* `messaging_email_preference`
* `messaging_email_urgent_all`
* `messaging_email_general_system`
* `messaging_email_queue_time`
* `ui_state`
* `api_token_auto_request`
* `fhir_data_mart_create_project`

## Example

### Simple User

A simple user entry that will not be able to login until an admin provides a way to set the password

**Json**
```json
{
    "username": "r.jorden",
    "user_firstname": "Rebecca",
    "last_name": "Jorden",
    "user_email": "r.jorden@lv-426.exo"
}
```

**YAML**
```yaml
username: r.jorden
user_firstname: Rebecca
last_name: Jorden
user_email: r.jorden@lv-426.exo
```

### Simple User with password

A simple user that is allowed to login from the getgo

**Json**
```json
{
    "username": "r.frost",
    "user_firstname": "Ricco",
    "last_name": "Frost",
    "user_email": "r.frost@uss-sulaco.space",
    "password": "do-not-tell-anyone"
}
```

**YAML**
```yaml
- username: r.frost
  user_firstname: Ricco
  last_name: Frost
  user_email: r.frost@uss-sulaco.space
  password: do-not-tell-anyone
```

### User admin user with password

An low level REDCap admin that can manage user accounts

**Json**
```json
{
    "username": "s.gorman",
    "user_firstname": "Scott",
    "last_name": "Gorman",
    "user_email": "r.gorman@lv-426.exo",
    "password": "pssst1234",
    "admin_rights": 1,
    "account_manager": 1
}
```

**YAML**
```yaml
- username: s.gorman
  user_firstname: Scott
  last_name: Gorman
  user_email: r.gorman@lv-426.exo
  password: pssst1234
  admin_rights: 1
  account_manager: 1
```

### Super user

A full blown admin that can do anything

**Json**
```json
{
    "username": "bishop",
    "user_firstname": "Lance",
    "last_name": "Bishop",
    "user_email": "bishop@hyperdyne.sys",
    "password": "pw341-B",
    "super_user": 1,
    "account_manager": 1,
    "access_system_config": 1,
    "access_system_upgrade": 1,
    "access_external_module_install": 1,
    "access_admin_dashboards": 1
}
```

**YAML**
```yaml
- username: bishop
  user_firstname: Lance
  last_name: Bishop
  user_email: bishop@hyperdyne.sys
  password: pw341-B
  super_user: 1
  account_manager: 1
  access_system_config: 1
  access_system_upgrade: 1
  access_external_module_install: 1
  access_admin_dashboards: 1
```

# Provide user data

There are 3 different options to provide the user data.

## Option 1 - Json list in environment variable

You can provide a one lined json list with multiple user data objects into the environment variable `USER_PROV`.

e.g.
```env
USER_PROV='[{"username": "user1","user_firstname": "userone","user_lastname": "mcone","user_email": "one@test.com"},{"username": "user12","user_firstname": "usertwo","user_lastname": "mctwo","user_email": "two@test.com"}]'
```

## Option 2 - json in multiple indexed environment variable

As option one will be messy very quickly the next best option is to index the env var `USER_PROV` with a `_` + sequentiel numbers. e.g.:  
e.g.
```env
USER_PROV_1='{"username": "user1","user_firstname": "userone","user_lastname": "mcone","user_email": "one@test.com"}'
USER_PROV_2='{"username": "user12","user_firstname": "usertwo","user_lastname": "mctwo","user_email": "two@test.com"}'
```

## Option 3 - Yaml or json files

As both option 1 and 2 are hard to read as soon as we hit a certain numbers of user, the best option is to provide the users in files.

The expected file structure is a yaml or json object that has the root key `REDCapUserList` which should container a list of user objects.

You can have multiple files. the REDCap docker container images scan the directory defined in the env var `USER_PROV_FILE_DIR` which will be `/opt/redcap-docker/users` by default.

**Json file example:**

```json
{
  "REDCapUserList": [
    {
      "username": "r.jorden",
      "user_firstname": "Rebecca",
      "last_name": "Jorden",
      "user_email": "r.jorden@lv-426.exo"
    },
    {
      "username": "r.frost",
      "user_firstname": "Ricco",
      "last_name": "Frost",
      "user_email": "r.frost@uss-sulaco.space",
      "password": "do-not-tell-anyone"
    },
    {
      "username": "s.gorman",
      "user_firstname": "Scott",
      "last_name": "Gorman",
      "user_email": "r.gorman@lv-426.exo",
      "password": "pssst1234",
      "admin_rights": 1,
      "account_manager": 1
    },
    {
      "username": "bishop",
      "user_firstname": "Lance",
      "last_name": "Bishop",
      "user_email": "bishop@hyperdyne.sys",
      "password": "pw341-B",
      "super_user": 1,
      "account_manager": 1,
      "access_system_config": 1,
      "access_system_upgrade": 1,
      "access_external_module_install": 1,
      "access_admin_dashboards": 1
    }
  ]
}
```

**Yaml file example:**

```yaml
REDCapUserList:
- username: r.jorden
  user_firstname: Rebecca
  last_name: Jorden
  user_email: r.jorden@lv-426.exo
- username: r.frost
  user_firstname: Ricco
  last_name: Frost
  user_email: r.frost@uss-sulaco.space
  password: do-not-tell-anyone
- username: s.gorman
  user_firstname: Scott
  last_name: Gorman
  user_email: r.gorman@lv-426.exo
  password: pssst1234
  admin_rights: 1
  account_manager: 1
- username: bishop
  user_firstname: Lance
  last_name: Bishop
  user_email: bishop@hyperdyne.sys
  password: pw341-B
  super_user: 1
  account_manager: 1
  access_system_config: 1
  access_system_upgrade: 1
  access_external_module_install: 1
  access_admin_dashboards: 1
```


Have a look at the directory at [examples/local_instance_with_user_prov](examples/local_instance_with_user_prov) for a fully working docker compose exmaple that uses user provisioning 

