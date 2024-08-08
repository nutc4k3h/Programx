<?php
/***************************************
* http://www.program-o.com
* PROGRAM O
* Version: 2.6.7
* FILE: install_programo.php
* AUTHOR: Elizabeth Perreau and Dave Morton
* DATE: FEB 01 2016
* DETAILS: Program O's Automatic install script
***************************************/

session_name('PGO_install');
session_start();
require_once ('install_config.php');
require_once (_LIB_PATH_ . 'template.class.php');
require_once (_LIB_PATH_ . 'misc_functions.php');
require_once (_LIB_PATH_ . 'error_functions.php');
require_once (_LIB_PATH_ . 'PDO_functions.php');

ini_set("display_errors", 0);
ini_set("log_errors", true);
ini_set("error_log", _LOG_PATH_ . "install.error.log");

define('PHP_SELF', $_SERVER['SCRIPT_NAME']); # This is more secure than $_SERVER['PHP_SELF'], and returns more or less the same thing
$input_vars = clean_inputs();

$clearDB = false;
if (isset($input_vars['clearDB']))
{
    $clearDB = true;
    $_SESSION['clearDB'] = $clearDB;
}


# Test for required version and extensions
$myPHP_Version = phpversion();
//$myPHP_Version = '5.2.9'; # debugging/testing - must be commented out for functionality.
$pdoSupport = (class_exists('PDO'));
$php_min_version = '5.3.0';
$version_compare = version_compare($myPHP_Version, $php_min_version);

$no_unicode_message = (extension_loaded('mbstring')) ? '' : "<p class=\"red bold\">Warning! Unicode Support is not available on this server. Non-English languages will not display properly. Please ask your hosting provider to enable the PHP mbstring extension to correct this.</p>\n";
$no_zip_message = (extension_loaded('zip')) ? '' : "<p class=\"red bold\">Warning! The 'zip' PHP extension is not available. As a result, the upload and download of AIML files will be limited to individual files. Please ask your hosting provider to enable the PHP zip extension to correct this.</p>\n";
$errorMessage = (!empty ($_SESSION['errorMessage'])) ? $_SESSION['errorMessage'] : '';
$errorMessage .= $no_unicode_message;
$errorMessage .= $no_zip_message;

$pdoExtensionsArray = array(
    'PDO_CUBRID',
    'PDO_DBLIB',
    'PDO_FIREBIRD',
    'PDO_IBM',
    'PDO_INFORMIX',
    'PDO_MYSQL',
    'PDO_SQLSRV',
    'PDO_OCI',
    'PDO_ODBC',
    'PDO_PGSQL',
    'PDO_SQLITE',
    'PDO_4D'
);
$recommendedExtensionsArray = array(
    'curl',
    'zip',
    'mbstring',
);


$template = new Template('install.tpl.htm');

// check/set/create the sessions folder
$dirArray = glob(_ADMIN_PATH_ . "ses_*", GLOB_ONLYDIR);
$session_dir = (empty($dirArray)) ? create_session_dirname() : basename($dirArray[0]);
$dupPS = "{$path_separator}{$path_separator}";
$session_dir = str_replace($dupPS, $path_separator, $session_dir); // remove double path separators when necessary
$session_dir = rtrim($session_dir, PATH_SEPARATOR);
$full_session_path = _ADMIN_PATH_ . $session_dir;

define('_SESSION_PATH_', $full_session_path);

$writeCheckArray = array('config' => _CONF_PATH_, 'debug' => _DEBUG_PATH_, 'logs' => _LOG_PATH_, 'session' => _SESSION_PATH_);
$errFlag = false;

foreach ($writeCheckArray as $key => $folder)
{
    if (!is_writable($folder))
    {
        $test = file_put_contents("{$folder}test.txt", $key);
        if (false === $test)
        {
            $dirExists = (file_exists($folder)) ? 'true' : 'false';
            $permissions = fileperms($folder);
            $txtPerms = showPerms($permissions);
            error_log("The {$key} folder ({$folder}) is not writable. Folder exists?: $dirExists. Permissions: $txtPerms." . PHP_EOL, 3, '../logs/install.log');

            $errFlag = true;
            $errorMessage .= "<p class=\"red bold\">The $key folder cannot be written to, or does not exist. Please correct this before you continue.</p>";
        }
        else
        {
            unlink("{$folder}test.txt");
        }
    }
}

$additionalInfo = <<<endInfo
  <p>
    This is usually a permissions issue, and most often occurs with Linux-based systems. Check
    file/folder permissions for the following directories:
    <ul>
      <li>The base install folder (where you unzipped or uploaded the script to)</li>
      <li>admin</li>
      <li>config</li>
      <li>chatbot/debug</li>
    </ul>
    Permissions for these folders should be 0755. If they are not, then you need to change that. If you
    have trouble with this, or have questions, please report the issue on
    <a href="https://github.com/Program-O/Program-O/issues">our GitHub page</a>.
  </p>
endInfo;

if ($errFlag) {
    $errorMessage .= $additionalInfo;
}

$myHost = $_SERVER['SERVER_NAME'];
chdir(dirname(realpath(__FILE__)));
$page = (isset ($input_vars['page'])) ? $input_vars['page'] : 0;
$action = (isset ($input_vars['action'])) ? $input_vars['action'] : '';
$message = '';

if (!empty ($action)) {
    $message = $action($page);
}

$content = $template->getSection('Header');
$content .= $template->getSection('Container');
$content .= $template->getSection('Footer');
$content .= $template->getSection("jQuery$page");
$notes = $template->getSection(ucwords("Page $page Notes"));
$submitButton = $template->getSection('SubmitButton');
switch ((int) $page)
{
    case 0:
        $pvpf = ($version_compare >= 0) ? 'true' : 'false';
        $main = $template->getSection('Checklist');
        $liTemplate = '                            <li class="[oe]">PDO [ext] extension enabled?: <span class="ext_[tf] floatRight">[tf]</span></li>' . PHP_EOL;
        $pdo_reqs = '';
        $oddEven = 0;
        $reqs_met = false;
        foreach ($pdoExtensionsArray as $ext)
        {
            $oeClass = ($oddEven % 2 === 0) ? 'odd' : 'even';
            $tf = (extension_loaded($ext)) ? 'true' : 'false';
            $curLi = $liTemplate;
            $curLi = str_replace('[ext]', $ext, $curLi);
            $curLi = str_replace('[oe]', $oeClass, $curLi);
            $curLi = str_replace('[tf]', $tf, $curLi);
            if ($tf === 'false') $curLi = '';
            $pdo_reqs .= $curLi;
            if ($tf !== 'false') $oddEven++;
        }
        $reqs_not_met = '';
        if (empty($pdo_reqs)) # || true # Again, debugging/testing code, to be commented out for actual use.
        {
            $pdo_reqs = $liTemplate;
            $pdo_reqs = str_replace('[oe]', 'even', $pdo_reqs);
            $pdo_reqs = str_replace('[ext]', '', $pdo_reqs);
            $pdo_reqs = str_replace('[tf]', 'false', $pdo_reqs);
            $reqs_not_met .= 'There are no PDO extensions available, so the install process cannot continue.<br>';
        }
        elseif ($pvpf == 'false')
        {
            $reqs_not_met .= "Your PHP version ({$myPHP_Version}) is older than the minimum required version of {$php_min_version}, so the install process cannot continue.<br>";
        }
        else $reqs_met = true;
        $main = str_replace('[pdo_reqs]', rtrim($pdo_reqs), $main);
        $rec_exts = '';
        $oddEven = 0;
        foreach ($recommendedExtensionsArray as $ext)
        {
            $oeClass = ($oddEven % 2 === 0) ? 'odd' : 'even';
            $curLi = $liTemplate;
            $tf = (extension_loaded($ext)) ? 'true' : 'false';
            $curLi = str_replace('[ext]', $ext, $curLi);
            $curLi = str_replace('[oe]', $oeClass, $curLi);
            $curLi = str_replace('[tf]', $tf, $curLi);
            $rec_exts .= $curLi;
            $oddEven++;
        }
        $main = str_replace('[rec_exts]', rtrim($rec_exts), $main);
        $main = str_replace('[pgo_version]', VERSION, $main);
        $main = str_replace('[pvpf]', $pvpf, $main);
        $main = str_replace('[version]', $myPHP_Version, $main);
        $continueLink = ($reqs_met) ? $template->getSection('Page0ContinueForm') :'<div class="center bold red">' .  $reqs_not_met . 'Please correct the items above in order to continue.</div>' . PHP_EOL;
        $main .= $continueLink;
        $main = str_replace('[blank]', '', $main);
        $main .= $no_unicode_message;
        $main .= $no_zip_message;
        break;
    case 1:
        $main = $template->getSection('InstallForm');
        break;
    default: $main = $message;
}
$tmpSearchArray = array();
$content .= "\n    </body>\n</html>";

$content = str_replace('[mainPanel]', $main, $content);
$content = str_replace('[http_host]', $myHost, $content);
$content = str_replace('[error_response]', $error_response, $content);
$content = str_replace('[notes]', $notes, $content);
$content = str_replace('[PHP_SELF]', PHP_SELF, $content);
$content = str_replace('[errorMessage]', $errorMessage, $content);
$content = str_replace('[cr6]', "\n ", $content);
$content = str_replace('[cr4]', "\n ", $content);
$content = str_replace("\r\n", "\n", $content);
$content = str_replace("\n\n", "\n", $content);
$content = str_replace('[admin_url]', _ADMIN_URL_, $content);
$content = str_replace('[mainPanel]', $main, $content);
$content = str_replace('[blank]', '', $content);

exit($content);

/**
 * Function Save
 *
 * @return string
 */
function Save()
{
    global $template, $error_response, $session_dir;

    // Do we want to start with a fresh, empty database?
    if (isset($_SESSION['clearDB']))
    {
        $clearDB = true;
        unset($_SESSION['clearDB']);
    }
    // initialize some variables and set some defaults
    $tagSearch = array();
    $varReplace = array();
    $pattern = "RANDOM PICKUP LINE";
    $error_response = "No AIML category found. This is a Default Response.";
    $conversation_lines = '1';
    $remember_up_to = '10';
    $_SESSION['errorMessage'] = '';


    $configContents = file_get_contents(_INSTALL_PATH_ . 'config.template.php');
    $configContents = str_replace('[session_dir]', $session_dir, $configContents);
    clearstatcache();

    // First off, create the sessions folder and set permissions if it doesn't exist
    if (!file_exists(_SESSION_PATH_))
    {
        mkdir(_SESSION_PATH_, 0755);

        // Place an empty index file in the sessions folder to prevent direct access to the folder from a web browser
        file_put_contents(_SESSION_PATH_ . 'index.html', '');
    }

    // Write the config file from all of the posted form values

    // Get the posted values, sanitize them and put them into an array
    $myPostVars = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);

    // Sort the array - not strictly necessary, but we're doing it anyway
    ksort($myPostVars);

    // Create the SEARCH and REPLACE arrays
    foreach ($myPostVars as $key => $value)
    {
        $tagSearch[] = "[$key]";
        $varReplace[] = $value;
    }

    // Replace all [placeholder] tags with the posted values
    $configContents = str_replace($tagSearch, $varReplace, $configContents);

    // Write the new config file
    $saveFile = file_put_contents(_CONF_PATH_ . 'global_config.php', $configContents);

    // Now, update the data to the database, starting with making sure the tables are installed
    $dbh = $myPostVars['dbh'];
    $dbn = $myPostVars['dbn'];
    $dbu = $myPostVars['dbu'];
    $dbp = $myPostVars['dbp'];

    // Open the database to begin storing stuff
    $dbConn = db_open();

    // Check to see if the database is empty, or if the user checked the "clear DB" option
    $row = db_fetch('show tables');
    if (empty ($row) || true === $clearDB)
    {
        $sqlArray = file('new.sql', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($sqlArray as $sql)
        {
            try {
                $insertSuccess = db_write($sql, null, __FILE__, __FUNCTION__, __LINE__, false);
                if (false === $insertSuccess){
                    throw new Exception('SQL operation failed!');
                }
            }
            catch(Exception $e)
            {
                $words = explode(' ', $sql);
                switch (strtoupper($words[0]))
                {
                    case 'DROP':
                        $table = trim($words[4], '`;');
                        break;
                    case 'CREATE':
                        $table = trim($words[5], '`');
                        break;
                    case 'ALTER':
                        $table = trim($words[2], '`');
                        break;
                    default:
                        $words[0] .= ' data into';
                        $table = trim($words[2], '`');
                }
                $errMsg = "Error while attempting to {$words[0]} the {$table} table. SQL:\n$sql\n-----------------------------------------------\n";
                error_log($errMsg, 3, _LOG_PATH_ . 'install.sql.error.log');
            }

        }
    }
    else
    {
        // Let's make sure that the srai lookup table exists
        try {
            /** @noinspection SqlNoDataSourceInspection */
            $sql = 'SELECT bot_id FROM srai_lookup;';
            $result = db_fetchAll($sql);
        }
        catch(Exception $e) {
            try {
                /** @noinspection SqlDialectInspection */
                /** @noinspection SqlNoDataSourceInspection */
                $sql = "DROP TABLE IF EXISTS `srai_lookup`; CREATE TABLE IF NOT EXISTS `srai_lookup` (`id` int(11) NOT NULL AUTO_INCREMENT, `bot_id` int(11) NOT NULL, `pattern` text NOT NULL, `template_id` int(11) NOT NULL, PRIMARY KEY (`id`), KEY `pattern` (`pattern`(64)) COMMENT 'Search against this for performance boost') ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Contains previously stored SRAI calls' AUTO_INCREMENT=1;";
                $affectedRows = db_write($sql, null, __FILE__, __FUNCTION__, __LINE__, false);
            }
            catch(Exception $e) {
              $errorMessage .= 'Could not add SRAI lookup table! Error is: ' . $e->getMessage();
            }
        }
    }

    /** @noinspection SqlDialectInspection */
    /** @noinspection SqlNoDataSourceInspection */
    $sql = 'SELECT `error_response` FROM `bots` WHERE 1 limit 1';
    $row = db_fetch($sql);
    $error_response = $row['error_response'];

    /** @noinspection SqlDialectInspection */
    /** @noinspection SqlNoDataSourceInspection */
    $sql = 'SELECT `bot_id` FROM `bots`;';
    $result = db_fetchAll($sql);

    if (count($result) == 0)
    {
        /** @noinspection SqlDialectInspection */
        /** @noinspection SqlNoDataSourceInspection */
        $sql_template = "
            INSERT IGNORE INTO `bots` (`bot_id`, `bot_name`, `bot_desc`, `bot_active`, `bot_parent_id`, `format`, `save_state`, `conversation_lines`, `remember_up_to`, `debugemail`, `debugshow`, `debugmode`, `error_response`, `default_aiml_pattern`)
            VALUES ([default_bot_id], '[bot_name]', '[bot_desc]', '[bot_active]', '[bot_parent_id]', '[format]', '[save_state]',
            '$conversation_lines', '$remember_up_to', '[debugemail]', '[debugshow]', '[debugmode]', '$error_response', '$pattern');";

        $bot_id = 1;
        $sql = str_replace('[default_bot_id]', $bot_id, $sql_template);
        $sql = str_replace('[bot_name]', $myPostVars['bot_name'], $sql);
        $sql = str_replace('[bot_desc]', $myPostVars['bot_desc'], $sql);
        $sql = str_replace('[bot_active]', $myPostVars['bot_active'], $sql);
        $sql = str_replace('[bot_parent_id]', 1, $sql);
        $sql = str_replace('[format]', $myPostVars['format'], $sql);

        // "Use PHP from DB setting
        // "Update PHP in DB setting
        $sql = str_replace('[save_state]', $myPostVars['save_state'], $sql);
        $sql = str_replace('[conversation_lines]', $conversation_lines, $sql);
        $sql = str_replace('[remember_up_to]', $remember_up_to, $sql);
        $sql = str_replace('[debugemail]', $myPostVars['debugemail'], $sql);
        $sql = str_replace('[debugshow]', $myPostVars['debug_level'], $sql);
        $sql = str_replace('[debugmode]', $myPostVars['debug_mode'], $sql);
        $sql = str_replace('[error_response]', $error_response, $sql);
        $sql = str_replace('[aiml_pattern]', $pattern, $sql);

        try
        {
            $affectedRows = db_write($sql, null, __FILE__, __FUNCTION__, __LINE__, false);
            $errorMessage .= ($affectedRows > 0) ? '' : ' Could not create new bot!';
        }
        catch(Exception $e) {
            $errorMessage .= $e->getMessage();
        }
    }

    $cur_ip = $_SERVER['REMOTE_ADDR'];
    $encrypted_adm_dbp = md5($myPostVars['adm_dbp']);
    $adm_dbu = $myPostVars['adm_dbu'];

    /** @noinspection SqlDialectInspection */
    /** @noinspection SqlNoDataSourceInspection */
    $sql = "SELECT id FROM `myprogramo` WHERE `user_name` = '$adm_dbu' AND `password` = '$encrypted_adm_dbp';";
    $result = db_fetchAll($sql);

    if (count($result) == 0)
    {
        /** @noinspection SqlDialectInspection */
        /** @noinspection SqlNoDataSourceInspection */
        $sql = "INSERT ignore INTO `myprogramo` (`id`, `user_name`, `password`, `last_ip`) VALUES(null, '$adm_dbu', '$encrypted_adm_dbp', '$cur_ip');";

        try {
            $affectedRows = db_write($sql, null, __FILE__, __FUNCTION__, __LINE__, false);
            $errorMessage .= ($affectedRows > 0) ? '' : ' Could not create new Admin!';
        }
        catch(Exception $e) {
            $errorMessage .= $e->getMessage();
        }
    }

    if (empty($errorMessage)) {
        $out = $template->getSection('InstallComplete');
    }
    else {
        $out = $template->getSection('InstallError');
    }

    return $out . $errorMessage;
}

/*
 * function create_session_dirname
 * Creates a cryptographically secure, random folder name for storing session files
 * return (string) $out
 */

function create_session_dirname()
{
    global $path_separator;
    $randBytes = openssl_random_pseudo_bytes(12);
    $suffix = bin2hex($randBytes);
    $out = "ses_$suffix$path_separator";
    return $out;
}

function showPerms ($permissions)
{
    switch ($permissions & 0xF000) {
        case 0xC000: // socket
            $info = 's';
            break;
        case 0xA000: // symbolic link
            $info = 'l';
            break;
        case 0x8000: // regular
            $info = 'r';
            break;
        case 0x6000: // block special
            $info = 'b';
            break;
        case 0x4000: // directory
            $info = 'd';
            break;
        case 0x2000: // character special
            $info = 'c';
            break;
        case 0x1000: // FIFO pipe
            $info = 'p';
            break;
        default: // unknown
            $info = 'u';
    }

// Owner
    $info .= (($permissions & 0x0100) ? 'r' : '-');
    $info .= (($permissions & 0x0080) ? 'w' : '-');
    $info .= (($permissions & 0x0040) ?
                (($permissions & 0x0800) ? 's' : 'x' ) :
                (($permissions & 0x0800) ? 'S' : '-'));

// Group
    $info .= (($permissions & 0x0020) ? 'r' : '-');
    $info .= (($permissions & 0x0010) ? 'w' : '-');
    $info .= (($permissions & 0x0008) ?
                (($permissions & 0x0400) ? 's' : 'x' ) :
                (($permissions & 0x0400) ? 'S' : '-'));

// World
    $info .= (($permissions & 0x0004) ? 'r' : '-');
    $info .= (($permissions & 0x0002) ? 'w' : '-');
    $info .= (($permissions & 0x0001) ?
                (($permissions & 0x0200) ? 't' : 'x' ) :
                (($permissions & 0x0200) ? 'T' : '-'));

    return $info;
}

