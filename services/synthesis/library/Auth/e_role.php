<?php
/**
* A role is a categorization of users' capabilities in the system.
*
* All original code.
* @package Synthesis/Authentication
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2007-2014 Lifetoward LLC
* @license proprietary
*/
class e_role extends Element
{
	public static $table = 'auth_role', $singular = 'User role', $plural = 'User roles', $descriptive = "Role info", $fielddefs = array(
		 'label'=>array('name'=>'label', 'class'=>'t_string', 'pattern'=>"^\\*?\\w[- \\w]{0,18}\\w$", 'identifying'=>true, 'required'=>true,
				'label'=>"Common label or handle", 'help'=>"You can select a role by its ID or by this handle. 2-20 characters please (letters, numbers, spaces, - or _ only)")
		,'description'=>array('name'=>'description', 'class'=>'t_text', 'label'=>'Description', 'help'=>"Describe how this role applies to system operations.")
		), $hints = array(
			 'a_Display'=>array('role'=>'*super', 'tiles'=>array(
					 array('method'=>'a_Display::fields', 'title'=>"Role info", 'operations'=>array('head'=>'update'))
					,array('method'=>'a_Display::relatives', 'class'=>'r_roleplayer', 'operations'=>array('head'=>array('select'))) ) )
			,'a_Browse'=>array('role'=>'*super', 'triggers'=>array('banner'=>'create', 'row'=>array('update','display'), 'multi'=>array('delete')) )
		), $operations = array( 'display'=>array(),'update'=>array(),'delete'=>array(),'create'=>array(),'list'=>array() );

    public function formatted()
    {
        return "$this->°label";
    }
}
