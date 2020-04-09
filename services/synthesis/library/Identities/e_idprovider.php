<?php
/**
* An identity provider provides credentials for accessing one or many facilities to which access is restricted by some kind of authentication credential.
*
* All original code.
* @package Synthesis/Identities
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
class e_idprovider extends Element
{
	public static $table = "ids_provider", $singular = "Identity provider", $plural = "Identity providers", $descriptive = "ID provider details",
		$fielddefs = array(
			 'name'=>array('name'=>'name','label'=>"Provider name", 'class'=>'t_string','identifying'=>true,'sort'=>'ASC')
			,'acctmanage'=>array('name'=>'acctmanage','label'=>"Account management", 'class'=>'t_url')
			,'loginuri'=>array('name'=>'loginuri','class'=>'t_url','label'=>"Login page",'help'=>"Only needed if the login page must be reached separately from the home page.")
			,'org'=>array('name'=>'org', 'label'=>"Provider organization", 'class'=>'e_organization', 'type'=>'refer')
			,'authenticators'=>array('name'=>'authenticators','label'=>"Authentication options", 'class'=>'t_boolset',
				'options'=>array('password'=>"Password", 'pin'=>"Personal ID # (PIN)", 'personal'=>"Personal questions",'rfid'=>"RFID gadget",
					'syncalg'=>"Synchro algorithm", 'smartcard'=>"Smart card", 'magstripe'=>"Magnetic stripe card", 'finger'=>"Fingerprint",
					'x509'=>"X.509 certificate", 'privkey'=>"Private key", 'oauth'=>"OAuth", 'saml2'=>"SAML v2", 'openid'=>"Open ID",'retina'=>"Retina scan",
					'palm'=>"Palm scan"))
			,'notes'=>array('name'=>'notes','label'=>"Notes", 'class'=>'t_richtext')
		), $hints = array( // hints are provided for general purpose actions to be able to customize rendering of this particular element class
			 'a_Browse'=>array('role'=>'*'
				,'include'=>array('name','acctmanage','org')
				,'triggers'=>array('banner'=>'create', 'row'=>'update', 'multi'=>'delete') )
			,'a_Edit'=>array('role'=>'Administrator','triggers'=>array('banner'=>'delete'))
			,'a_Delete'=>array('role'=>'Administrator')
			,'a_Display'=>array('role'=>'Staff')
		), $operations = array('display'=>array(),'update'=>array(),'delete'=>array(),'create'=>array(),'list'=>array()
		);

	public function formatted()
	{
		return "$this->°name";
	}
}
