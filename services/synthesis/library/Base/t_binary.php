<?php
/**
* The methods in this abstract subclass are all described in the base class Type, so don't expect a lot of comments about them here.
*
* All original code.
* @package Synthesis/Base
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
abstract class t_binary extends t_text
{
	// v4 OK
	public static function render( Instance $d, $fname, HTMLRendering $R )
	{
		return "<div class=\"binary\">(binary file)</div>";
	}

	public static function mysql_ddl( array $fd, Database $db = null )
	{
		if (!$db)
			$db = $GLOBALS['root'];
		$comment = "COMMENT 'binary: ". $db->dbEscapeString($fd['label']) ."'";
		if ($fd['required'] || $fd['notnull'])
			$notnull = "NOT NULL";
		$default = $fd['initial'] ? $db->dbEscapeString($fd['initial']) : ($notnull ? "''" : 'NULL');
		return "`$fd[name]` LONGBLOB $notnull DEFAULT $default $comment";
	}
}
