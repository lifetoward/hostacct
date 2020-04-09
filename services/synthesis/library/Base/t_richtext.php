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
abstract class t_richtext extends Type
{
	const InputWidth = 12;

	public static function getVCardProperty( $value, $PID = null )
	{
		return t_text::getVCardProperty(strip_tags(preg_replace('#(<p |<br[/ >])#', "\n\1", $value)), $PID);
	}

	public static function render( Instance $d, $fname, HTMLRendering $R, $format = null )
	{
		$f = $d->getFieldDef($fname);

		if ($R->mode == $R::INPUT && !($f['readonly'] || $R->readonly || $format == '*readonly')) {
			$R->linkScript("{$R->context->weblib}/tinymce/tinymce.min.js");
			$content = $d->$fname ? $d->$fname : $f['initial'];
			$R->addReadyScript(str_replace("%FIELDID%", "$R->idprefix$fname", self::NewRTEditorInit));
			return "<div $R->tabindex name=\"$fname\" id=\"$R->idprefix$fname\" style=\"border-radius:5px;border:1px solid gray;padding:8pt;max-height:3in;overflow:auto;display:block;width:100%;\">$content</div>";
		}

		if (in_array($R->mode, array($R::VERBOSE, $R::INPUT))) // INPUT here just means it's readonly per logic above
			return '<div class="value">'. $d->$fname .'</div>';

		return "<div class=\"truncatext\">". preg_replace('|<[^>]*>|', " ", $d->$fname) ."</div>";
	}

	const NewRTEditorInit = <<<'jscript'
tinymce.init({selector: "#%FIELDID%",inline:true,
    plugins: ["advlist autolink lists link image charmap print preview anchor","searchreplace visualblocks code fullscreen","insertdatetime media table contextmenu paste"],
    toolbar: "insertfile undo redo | styleselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image"
});
jscript;

	public static function mysql_ddl( array $fd, Database $db = null )
	{
		if (!$db)
			$db = $GLOBALS['root'];
		$comment = "COMMENT 'richtext: ". $db->dbEscapeString($fd['label']) ."'";
		if ($fd['required'] || $fd['notnull'])
			$notnull = "NOT NULL";
		$default = $fd['initial'] ? $db->dbEscapeString($fd['initial']) : ($notnull ? "''" : 'NULL');
		return "`$fd[name]` TEXT $notnull DEFAULT $default $comment";
	}

}
