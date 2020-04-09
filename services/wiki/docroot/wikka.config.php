<?php
/**
 * WikkaWiki configuration file 
 * 
 * This template was created for the Synthetic hosting model to work with env.sh.
 * Do not manually change wakka_version if you wish to keep your engine up-to-date.
 * Documentation is available at: http://docs.wikkawiki.org/ConfigurationOptions
 */

/** 
 * Begin Synthetic Web Apps  multi-site, SSL, and proxy handling logic.
 * We learn the database prefix (site name) from $_SERVER[HTTP_GATEWAY_SITENAME] which is a header set by the proxy.
 * From this it follows that the URL, locations, and media paths are all determined separately
 * While themes and plugins are shared. Note that the user databases remain separate, and that's
 * the most likely thing to be developed-around someday.
 */
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_X_FORWARDED_HOST'];
if ($_SERVER['HTTP_SECURE_REQUEST'] == 'true') {
        $_SERVER['REQUEST_SCHEME'] = "https";
        $_SERVER['HTTP_X_FORWARDED_PORT'] = 443;
        $_SERVER['HTTPS'] = 'on';
} else {
        $_SERVER['REQUEST_SCHEME'] = "http";
        $_SERVER['HTTP_X_FORWARDED_PORT'] = 80;
        $_SERVER['HTTPS'] = null;
}
$SiteName = "$_SERVER[HTTP_GATEWAY_SITENAME]";
$WikiRoot = $_ENV['WikiRoot'] ? : 'HomePage';
$WikiReqBase = $_ENV['WikiReqBase'] ? preg_replace('|^/?(.*)/?$|', '\\1', $_ENV['WikiReqBase']) : 'wiki';
$WikiTitle = $_ENV['WikiTitle'] ? : "$SiteName: Wikka Wiki";

$wakkaConfig = array(
	'mysql_host' => "localhost:$_ENV[ACCOUNT_HOME]/mysql/mysqld.sock",
	'mysql_database' => 'wiki',
	'mysql_user' => $_ENV['ACCOUNT'],
	'table_prefix' => "${SiteName}_",
	'root_page' => "$WikiRoot",
	'wakka_name' => "$WikiTitle",
	'base_url' => "/$WikiReqBase/",
	'rewrite_mode' => '1',
	'wiki_suffix' => "@$SiteName",
	'enable_user_host_lookup' => '1',
	'action_path' => 'plugins/actions,actions',
	'handler_path' => 'plugins/handlers,handlers',
	'gui_editor' => '1',
	'theme' => 'light',
	'wikka_formatter_path' => 'plugins/formatters,formatters',
	'wikka_highlighters_path' => 'formatters',
	'geshi_path' => '3rdparty/plugins/geshi',
	'geshi_languages_path' => '3rdparty/plugins/geshi/geshi',
	'wikka_template_path' => 'plugins/templates,templates',
	'safehtml_path' => '3rdparty/core/safehtml',
	'referrers_purge_time' => '30',
	'pages_purge_time' => '0',
	'xml_recent_changes' => '10',
	'hide_comments' => '0',
	'require_edit_note' => '0',
	'anony_delete_own_comments' => '1',
	'public_sysinfo' => '0',
	'double_doublequote_html' => 'safe',
	'sql_debugging' => '0',
	'admin_users' => 'WikiAdmin',
	'admin_email' => "wiki.admin@$_ENV[DOMAIN]",
	'upload_path' => "$_ENV[ACCOUNT_HOME]/static/$SiteName/wiki",
	'mime_types' => 'mime_types.txt',
	'geshi_header' => 'div',
	'geshi_line_numbers' => '1',
	'geshi_tab_width' => '4',
	'grabcode_button' => '1',
	'wikiping_server' => '',
	'default_write_acl' => '+',
	'default_read_acl' => '*',
	'default_comment_acl' => '+',
	'allow_user_registration' => '0',
	'enable_version_check' => '1',
	'version_check_interval' => '1h',
	'wakka_version' => '1.2',
	'mysql_password' => '',
	'meta_keywords' => '',
	'meta_description' => '',
	'stylesheet_hash' => 'c1079');
?>
