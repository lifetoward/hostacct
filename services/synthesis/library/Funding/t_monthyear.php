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
abstract class t_monthyear extends Type
{

/* NOTICE: These functions currently only render for input because this CC information is not intended to be stored or reviewed. */

function monthyear_type( $c, $f, $d )
{
	$thisyear = 0+date('Y');
	list($m,$y) = ($d ? sscanf($d[$f['name']], "%d-%d") : array(12,$thisyear));
	for ($x = 1; $x <= 12; $x++) {
		$val = sprintf("%02d", $x);
		$monthoptions .= '<option value="'. $val .'"'. ($x==$m?' selected=""':null) .">$val</option>";
	}
	for ($x = 0; $x < 10; $x++)
		$yearoptions .= '<option value="'. ($thisyear + $x + 0) .'"'. ($x==$y?' selected=""':null) .'>'. ($thisyear + $x + 0) .'</option>';
	if ($m<10)
		$m = "0$m";
	return '<input type="hidden" name="'. $f['name'] .'" id="'. $c['idprefix'] . $f['name'] .'" value="'. "$m-$y" .'"/>'.
		'<select class="input" size="1" onchange="this.previousSibling.value=this.value+\'-\'+this.nextSibling.value">'. $monthoptions .'</select>'.
		'<select class="input" size="1" onchange="this.previousSibling.previousSibling.value=this.previousSibling.value+\'-\'+this.value">'. $yearoptions .'</select>';
}

}

?>
