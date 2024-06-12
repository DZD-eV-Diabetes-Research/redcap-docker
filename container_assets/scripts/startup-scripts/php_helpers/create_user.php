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

    public function __set($name, $value)
    {
        $no_sanitization_props = array("pass");
        if (property_exists($this, $name) and ($value != null)) {
            $prop_info = new ReflectionProperty($this, $name);
            // lets sanitate all strings (if its not blacklisted or anything other as a string)
            if (in_array($name, $no_sanitization_props) or $prop_info->getType()->getName() != 'string') {
                $this->$name = trim($value);
            } else {
                $this->$name = trim(strip_tags(label_decode($value)));
            }
        } elseif (!property_exists($this, $name)) {
            throw new InvalidArgumentException("Invalid property for User: $name");
        }
    }

    public function get_required_fields_that_are_empty(): array
    {
        $null_fields = [];
        $required_fields = array("username", "user_email", "user_firstname", "user_lastname");
        foreach ($required_fields as $req_field_name) {
            if ($this->$req_field_name == null or $this->$req_field_name == '') {
                $null_fields[] = $req_field_name;
            }
        }
        return $null_fields;
    }
    public function check_if_required_fields_not_empty(): bool
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
    private function UserIdByName($username)
    {
        $query = "SELECT ui_id FROM `redcap_user_information` WHERE `username` = '$username'; ";
        $res = $this->db->query($query);
        $row = $res->fetch_column();
        return $row;
    }
    private function UserExists($username)
    {
        $query = "SELECT 1 FROM `redcap_user_information` WHERE `username` = '$username'; ";
        $res = $this->db->query($query);
        $row = $res->fetch_assoc();
        return (bool)$row;
    }

    private function generateInsertUserQuery(UserData $data)
    {
        $tableName = "redcap_user_information";
        $columns = [];
        $values = [];
        $ignore_props = array("password", "ui_id");

        foreach ($data as $key => $value) {
            if ($value !== null and !in_array($key, $ignore_props)) {
                $columns[] = $key;
                $values[] = is_int($value) ? $value : "'$value'";
            }
        }

        $columnStr = implode(", ", $columns);
        $valueStr = implode(", ", $values);

        $query = "INSERT INTO $tableName ($columnStr) VALUES ($valueStr);";

        return $query;
    }
    private function generateUpdateUserQuery(UserData $data)
    {
        $tableName = "redcap_user_information";
        $setValues = [];
        $ignore_props = array("password", "ui_id");

        foreach ($data as $key => $value) {
            if ($value !== null and !in_array($key, $ignore_props)) {
                $setValues[] = "$key = " . (is_int($value) ? $value : "'$value'");
            }
        }

        $setValueStr = implode(", ", $setValues);
        $query = "UPDATE $tableName SET $setValueStr WHERE ui_id=$data->ui_id;";

        return $query;
    }

    public function UpsertPassword(UserData $userdata)
    {
        if ($userdata->password == null or $userdata->password == '') {
            printf("Can not set password for user '$userdata->username'. Password is empty.\n");
            return;
        }
        $user_auth = new UserAuthData($userdata->password);
        $query = "INSERT INTO `redcap_auth` (`username`,`password`, `password_salt`) VALUES('$userdata->username', '$user_auth->pw_hash','$user_auth->salt') ON DUPLICATE KEY UPDATE `password` ='$user_auth->pw_hash', `password_salt`='$user_auth->salt';";
        $this->db->query($query);
    }

    public function UpsertUser(UserData $userdata, bool $overwrite_existing = false)
    {

        $user_exists = $this->UserExists($userdata->username);
        $query = null;
        if ($user_exists and !$overwrite_existing) {
            return;
        } elseif ($user_exists) {
            printf("[USER PROVISIONING]: Update user '$userdata->username'\n");
            $userdata->ui_id = $this->UserIdByName($userdata->username);
            $query = $this->generateUpdateUserQuery($userdata);
        } else {
            printf("[USER PROVISIONING]: Create user '$userdata->username'\n");
            $query = $this->generateInsertUserQuery($userdata);
        }
        $res = $this->db->query($query);
        if ($overwrite_existing and ($userdata->password != null or $userdata->password != '')) {
            printf("[USER PROVISIONING]: Update/Create password for user '$userdata->username'\n");
            $this->UpsertPassword($userdata);
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
