<?php
/**
*
* @package phpBB3
* @version $Id$
* @copyright (c) 2005 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
* Minimum Requirement: PHP 4.3.3
*/

/**
*/

$phpbb_root_path = "./wp-content/plugins/tod-whitelist/phpbb/";

if (file_exists($phpbb_root_path . 'config.' . $phpEx))
{
	require($phpbb_root_path . 'config.' . $phpEx);
}

require($phpbb_root_path . 'includes/constants.' . $phpEx);
require($phpbb_root_path . 'includes/db/' . $dbms . '.' . $phpEx);

$db			= new $sql_db();

// Connect to DB
$db->sql_connect($dbhost, $dbuser, $dbpasswd, $dbname, $dbport, false, defined('PHPBB_DB_NEW_LINK') ? PHPBB_DB_NEW_LINK : false);

// We do not need this any longer, unset for safety purposes
unset($dbpasswd);

?>