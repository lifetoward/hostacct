<?php
/**
 * Authorization is a trait which makes an authenticated root context able to answer authorization questions at the static (Class and Type) level.
 *
 * @package Synthesis/Authentication
 * @author Guy Johnson <Guy@SyntheticWebApps.com>
 * @copyright 2007-2014 Lifetoward LLC
 * @license proprietary
 */
trait Authorization
{
	private $authInfo = null, $authRoles = array();
	private static $savedProperties = array('authInfo','authRoles');

	public function hasAuthRole( $role )
	{
		return $this->authRoles['*super'] || ($role ? $this->authRoles[$role] : false);
	}
	public function getAuthId( )
	{
		return $this->authInfo->_id;
	}
	public function getAuthUser( )
	{
		return $this->authInfo->username;
	}
	public function listAuthRoles( )
	{
		return array_keys($this->authRoles);
	}
    public function getAuthRoleIds( )
    {
        return count($this->authRoles) ? implode(',', array_values($this->authRoles)) : 'null';
    }

	/**
	* This general purpose authorization checker bases its decisions on the roles defined for the authenticated user.
	* It has a polymorphic signature. Note that in all cases, if a context's authenticated user has the *super authority, they are always authorized.
	* - When passed nothing, we return true if there is an authenticated user registered for the context.
	* - When passed an array, it's assumed to be a simple list of role names which are authorized. This list is checked against the context's known authorized roles and if a match is found we return true.
	* - When passed a string, we assume it's a role name and if the user has that role we return true.
	* - When passed 2 strings, we take the first as a class name and the second as an action identifier and we return whether the user is allowed to perform that action on that class of data.
	* - When passed a Instance object, we return whether the user is the represented by the object.
	* - When passed a Instance object and an action index, we check whether the user is allowed to perform that action on that instance. Mostly that means for the class, but pseudo roles *self or *owner can apply (unlike for class).
	* @return boolean True if the authenticated user (according to the context) is authorized; false otherwise. We return null in error situations.
	*/
	public function isAuthorized( $operand = null, $role = null )
	{
		// Users with the *super role are always authorized
		if ($this->authRoles['*super'])
			return true;

		// If you pass nothing and a user is logged in (authenticated) then we return true
		if (!$operand)
			return (boolean)$this->authInfo;

		// If you pass only the name of a role, we just tell you whether the authenticated user has that role.
		if (is_string($operand) && !$role)
			return $this->authRoles[$operand];

		// If you pass only an array, we assume its a list of roles, and as long as one of them is authorized we say yes.
		if (is_array($operand) && !$role) {
			foreach ($operand as $reqrole)
				if ($this->authRoles[$reqrole])
					return true;
			return false;
		}

		// When given an Instance object or the name of a subclass of it...
		if (is_subclass_of($operand, 'Instance')) {

			// If you provide a string as the second arg, we assume it's an operation name and we'll tell you whether the user is allowed to perform that operation on that object type
			if (is_string($role)) {
				if (isset($operand::$actions))
					if (is_array($operand::$actions[$role]))
						return $this->isAuthorized((array)$operand::$actions[$role]['role']);
				return true;

			// If you provide an actual object instance but no second arg, we'll tell you whether the current user owns (created unless reassigned) that object.
			} else if (!$role && $operand instanceof Instance)
				return $this->authInfo->_id == $operand->_creator;
		}

		return false; // if we can't figure out what you're asking, we better not say yes.
	}
}
