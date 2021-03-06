<?php

class flow_log
{
	public $user, $focus, $action, $time, $note;

	public function __toString()
	{

	}

	public function __construct($user, $focus, $action, $time, $note)
	{
		foreach (compact('user','focus','action','time','note') as $name=>$value)
			$this->$name = $value;
	}
}

abstract class t_flow_log extends Type
{
	public static function format( /* Instance */ $d, $fn = null, $format = null )
	{
	}

	public static function render( Instance $d, $fn, Context $c, $format = null )
	{
	}

	public static function accept( $value, $fd = array() )
	{
	}

	public static function accept_db( $value, $fd = array() )
	{
	}

	public static function put_db( $value, $fd = array(), Database $db = null )
	{
	}

	public static function mysql_ddl( array $fd, Database $db = null )
	{
		if (!$db)
			$db = $GLOBALS['root'];
		return "`$fd[name]` TEXT DEFAULT NULL COMMENT '". $db->dbEscapeString("$fd[label] (". get_called_class() .")") ."'";
	}

}

