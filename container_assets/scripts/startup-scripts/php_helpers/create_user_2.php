<?php
# Ignore this code! 
# THIS IS A FAILED TRY TO USE EXISTING REDCap php files. There is no class API in redcap i can reuse. Errors are suppressed instead of passed along. Impossible to debug. A pitty.
# create_user.php is the correct implemenation. 
# This is just here if i revisit this idea one day.

# import 
#get_redcap_source_path()
#get_existent_redcap_version_dirs()
#get_highest_redcap_version_dir()
#get_highest_redcap_version_no()
#get_installed_redcap_version_no()
#get_installed_redcap_version_dir_path() 
# from php_helpers/redcap_info.php
require __DIR__ . '/redcap_info.php';
$REDCAPDIR = get_installed_redcap_version_dir_path();
$REDCAP_CLASSES_DIR = "$REDCAPDIR/Classes";
$current_include_path = get_include_path();
set_include_path(".:$REDCAP_CLASSES_DIR:$REDCAPDIR/Libraries/PEAR/");


#require $REDCAP_CLASSES_DIR  . '/Authentication.php';
#require $REDCAP_CLASSES_DIR  . '/User.php';

require_once $REDCAP_CLASSES_DIR  . '/RedCapDB.php';

# import label_decode()
require $REDCAPDIR . '/Config/init_functions.php';

function removeDuplicateIncludePath()
{
    $paths = explode(PATH_SEPARATOR, get_include_path());
    $uniquePaths = array_unique($paths);
    set_include_path(implode(PATH_SEPARATOR, $uniquePaths));
}


class UserData
{
    public $ui_id = null;
    public
        $username = null;
    public
        $fname = null;
    public
        $lname = null;
    public
        $email = null;
    public
        $email2 = null;
    public
        $email3 = null;
    public
        $inst_id = null;
    public
        $expiration = null;
    public
        $user_sponsor = null;
    public
        $user_comments = null;
    public
        $allow_create_db = null;
    public
        $pass = null;
    public
        $datetime_format = null;
    public
        $number_format_decimal = null;
    public
        $number_format_thousands_sep = null;
    public
        $display_on_email_users = null;
    public
        $user_phone = null;
    public
        $user_phone_sms = null;
    public $messaging_email_preference = '4_HOURS';
    public $messaging_email_urgent_all = '1';
    public $api_token_auto_request = '1';
    public $isAaf = 0;
    public $fhir_data_mart_create_project = 0;

    public function __set($name, $value)
    {
        $no_sanitization_props = array("pass");
        if (property_exists($this, $name) and ($value != null)) {
            if (in_array($name, $no_sanitization_props)) {
                $this->$name = trim($value);
            } else {
                $this->$name = trim(strip_tags(label_decode($value)));
            }
        } elseif (!property_exists($this, $name)) {
            throw new InvalidArgumentException("Invalid property for User: $name");
        }
    }
}


error_reporting(E_ALL);

ini_set("display_errors", 1);
function UpsertUser(UserData $userdata, bool $overwrite_existing = false)
{
    $user_exists = User::exists($userdata->username);
    if ($user_exists and !$overwrite_existing) {
        return;
    } elseif ($user_exists) {
        $userdata->ui_id::getUIIDByUsername($userdata->username);
    }
    $ud = get_object_vars($userdata);
    removeDuplicateIncludePath();
    $new_include_path = get_include_path();
    printf("INCLUDE PATH: $new_include_path\n");
    #print_array($ud);
    printf("Connect DB\n");
    try {
        $db = new RedCapDB();
    } catch (Exception $e) {
        printf('Caught exception: ',  $e->getMessage(), "\n");
    }

    printf("Save user\n");
    # This generates some error but it is not passed along. instead we just get an empty array.
    # very hard to debug... I surrender at this point.
    $sql = $db->saveUser(...get_object_vars($userdata));
    printf("SQL:");
    print_array($sql);
}

# test
printf("Set user\n");
$user = new UserData();
$user->username = "testi.mc.testfit";
$user->pass = "dausiogquwekb";
$user->email = "test@test.com";
$user->lname = "Testi";
$user->fname = "mc testfit";

UpsertUser($user, true);

## 
# User::exists($username)
# User::saveAdminPriv(($userid, $attr, $value))
# User::getUserInfoByUiid($userid);
# User::getUIIDByUsername($username)
