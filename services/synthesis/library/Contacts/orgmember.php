<?php
/**
* orgmember
* Describe this new Relation's role in the Module or System here.
*
* Created: 12/18/14 for Lifetoward LLC
*
* All original code.
* @package Synthesis/Contacts
* @author Biz Wiz <bizwiz@SyntheticWebApps.com>
* @copyright (c) 2014 Lifetoward LLC; All rights reserved.
* @license proprietary
*/
abstract class OrgMember extends Relation
{
	static $table = 'com_orgmbr', $descriptive = "Descriptive label",
		$fielddefs = array(
			 'title'=>array('name'=>'title','class'=>'t_string','label'=>"Title", 'help'=>"The official title the person holds within the overall organization.")
			,'email'=>array('name'=>'email', 'class'=>'t_email', 'label'=>"Email address @",
				'help'=>"This is the email address held by this person as a member of this organization.")
			,'phone'=>array('name'=>'phone', 'class'=>'t_phone', 'label'=>"Phone number @",
				'help'=>"This is the number to use for calling by voice.")
			,'msgsvc'=>array('name'=>'msgsvc', 'class'=>'t_msgsvc', 'label'=>"Text message # @",
				'help'=>"For handheld SMS, MMS, iMessage, etc. messaging for the purposes of the organization.")
			,'carrier'=>array('name'=>'carrier', 'class'=>'t_select', 'label'=>"Mobile communications service provider @", 'help'=>"This setting may be required for the system to send some kinds of messages.",
					'options'=>array('verizon'=>"Verizon", 'att'=>"AT&T", 'tmobile'=>"T-Mobile", 'sprint'=>"Sprint",
							'boost'=>"Boost mobile", 'metropcs'=>"MetroPCS", 'cricket'=>"Cricket", 'uscellular'=>"US Cellular", 'virgin'=>"Virgin mobile", 'ting'=>"Ting"))
		), $hints = array( // hints are provided for general purpose actions to be able to customize rendering of this particular element class
			 'a_Display'=>array('role'=>'Staff', 'operations'=>array('head'=>array('select','view')),'include'=>array())
			,'a_Edit'=>array('triggers'=>array('banner'=>'delete'))
			,'a_Relate'=>array()
			,'a_Delete'=>array('role'=>'Manager')
		), $operations = array( // actions allow general purpose actions to know how to interact with this element class in various situations
			 'select'=>array(),'view'=>array(),'edit'=>array(),'remove'=>array(),'add'=>array()
		);
}

class r_OrgMember extends OrgMember
{
	static $keys = array('organization'=>'e_organization', 'person'=>'e_person'),
		$singular = "Member", $plural = "Members";
}

class r_Org extends OrgMember
{
	static $keys = array('person'=>'e_person', 'organization'=>'e_organization'),
		$singular = "Organization", $plural = "Organizations";
}


