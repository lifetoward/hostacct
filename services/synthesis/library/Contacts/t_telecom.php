<?php
/**
* This is a telecom address, as is used in the PSTN (Public Switched Telephone Network) addressing scheme.
* In early versions it was assumed constrained to the +1 IDD (International Direct Dialing) region.
* However, we can extend it to make it smart enough to handle any address in the known world.
*
* All original code.
* @package Synthesis/Contacts
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
abstract class t_telecom extends t_string
{
	public static function getVCardProperty( $value, $modes = 'voice', $PID = null, $pref = false )
	{
		foreach ((array)$modes as $mode)
			$types .= "TYPE=$mode;";
		return "TEL;". ($PID ? "PID=$PID.1;" : null) .'VALUE=text;'. ($pref ? "PREF=$pref;TYPE=pref;" : null) . $types .":$value";
		/*
		We've found that Mac's Address Book doesn't really support v4.0, so we're dumbing down to 3.0 in rfc2426 (https://www.ietf.org/rfc/rfc2426.txt).
		According to vCard's RFC6350, we SHOULD be using URI values for telecom addresses. HOWEVER, Apple Contacts (at least) hates it, 
		and it appears there is inconsistency between RFC6350 and RFC3966 which tells how to render a telecom URI.
		For this reason, we are assuming that RFC3966 is for some reason not well adopted, at least for vCards and it's not worth even including as an 
		alternate because I'm ending up with both forms (and worse) when I include both even with matching PIDs. 
		This of course represents noncompliance with vCard RFC6350, mais c'est la vie.
		
		"TEL;". ($PID ? "PID=$PID.1;" : null) .'VALUE=uri;'. ($pref ? "PREF=$pref;" : null) .'TYPE="'. implode(',', (array)$modes) .'":'. t_telecom::asURI($value)
		*/
	}

	/**
	* Here we render a telecom address as a URI with scheme "tel" according to RFC3966 (http://tools.ietf.org/html/rfc3966)
	* Note that right now we only handle global dialing region 1.
	* @param string $stdfmt A telecom address in our own standard format.
	* @param string A telecom address URI including the scheme.
	*/
	public static function asURI( $stdfmt )
	{
		if (!preg_match('|^\((\d{3})\) (\d{3})-(\d{4})( x(\d{1,8}))?$|', $stdfmt, $m))
			return "tel:$stdfmt";
		list($whole,$area,$prefix,$main,$erend,$ext) = $m;
		return "tel:+1-$area-$prefix-$main". ($ext ? ";extension=$ext" : null);
	}

	public static function accept( $value, array $fd )
	{
		if ($value && !preg_match("/^\(\d{3}\) \d{3}-\d{4}( x\d{1,8})?$/", $value))
			throw new BadFieldValueX($fd, "Invalid phone number submitted.");
		return $value;
	}

	public static function render( Instance $d , $fname, HTMLRendering $R = null )
	{
		$num = htmlentities($d->$fname);
		$cid = "$R->idprefix$fname";
		if ($R->mode != $R::INPUT)
			return $d->$fname ? '<a href="'. static::asURI($d->$fname) .'" id="'. $cid .'">'. str_replace(' ','&nbsp;',$num) .'</a>' : null;

		$R->addScript(self::PhoneHandler, "t_telecom");
		$f = $d->getFieldDef($fname);
		$nullok = $f['required'] ? 0 : 1;
		$R->addReadyScript("valid_phone(\$('#$cid')[0],'$fname',$nullok);");
		$R->addReadyScript("\$(\$('#$cid').prop('form')).on('submit',function(e){if(\$('#$cid').parents('.form-group').hasClass('has-error'))e.preventDefault()});");
		return <<<html
	<input $R->tabindex class="input form-control" id="$cid" title="Use 'x1234' format for extensions." onchange="valid_phone(this,'$f[name]',$nullok)" type="text" size="22" name="$fname" value="$num" />
html;
	}

	const PhoneHandler = <<<'jscript'
function valid_phone(c,f,n) {
	c.value=(c.value.replace(/\s+$/, '')).replace(/^\s+/, '');
	var ext="",ok,parts;
	if(!c.value.length)
		ok=n;
	else{
		parts=c.value.split(/x+/,2);
		if(parts[1]){ // this is the extension
			ext=parts[1].replace(/[^0-9]/g,'')
			if(ext.length)ext=' x'+ext;
		}
		var main=parts[0].replace(/[^0-9]/g,'');
		if(main.length==10&&main[0]!='1'){
			ok=true;
			c.value='('+main.substr(0,3)+') '+main.substr(3,3)+'-'+main.substr(6)+ext;
		}else if(main.length==11&&main.substr(0,1)=='1'){
			ok=true;
			c.value='('+main.substr(1,3)+') '+main.substr(4,3)+'-'+main.substr(7)+ext;
		}else if(main.length==7){
			ok=false;
			alert('Please include an area code.');
			c.value='() '+main.substr(0,3)+'-'+main.substr(3)+ext;
		}else{
			ok=false;
			alert('Unrecognized phone number format');
		}
	}
	fg=$(c).parents('.form-group');
	ok ?
		fg.removeClass('has-error') :
		fg.addClass('has-error');
}
jscript;
}
