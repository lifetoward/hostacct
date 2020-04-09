<?php
$paths = array('/Users/guy/Projects/inneractive/lib/x12');
set_include_path(implode(PATH_SEPARATOR, $paths));

function trace($obj, $msg = null)
{
	global $trace;
	if (!is_string($obj))
		$trace .= "$msg: ". dump_array($obj);
	else
		$trace .= "$obj\n";
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

spl_autoload_register(); // get standard class loading by path and classname

spl_autoload_register(
	function ( $name )
	{
		foreach (array('comp','code','text','integer','decimal','notused') as $element)
			if ("x12el_$element" == $name)
				require_once 'x12_element.php';
	}
);

// TEST STUFF BELOW

$EDI = new x12env_interchange();
$EDI->parse(file_get_contents($argv[1]));
print $EDI->payload->payload->payload->report() ."\n";
?>
