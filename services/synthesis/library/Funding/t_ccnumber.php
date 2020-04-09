<?php
/**
* The methods in this abstract subclass are all described in the base class Type, so don't expect a lot of comments about them here.
*
* All original code.
* @package Synthesis/Business
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
abstract class t_ccnumber extends Type
{

/* NOTICE: These functions currently only render for input because this CC information is not intended to be stored or reviewed. */

function ccnumber_type( $c, $f, $d )
{
	capcel_include_jscript('input','ebiz');
	$nullok = $f['flags']['notnull']?0:1;
	if ($d[$f['name']]) {
		global $tailscript;
		$tailscript .= "ebiz_validate_ccnumber(document.getElementById('$c[idprefix]$f[name]'),'$f[name]',$nullok,$f[pattern])\n";
	}
	return '<input type="text" name="'. $f['name'] .'" id="'. $c['idprefix'] . $f['name'] .'" value="'. htmlentities($d[$f['name']]) .'" '.
		'onchange="ebiz_validate_ccnumber(this,\''. $f['name'] ."',$nullok,$f[pattern])".'" size="22"/><img src="images/invalid.png"/>';
}

	const scripting = <<<'jscript'
function luhnchk(n)
{
	var x,e,v,s=0;
	if(!n.match(/^\d{2,20}$/))return 0;
	e=n.length%2?0:1;
	for(x in n){
		v=''+((((e+1*x)%2)+1)*n[x])+'';
		for(y in v)
			s+=(1*v[y]);
	}
	return !(s%10);
}

function ebiz_validate_ccnumber(c,f,n,p)
{
	v=c.value=c.value.replace(/[-\s]/g,'');
	if(!v.length&&n)
		return capcel_validated(c,f,true);
	ok=1*(v.match(p)&&luhnchk(v));
	c.nextSibling.src=(ok?'images/check.png':'images/invalid.png');
	if(!ok)alert('The card number entered is invalid. Please correct it before proceeding.');
	return capcel_validated(c,f,ok);
}
jscript;

}

?>
