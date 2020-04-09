<?php
$cfg['PmaAbsoluteUri'] = "http://localhost:$_ENV[APACHE_PORT]";

$i = 1;
/* Authentication type */
$cfg['Servers'][$i]['host'] = 'localhost';
$cfg['Servers'][$i]['connect_type'] = 'socket';
$cfg['Servers'][$i]['socket'] = "$_ENV[APACHE_HOME]/mysqld.sock";
$cfg['Servers'][$i]['compress'] = false;
$cfg['Servers'][$i]['extension'] = 'mysqli';
$cfg['Servers'][$i]['auth_type'] = 'config';
$cfg['Servers'][$i]['user'] = "$_ENV[ACCOUNT]";
$cfg['Servers'][$i]['password'] = '';
$cfg['Servers'][$i]['nopassword'] = true;
$cfg['Servers'][$i]['AllowNoPassword'] = true;

/* Configuration Storage database and tables */
$cfg['Servers'][$i]['pmadb'] = 'phpmyadmin';
foreach (array('bookmarktable','relation','table_info','table_coords','pdf_pages','column_info','history','table_uiprefs','tracking','designer_coords','userconfig','recent') as $table)
	$cfg['Servers'][$i][$table] = $table;
$cfg['Servers'][$i]['controluser'] = "$_ENV[ACCOUNT]";
$cfg['Servers'][$i]['controlpass'] = '';

$cfg['UploadDir'] = "$_ENV[ACCOUNT_HOME]/static/common/mysql";
$cfg['SaveDir'] = "$cfg[UploadDir]";

// User interface settings
$cfg['ShowAll'] = true;
$cfg['MaxRows'] = 50;
$cfg['ProtectBinary'] = 'all';
$cfg['DefaultLang'] = 'en';
$cfg['QueryHistoryDB'] = true;
$cfg['QueryHistoryMax'] = 100;

/* vim: set expandtab sw=4 ts=4 sts=4: */
