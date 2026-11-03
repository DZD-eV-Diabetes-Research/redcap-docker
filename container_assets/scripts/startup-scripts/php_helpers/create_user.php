<?php

require_once __DIR__ . '/db.php';

# from php_helpers/redcap_info.php
require_once __DIR__ . '/redcap_info.php';
$REDCAPDIR = get_installed_redcap_version_dir_path();
$REDCAP_CLASSES_DIR = "$REDCAPDIR/Classes";
$current_include_path = get_include_path();
set_include_path(".:$REDCAP_CLASSES_DIR");

# import generateRandomHash() function
require_once $REDCAPDIR  . '/Config/init_functions.php';
# import helper classes from redcap
require_once $REDCAP_CLASSES_DIR  . '/Authentication.php';
require_once $REDCAP_CLASSES_DIR  . '/User.php';

error_reporting(E_ALL);

ini_set("display_errors", 1);
class UserData
{
    public ?int $ui_id;
    public ?string $password;
    public ?string $username;
    public ?string $user_email;
    public ?string $user_email2;
    public ?string $user_email3;
    public ?string $user_phone;
    public ?string $user_phone_sms;
    public ?string $user_firstname;
    public ?string $user_lastname;
    public ?string $user_inst_id;
    public ?int $super_user;
    public ?int $account_manager;
    public ?int $access_system_config;
    public ?int $access_system_upgrade;
    public ?int $access_external_module_install;
    public ?int $admin_rights;
    public ?int $access_admin_dashboards;
    public ?string $user_sponsor;
    public ?string $user_comments;
    public ?int $allow_create_db;
    public ?string $email_verify_code;
    public ?string $email2_verify_code;
    public ?string $email3_verify_code;
    public ?string $datetime_format;
    public ?string $number_format_decimal;
    public ?string $number_format_thousands_sep;
    public ?string $csv_delimiter;
    public ?string $two_factor_auth_secret;
    public ?int $two_factor_auth_enrolled;
    public ?int $display_on_email_users;
    public ?int $two_factor_auth_twilio_prompt_phone;
    public ?int $two_factor_auth_code_expiration;
    public ?string $api_token;
    public ?string $messaging_email_preference;
    public ?int $messaging_email_urgent_all;
    public ?int $messaging_email_general_system;
    public ?string $messaging_email_queue_time;
    public ?string $ui_state;
    public ?int $api_token_auto_request;
    public ?int $fhir_data_mart_create_project;

    // Not a __set() magic method: PHP never calls __set() for declared typed properties,
    // so values assigned directly would skip the type checks and sanitization below.
    public function set_value(string $name, $value): void
    {
        // values that are stored as given (opaque secrets and serialized data)
        $no_sanitization_props = array("password", "two_factor_auth_secret", "api_token", "ui_state");
        if (!property_exists($this, $name)) {
            throw new InvalidArgumentException("Invalid property for User: $name");
        }
        // null means "not set", the column default applies. 0 and false are values (#20)
        if ($value === null) {
            return;
        }
        if (!is_scalar($value)) {
            throw new InvalidArgumentException("Property '$name' expects a single value, got a " . gettype($value));
        }
        $prop_info = new ReflectionProperty($this, $name);
        if ($prop_info->getType()->getName() == 'int') {
            $int_value = is_bool($value) ? (int)$value : filter_var($value, FILTER_VALIDATE_INT);
            if ($int_value === false) {
                throw new InvalidArgumentException("Property '$name' expects an integer");
            }
            $this->$name = $int_value;
        } elseif (in_array($name, $no_sanitization_props)) {
            $this->$name = (string)$value;
        } else {
            $this->$name = trim(strip_tags(label_decode((string)$value)));
        }
    }

    public function get_required_fields_that_are_empty(): array
    {
        $null_fields = [];
        $required_fields = array("username", "user_email", "user_firstname", "user_lastname");
        foreach ($required_fields as $req_field_name) {
            if (!isset($this->$req_field_name) or $this->$req_field_name == null or $this->$req_field_name == '') {
                $null_fields[] = $req_field_name;
            }
        }
        return $null_fields;
    }
    public function required_fields_not_empty(): bool
    {
        return empty($this->get_required_fields_that_are_empty());
    }
}

class UserAuthData
{
    public string $password;
    public string $salt;
    public string $pw_hash;
    public function __construct(string $password)
    {
        $this->password = $password;
        $this->salt = Authentication::generatePasswordSalt();
        $this->pw_hash = Authentication::hashPassword($password, $this->salt, '');
    }
}

class UserCRUD
{
    private mysqli $db;
    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }
    // All values go through prepared statements, user data can contain quotes (e.g. "O'Neill", #21)
    private function execute(string $query, string $types, array $params): mysqli_stmt
    {
        $stmt = $this->db->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt;
    }

    private function UserIdByName(string $username): ?int
    {
        $stmt = $this->execute("SELECT ui_id FROM `redcap_user_information` WHERE `username` = ?;", "s", [$username]);
        $ui_id = $stmt->get_result()->fetch_column();
        $stmt->close();
        return $ui_id === false ? null : (int)$ui_id;
    }

    // Column names are the declared UserData properties, only the values are user input
    private function userColumns(UserData $data): array
    {
        $ignore_props = array("password", "ui_id");
        $columns = [];
        foreach ($data as $key => $value) {
            if ($value !== null and !in_array($key, $ignore_props)) {
                $columns[$key] = $value;
            }
        }
        return $columns;
    }

    private static function bindTypes(array $values): string
    {
        return implode("", array_map(fn($value) => is_int($value) ? "i" : "s", $values));
    }

    private function InsertUser(UserData $data): void
    {
        $columns = $this->userColumns($data);
        $columnStr = implode(", ", array_map(fn($column) => "`$column`", array_keys($columns)));
        $placeholderStr = implode(", ", array_fill(0, count($columns), "?"));
        $query = "INSERT INTO `redcap_user_information` ($columnStr) VALUES ($placeholderStr);";
        $this->execute($query, self::bindTypes($columns), array_values($columns))->close();
    }

    private function UpdateUser(UserData $data): void
    {
        $columns = $this->userColumns($data);
        $setValueStr = implode(", ", array_map(fn($column) => "`$column` = ?", array_keys($columns)));
        $query = "UPDATE `redcap_user_information` SET $setValueStr WHERE `ui_id` = ?;";
        $params = array_values($columns);
        $params[] = $data->ui_id;
        $this->execute($query, self::bindTypes($params), $params)->close();
    }

    public function UpsertPassword(UserData $userdata)
    {
        if (!isset($userdata->password) or $userdata->password === '') {
            printf("Can not set password for user '%s'. Password is empty.\n", $userdata->username);
            return;
        }
        $user_auth = new UserAuthData($userdata->password);
        $query = "INSERT INTO `redcap_auth` (`username`, `password`, `password_salt`) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE `password` = ?, `password_salt` = ?;";
        $this->execute($query, "sssss", [$userdata->username, $user_auth->pw_hash, $user_auth->salt, $user_auth->pw_hash, $user_auth->salt])->close();
    }

    public function UpsertUser(UserData $userdata, bool $overwrite_existing = false)
    {
        $ui_id = $this->UserIdByName($userdata->username);
        if ($ui_id !== null and !$overwrite_existing) {
            return;
        }
        // user and password in one transaction, a user created without its password would never get it on later boots
        $this->db->begin_transaction();
        try {
            if ($ui_id !== null) {
                printf("[USER PROVISIONING]: Update user '%s'\n", $userdata->username);
                $userdata->ui_id = $ui_id;
                $this->UpdateUser($userdata);
            } else {
                printf("[USER PROVISIONING]: Create user '%s'\n", $userdata->username);
                $this->InsertUser($userdata);
            }
            // new users always get their password, existing ones only when overwriting is enabled
            if (isset($userdata->password) and $userdata->password !== '') {
                printf("[USER PROVISIONING]: Update/Create password for user '%s'\n", $userdata->username);
                $this->UpsertPassword($userdata);
            }
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }
}



# test
/* 
printf("Set user\n");
$user = new UserData();
$user->username = "testi.mc.testfit";
$user->password = "dausiogquwekb";
$user->user_email = "test@test.com";
$user->user_firstname = "Testi";
$user->user_lastname = "mc testfit";
$mysqli_con = get_db_con();
$uc = new UserCRUD($mysqli_con);
$uc->UpsertUser($user, true);
$mysqli_con->close();
*/
