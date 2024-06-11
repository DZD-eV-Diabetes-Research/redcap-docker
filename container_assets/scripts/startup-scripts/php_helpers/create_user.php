<?php

//IMPORTS
# import get_db_con() from php_helpers/db.php
require __DIR__ . '/php_helpers/db.php';

# import 
#get_redcap_source_path()
#get_existent_redcap_version_dirs()
#get_highest_redcap_version_dir()
#get_highest_redcap_version_no()
#get_installed_redcap_version_no()
#get_installed_redcap_version_dir_path() 
# from php_helpers/redcap_info.php
require __DIR__ . '/php_helpers/redcap_info.php';

$REDCAPDIR = get_installed_redcap_version_dir_path();
require $REDCAPDIR . '/Classes/Authentication.php';

class PasswordAndHashAndSalt{
    public $salt;
    public $hash;
}

function get_hash_salt_password($password,$user_id) : PasswordAndHashAndSalt
{
    $payload = new PasswordAndHashAndSalt();
    $payload->salt = Authentication::generatePasswordSalt();
    $payload->hash = Authentication::hashPassword($password,$payload->salt);
    return $payload;
}

# "INSERT INTO `redcap_auth` (`username`, `password`, `password_salt`) VALUES( '$username', '$password_hash', '$salt') ON DUPLICATE KEY UPDATE `password` = '$password_hash', `password_salt`='$salt';";
# $mysqli_con->query($sql_statement);

