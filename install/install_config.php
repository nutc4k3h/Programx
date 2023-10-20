<?php
/***************************************
 * http://www.program-o.com
 * PROGRAM O
 * Version: 2.6.3
 * FILE: config/install_config.php
 * AUTHOR: Elizabeth Perreau and Dave Morton AND DAVE MORTON
 * DATE: FEB 01 2016
 * DETAILS: this file is a stripped down, "install" version of the config file,
 * and as such, only has the most minimal settings within it. The install script
 *  will create a full and complete config file during the install process
 ***************************************/

//------------------------------------------------------------------------
// Paths - only set this manually if the below doesnt work
//------------------------------------------------------------------------

chdir(dirname(__FILE__));
$thisConfigFolder = dirname(realpath(__FILE__)) . DIRECTORY_SEPARATOR;
$thisConfigParentFolder = preg_replace('~[/\\\\][^/\\\\]*[/\\\\]$~', DIRECTORY_SEPARATOR, $thisConfigFolder);
$baseURL = '//' . $_SERVER['SERVER_NAME'] . $_SERVER['SCRIPT_NAME'];
$docRoot = $_SERVER['DOCUMENT_ROOT'];

define("_BASE_PATH_", $thisConfigParentFolder);
$path_separator = DIRECTORY_SEPARATOR;

$thisFile = str_replace(_BASE_PATH_, '', $thisFile);
$thisFile = str_replace($path_separator, '/', $thisFile);
$baseURL = str_replace($thisFile, '', $baseURL);
define("_BASE_URL_", $baseURL);

//------------------------------------------------------------------------
// Define paths for include files
//------------------------------------------------------------------------

define('_ADMIN_PATH_', _BASE_PATH_ . "admin$path_separator");
define('_BOTCORE_PATH_', _BASE_PATH_ . "chatbot{$path_separator}core$path_separator");
define('_LIB_PATH_', _BASE_PATH_ . "library$path_separator");
define('_ADDONS_PATH_', _BASE_PATH_ . "chatbot{$path_separator}addons$path_separator");
define('_CONF_PATH_', _BASE_PATH_ . "config$path_separator");
define('_LOG_PATH_', _BASE_PATH_ . "logs$path_separator");
define('_DEBUG_PATH_', _BASE_PATH_ . "chatbot{$path_separator}debug$path_separator");
define('_INSTALL_PATH_', _BASE_PATH_ . "install$path_separator");
define('_CAPTCHA_PATH_', _ADMIN_PATH_ . "captcha-images$path_separator");
define('_UPLOAD_PATH_', _ADMIN_PATH_ . "uploads$path_separator");
define('IS_WINDOWS', (DIRECTORY_SEPARATOR == '/') ? false : true);
#define('_SESSION_PATH_', _ADMIN_PATH_ . '[session_dir]' . $path_separator);
# The above line is commented out till I can come up with a better implementation of session handling

//------------------------------------------------------------------------
// Define URL paths
//------------------------------------------------------------------------

define('_ADMIN_URL_', _BASE_URL_ . 'admin/');
define('_LIB_URL_', _BASE_URL_ . 'library/');
define('_LOG_URL_', _BASE_URL_ . 'logs/');
define('_DEBUG_URL_', _BASE_URL_ . 'chatbot/debug/');
define('_INSTALL_URL_', _BASE_URL_ . 'install/');

//------------------------------------------------------------------------
// Error reporting
//------------------------------------------------------------------------

$e_all = defined('E_DEPRECATED') ? E_ALL & ~E_DEPRECATED : E_ALL;
error_reporting($e_all);
ini_set('log_errors', true);
ini_set('error_log', _LOG_PATH_ . 'install.error.log');
ini_set('html_errors', false);
ini_set('display_errors', false);

//------------------------------------------------------------------------
// Default chatbot values
//------------------------------------------------------------------------

$pattern = "RANDOM PICKUP LINE";
$error_response = "No AIML category found. This is a Default Response.";
$conversation_lines = '1';
$remember_up_to = '10';

//------------------------------------------------------------------------
// Configure mbstring parameters
//------------------------------------------------------------------------

define('IS_MB_ENABLED', (function_exists('mb_internal_encoding')) ? true : false);

if (!IS_MB_ENABLED) {
    $no_unicode_message = "<p class=\"red\">Warning! Unicode Support is not available on this server. Non-English languages will not display properly. Please ask your hosting provider to enable the PHP mbstring extension to correct this.</p>\n";
}