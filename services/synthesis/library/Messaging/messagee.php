<?php

interface Messagee
{
	// Call this to inform an implementing entity that they have a message waiting, ie. typically by sending an email.
	public function message_notifier( );
	// Return the number of notifications sent, ie. 0 or 1.
}

?>
