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
abstract class t_text extends Type
{
	const InputWidth = 12;

	public static function getVCardProperty( $value, $PID = null )
	{
		return 'NOTE'. ($PID ? ";PID=$PID.1" : null) .':'. str_replace("\n", '\n', str_replace(',', '\,', $value));
	}

	// v4 OK
	public static function render( Instance $d, $fname, HTMLRendering $R )
	{
		$f = $d->{¶.$fname};
		if ($R->mode == $R::INPUT)
			return "<textarea style=\"height:10em\" class=\"input form-control\" $R->tabindex id=\"$fname\" name=\"$fname\" onchange=\"value=(value.replace(/\s+$/, '')).replace(/^\s+/, '')\" cols=\"48\" rows=\"8\">".
				htmlentities($d->$fname ? $d->$fname : $f['initial']) ."</textarea>";
		$value = htmlentities($d->$fname);
		if ($R->mode == $R::INLINE || $R->mode == $R::COLUMNAR)
			return "<div class=\"plaintext\" style=\"background-color:transparent;margin:0;padding:0;max-width:4in;overflow:hidden;white-space:nowrap\">$value</div>";
		return "<div class=\"plaintext\">$value</div>";
	}

	public static function mysql_ddl( array $fd, Database $db = null )
	{
		if (!$db)
			$db = $GLOBALS['root'];
		$comment = "COMMENT 'text: ". $db->dbEscapeString($fd['label']) ."'";
		if ($fd['required'] || $fd['notnull'])
			$notnull = "NOT NULL";
		$default = $fd['initial'] ? $db->dbEscapeString($fd['initial']) : ($notnull ? "''" : 'NULL');
		return "`$fd[name]` TEXT $notnull DEFAULT $default $comment";
	}
}

?>
