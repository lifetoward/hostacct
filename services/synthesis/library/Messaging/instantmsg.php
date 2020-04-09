<?php
/**
* A communications system is a messaging mode employable in contacting an entity with a message.
* For each mode, we have one of these instance records. But most instance records actually have their own classes.
* Why do they have a database presence? 1. So they are listable, ie. "What modes are possible to assign to this entity?" can be answered. 2. So we can anchor the real data associated with them to other fixed points in the data model.
*
* All original code.
* @package Synthesis/Contacts
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2007-2014 Lifetoward LLC
* @license proprietary
*/
class e_messaging extends e_commsys
{
	public static $singular = "Messaging service" ,$plural = "Messaging services", $descriptive = "Messaging service";

	/**
	*
	*/
	public function postMessage( Relation $ident, $format, $message, $thread = null );

	/**
	*
	*/
	public function renderConnectable( )
	{
	}

}
