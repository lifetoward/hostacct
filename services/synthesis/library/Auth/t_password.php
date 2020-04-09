<?php
/**
* The password type is special in several ways:
* 	- When writing to the database it must use the "PASSWORD()" encoding, although when reading it we get the result of that function and can't use it otherwise.
*	- Obviously on input it is concealed, however, when input for password-change reasons, it needs to request it twice and validate a match to allow submission.
*		To note this difference we check for the format "*login". Without this format flag, if the context mode is INPUT then we render in the double "update password" form.
*	-
*
* All original code.
* @package Synthesis/Authentication
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2007-2014 Lifetoward LLC
* @license proprietary
*/
abstract class t_password extends Type
{
	const STANDARD_PATTERN = '^.{6,32}$', SIZE = 20;

	public static function render( Instance $d, $fn, HTMLRendering $R, $format = null )
	{
		$fd = $d->getFieldDef($fn);
		$size = is_numeric($fd['size']) ? $fd['size'] : self::SIZE;
		$autofill = isset($fd['autofill']) ? 'on' : ($fd['autofill'] ? 'on' : 'off');

		if ($R->mode == $R::INPUT && $format == '*login') // we expect only login forms will ever supply this overriding input format
			return "	<input type=\"password\" class=\"form-control\" autocomplete=\"$autofill\" size=\"$size\" name=\"$fn\" id=\"$R->idprefix$fn\" placeholder=\"{$d->{¬.$fn}}\"/>";

		// In standard INPUT mode we render for update or create, meaning we display two password fields which must match to allow submission.
		if ($R->mode == $R::INPUT) {
			$pattern = $fd['pattern'] ? $fd['pattern'] : self::STANDARD_PATTERN;
			$cid = "$R->idprefix$fn";
			$reqTest = $d->original($fn) ? "v.length==0" : 'false';
			$R->addScript(<<<js
function checkpwds(\$c,p){
	v=\$c.val();
	if (($reqTest||v.match(p))&&\$('#_'+\$c.attr('id')).val()==v)
		\$c.parents('.form-group').removeClass('has-error');
	else
		\$c.parents('.form-group').addClass('has-error');}
js
				, "t_password validator");
			$R->addReadyScript(<<<js
\$(\$('#$cid').on('change',function(e){checkpwds(\$('#$cid'),/$pattern/)})
	.prop('form')).on('submit',function(e){if(\$('#$cid').parents('.form-group').hasClass('has-error'))e.preventDefault()});
\$('#_$cid').on('change',function(e){checkpwds(\$('#$cid'),/$pattern/)});
checkpwds(\$('#$cid'),/$pattern/);
js
				);
			if ($d->latent($fn))
				$initval = htmlentities("{$d->$fn}");
			return <<<html
	<input class="form-control" type="password" $R->tabindex autocomplete="$autofill" size="$size" id="$R->idprefix$fn" value="$initval" placeholder="{$d->{¬.$fn}}" name="$fn" />
	<input class="form-control" type="password" $R->tabindex autocomplete="$autofill" size="$size" id="_$R->idprefix$fn" value="" placeholder="Confirm (retype) password"/>
html;
		}

		// In other modes we just render, but that's always trivial because we never render system passwords.
		return "( secret )";
	}

	public final static function accept_db(  )
	{
		return "( secret )";
	}

	public final static function put_db( $value, Database $db = null )
	{
		if (!$db)
			$db = $GLOBALS['root'];
		return "PASSWORD('". $db->dbEscapeString($value) ."')";
	}
}
