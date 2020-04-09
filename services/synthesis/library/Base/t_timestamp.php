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
abstract class t_timestamp extends Type
{
	// v4 OK
	public static function render( Instance $d, $fn, HTMLRendering $R )
	{
		$fd = $d->getFieldDef($fn);
		if ($R->mode == $R::INPUT && !$d->$fn)
			return $fd['unsetRendering'] ? $fd['unsetRendering'] : '(&nbsp;Automatic&nbsp;)';
		return t_date::render($d, $fn, $R, '*readonly');
	}

	public static function put_db( $value )
	{
		return isset($value) ? "CURRENT_TIMESTAMP" : "NULL";
	}

	public static function mysql_ddl( array $fd, Database $db = null )
	{
		if (!$db)
			$db = $GLOBALS['root'];
		$comment = "COMMENT 'timestamp: ". $db->dbEscapeString($fd['label']) ."'";
		if ($fd['onupdate'])
			$update = "ON UPDATE CURRENT_TIMESTAMP";
		return "`$fd[name]` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP $update $comment";
	}

}

?>
