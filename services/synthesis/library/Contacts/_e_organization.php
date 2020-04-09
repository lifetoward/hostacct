<?php
/**
* An organization with agency.
*
* All original code.
* @package Synthesis/Contacts
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2007-2014 Lifetoward LLC
* @license proprietary
*/
class _e_organization extends Element
{
	public static $table = "com_organization", $singular = "Company", $plural = "Companies", $descriptive = "Company details",
		$fielddefs = array(
			 'entity'=>array('name'=>'entity', 'label'=>"Entity", 'class'=>'e_entity', 'type'=>'include', 'identifying'=>true, 'overrides'=>array(
				'name'=>array('label'=>'Common name', 'help'=>"Provide the common name which refers to this Organization.")))
			,'contact'=>array('name'=>'contact', 'label'=>"Primary contact", 'class'=>'e_person', 'type'=>'refer', 'sort'=>true, 'help'=>"Person to contact for general purposes.")
			,'website'=>array('name'=>'website','label'=>"Website", 'class'=>'t_url')
			,'legalname'=>array('name'=>'legalname', 'label'=>"Legal name", 'class'=>'t_string', 'sort'=>'ASC', 'help'=>"The official legal name of the entity")
			,'empident'=>array('name'=>'empident', 'label'=>"Employer Identification Number", 'class'=>'t_string', 'pattern'=>'^\d\d-\d{7}$', 'trim'=>true)
			,'charter'=>array('name'=>'charter', 'label'=>"Purpose or description", 'class'=>'t_richtext', 'help'=>"Describe the Organization's charter or business here.")
			,'notes'=>array('name'=>'notes', 'label'=>"Reference notes", 'class'=>'t_richtext', 'help'=>"Use for historical notes or other internally relevant information.")
		), $hints = array( // hints are provided for general purpose actions to be able to customize rendering of this particular element class
			 'a_Browse'=>array(
				 'include'=>array('name','contact','website')
				,'triggers'=>array('banner'=>'create', 'row'=>array('update','display','addresses'), 'multi'=>array('delete','addresses')) )
			,'a_Display'=>array('role'=>'Staff','tiles'=>array(
				 array('method'=>'a_Display::fields','title'=>"Contact information", 'include'=>array('phone','email','contact','website','msgsvc','carrier','mailaddr','comnotes')
					,'operations'=>array('head'=>'update'))
				,array('method'=>'a_Display::fields','title'=>"Reference information", 'exclude'=>array('phone','email','contact','website','msgsvc','carrier','mailaddr','comnotes')
					,'operations'=>array('head'=>'update'))
				,array('method'=>'a_Display::relations','class'=>'r_OrgMember', 'headfield'=>'title', 'include'=>array('phone','email'), 'operations'=>array('head'=>array('view','select')))
				) )
			,'a_Delete'=>array('role'=>'Administrator')
		), $operations = array( // actions allow general purpose actions to know how to interact with this element class in various situations
			 'display'=>array()
			,'update'=>array('action'=>'a_Edit', 'role'=>array('*owner','Staff'))
			,'delete'=>array('action'=>'a_Delete','role'=>'Administrator')
			,'create'=>array('action'=>'a_Edit', 'role'=>'Staff')
			,'list'=>array('action'=>'a_Browse', 'role'=>'Staff')
			,'addresses'=>array('action'=>'a_ProduceAddressesPDF', 'icon'=>'envelope')
		);

	public function formatted( )
	{
		return "$this->°name";
	}

    /**
    * To facilitate fast manual data entry when entering biz cards (for example), we recommend users
    * begin by creating the Company (if there is one), being sure to ( Add new Primary contact ... ) during that
    * process to get the new Person in the system. Then we have this logic which makes sure that any Primary Contact
    * of a Company has that Company SET as their own.
    */
	protected function storeInstanceData( Database $db )
	{
        $this->key = parent::storeInstanceData($db);
		if ($this->contact) {
            $o = r_OrgMember::get($this, $this->contact);
            if (!$o->_stored) {
                $o->title = "Primary contact";
                $o->store();
            }
        }
		return $this->key;
	}
}
