<?php
/**
* An entity is any contactable agent or agency. It could be a person, a company, a department, an automated service, etc.
*
* All original code.
* @package Synthesis/Contacts
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2007-2014 Lifetoward LLC
* @license proprietary
*/
class _e_entity extends Element
{
	public static $table = 'com_entity', $singular = "Contactable entity", $plural = "Entities", $descriptive = "Contact information",
		$fielddefs = array(
			 'name'=>array('name'=>'name', 'class'=>'t_string', 'label'=>"Entity name", 'sort'=>'ASC', 'identifying'=>true, 'required'=>true)
			,'category'=>array('name'=>'category', 'derived'=>'{}._capcel', 'label'=>"Category", 'sort'=>'ASC')
			,'email'=>array('name'=>'email', 'class'=>'t_email', 'label'=>"Email address",
				'help'=>"We practically require an email address because it's one of our primary means of contacting our members.")
			,'phone'=>array('name'=>'phone', 'class'=>'t_phone', 'label'=>"Phone number",
				'help'=>"This is the number to use for calling by voice.")
            ,'enthusiasm'=>array('name'=>'enthusiasm', 'class'=>'t_rating', 'label'=>"Enthusiasm", 'sort'=>true,
               'help'=>"A qualitative assessment of how interested or interesting this contact is relative to peers of the same type. Usually used in CRM selection criteria.")
			,'mailaddr'=>array('name'=>'mailaddr', 'class'=>'f_postaddr_USA', 'label'=>"Mailing address", 'type'=>'fieldset',
				'help'=>"This address is for receiving mail, hence perhaps a PO Box, etc. Locating the person (to attend a meeting, assign a route, etc) is a different thing. Add locations by managing the entity's overview (<span class=\"glyphicon glyphicon-zoom-in\"></span>)")
//			,'fax'=>array('name'=>'fax', 'class'=>'t_fax', 'label'=>"Fax number")
//			,'capabilities'=>array('name'=>'capabilities', 'class'=>'t_boolset', 'label'=>"Capabilities", 'initial'=>"html,mms,voicemail",
//					'options'=>array('voicemail'=>"Voicemail", 'mms'=>"Multimedia messaging", 'html'=>"Rich text email", 'paperless'=>"Paperless faxes"))
//			,'comprefs'=>array('name'=>'comprefs', 'class'=>'t_boolset', 'label'=>"Communication preferences",
//					'options'=>array('encrypt'=>"Encrypt emails", 'sign'=>"Sign emails", 'msgOverEmail'=>"Use text messaging rather than email when possible", 'novm'=>"Do not leave voicemails"))
			,'comnotes'=>array('name'=>'comnotes', 'class'=>'t_richtext', 'label'=>"Contact notes", 'help'=>"Here specify further details about how or when to contact this entity.")
		), $hints = array(
			 '*all'=>array('exclude'=>array('comprefs','capabilities'))
			,'a_Browse'=>array('include'=>array('name','category','email','phone'))
		), $operations = array(
			 'list'=>array()
		);

	public function formatted( )
	{
		return "$this->°name";
	}

	public function getRecipientAddress( )
	{
		if ($this->mailaddr)
			$address = $this->°mailaddr;
		else if ($relLoc = reset(r_location::getRelations($this, array('limit'=>1))) && $relLoc->_relative->address) // assignment intended
			$address = $relLoc->_relative->°address;
		else
			return null;
		return Element::get($this->_capcel) . $address;
	}

	public function notify( $subject, $msgs )
	{
		if (!is_array($msgs))
			$msgs = array($msgs);
		$htmlMsgs = array_walk($msgs, function(&$val,$key){$val=htmlentities($val);});
		$message = '<html><head><style type="text/css">'.
			'body { font-size:12pt; background-color:white; font-family:arial;color:black; }'.
			'p { border-width:0;position:relative;left:5%;width:60%; }'.
			"</style></head><body>\n".
			"<h3>". htmlentities($subject) ."</h3>\n<p>".
			implode("</p>\n<p>", $htmlMsgs) ."</p>\n</body></html>\n";
		sendEmail("$this->°name <$this->°email>", $subject, $message);
		return;
	}

}
