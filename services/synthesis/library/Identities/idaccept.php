<?php
/**
* Associates a secured facility with an identity provider which is recognized for authenticating to it.
*
* All original code.
* @package Synthesis/Contacts
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
abstract class IDAccept extends Relation
{
	static $table = 'ids_accept', $descriptive = "Support",
		$fielddefs = array(
		);
}

class r_secfacility extends IDSupport
{
	static $keys = array('provider'=>'e_idprovider', 'facility'=>'e_facility'), $singular = "Secured facility", $plural = "Secured facility"
		,$hints = array(
		);
}

class r_idaccept extends IDSupport
{
	static $keys = array('facility'=>'e_facility', 'provider'=>'e_idprovider'), $singular = "Accepted identity", $plural = "Accepted identities"
		,$hints = array(
		);
}
