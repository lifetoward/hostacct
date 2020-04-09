<?php

class e_message extends Element
{
	public static $FQN = 'messages:element:message';

	public function formatted()
	{
		return "$this->name @ $this->composed: $this->subject";
	}
}

?>
