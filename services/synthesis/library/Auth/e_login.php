<?php
/**
* The login element contains authentication information for a user.
* It includes some special handling rules which support rendering a login form and allowing only privileged acceptance of data.
*
* All original code.
* @package Synthesis/Authentication
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2007-2014 Lifetoward LLC
* @license proprietary
*/
class e_login extends Element
{
	public static $table = 'auth_login', $singular = 'System user', $plural = 'System users', $descriptive = "System login details", $noQueryLogs = true, $fielddefs = array(
		 'username'=>array('name'=>'username', 'label'=>"Login name", 'class'=>'t_string', 'size'=>20, 'pattern'=>'^[\\w]{2,16}$', 'identifying'=>true, 'required'=>true, 'trim'=>true, 'noupdate'=>true,
				'help'=>"This name is used to allow you to log in to the system. A login name must be unique in the system and consist only of letters, numbers, spaces, and _'s with at least 2 and no more than 16 characters.")
		,'password'=>array('name'=>'password', 'label'=>'Password', 'class'=>'t_password', 'trim'=>true, 'size'=>20,
				'help'=>"A password is required if you have not already set one. If you have already set one, leave both fields blank to leave it as is. Passwords must have at least 6 characters and should be hard for others to guess.")
		,'passhint'=>array('name'=>'passhint', 'label'=>'Password hint', 'class'=>'t_string', 'required'=>true,
				'help'=>"If you are having trouble remembering your password, you can request that this text be shown to you to jog your memory. Use a word or phrase that will help you but no one else.")
		), $hints = array( // hints are provided for general purpose actions to be able to customize rendering of this particular element class
			 'a_Display'=>array('role'=>array('User Manager','*owner'), 'tiles'=>array(
					 array('method'=>'a_Display::fields', 'title'=>"Login details", 'operations'=>array('head'=>'update'))
					,array('method'=>'a_Display::relatives', 'class'=>'r_rolemap', 'operations'=>array('head'=>array('select'))) ) )
			,'a_Browse'=>array('role'=>'User Manager', 'triggers'=>array('banner'=>'create', 'row'=>array('update','display'), 'multi'=>array('delete')) )
			,'a_Edit'=>array('role'=>array('User Manager','*owner'))
		), $operations = array(
            'display'=>array(),'update'=>array(),'delete'=>array(),'create'=>array(),'list'=>array()
        );

    public function formatted()
    {
        return "$this->°username";
    }

	/**
	* We will only accept passwords from the owner of the login record or from a user with *super role.
	*/
	public function acceptValues( $values, Context $c )
	{
		if ($c->getAuthUser() != $this->original('username') && !$c->isAuthorized('User Manager'))
			return null;
		if (array_key_exists('password', $values) && !$values['password'])
			unset($values['password']); // an empty password is not allowed when updating an e_login
		return parent::acceptValues($values, $c);
	}

	/**
	* This is a helpful little API to allow simple assignment of login users to roles by name.
	* @param string $roleName The unique name of the role you want this login to have.
	* @return The key to the rolemap relation created or confirmed.
	* @throws Exception Could possibly throw a database error exception if there's something seriously wrong, but this is not likely.
	*/
	public function assignRole( $roleName )
	{
		$selector = "label='". $GLOBALS['root']->dbEscapeString($roleName). "'";
		$role = (e_role::cardinality($selector) >= 1 ? e_role::get($selector) :
			e_role::create(array('label'=>$roleName, 'description'=>"Auto-created by the system (e_login->assignRole()) because someone assigned it.")));
		return r_rolemap::get($this, $role)->store(); // will store the new role with it if necessary
	}
}

