<?php
$sysroot = "/Users/guy/Projects/inneractive";

function trace($msg)
{
	print "$msg\n";
}
function dump_array($a, $name = "")
{
	if (!is_array($a) && !is_object($a))
		return "$name: not an array or object";
	ob_start();
	var_dump($a); 
	$fixed = preg_replace("|=>\n *|", "=> ", ob_get_clean());
	$clean = substr(preg_replace("|\n *}\n|", " }\n", $fixed), 0, -1);
	return "$name: $clean";
}
function trace_var($a, $name = "")
{
	print dump_array($a, $name) ."\n";
}

set_include_path(get_include_path() . PATH_SEPARATOR . "$GLOBALS[sysroot]/app/x12");

spl_autoload_register(); // get standard class loading by path and classname

spl_autoload_register(
	function ( $name )
	{
		foreach (array('loop','segment','envelope') as $struct)
			if ("x12_$struct" == $name)
				require_once 'x12_struct.php';
		foreach (array('comp','code','text','integer','decimal','notused') as $element)
			if ("x12el_$element" == $name)
				require_once 'x12_element.php';
	}
);

// TEST STUFF BELOW

$EDI = new x12env_interchange();
$EDI->parse(file_get_contents('/Users/guy/Projects/inneractive/check.x12'));
file_put_contents('dump.out', dump_array($EDI));
file_put_contents('rendered.x12', $rendered = $EDI->render());
?>
