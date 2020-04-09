<?php
namespace Lifetoward\Synthesis\Infrastructure;
/**
*/
abstract class EmailManager extends Element
{
	public static $singular = "Email manager", $plural = "Email managers", $descriptive = "Email management configuration"
		, $fielddefs = [
			  'name'=>[ 'name'=>'name', 'label'=>"Name", 'class'=>'t_string', 'identifying'=>true, 'sort'=>'ASC' ]
			, 'secrets'=>[ 'name'=>'secrets', 'label'=>"Access configuration", 'class'=>'t_json' ]
			];

	/**
	* Obtains a list of arrays for all active email aliases (forwarders)
	* Each array contains string values for the following keys: domain, address, & target.
	*/
	public function listEmailAliases( )
	{
		throw new NotImplementedX(__FUNCTION__, __CLASS__);
	}

	/**
	* Ensures that the specified alias for this domain exists in the operational system.
	* If the alias already exists with this target, it works out to be a no-op.
	* If an alias with the passed address already exists for a different target, both existing and new will remain.
	* @param string $address Pass the address portion of the alias, i.e. without the domain (which is determined by this object)
	* @param t_email $target Pass a valid email address to serve as the destination of the forwarding operations
	* @return void
	* @throws Exception If we're unable to complete the operation, we'll through an exception.
	*/
	public function addEmailAlias( $address, $target )
	{
		throw new NotImplementedX(__FUNCTION__, __CLASS__);
	}

	/**
	* Note that a unique domain (this), address, and target are
	* @param string $address Pass the address portion of the alias, i.e. without the domain (which is determined by this object)
	* @param t_email $target Pass a valid email address to serve as the destination of the forwarding operations
	* @return void
	* @throws Exception If we're unable to complete the operation, we'll through an exception.
	*/
	public function dropEmailAlias( $address, $target )
	{
		throw new NotImplementedX(__FUNCTION__, __CLASS__);
	}

	public function listEmailAccounts( )
	{
		throw new NotImplementedX(__FUNCTION__, __CLASS__);
	}

	public function addEmailAccount( $address, $password, $quota )
	{
		throw new NotImplementedX(__FUNCTION__, __CLASS__);
	}

	public function dropEmailAccount( $address )
	{
		throw new NotImplementedX(__FUNCTION__, __CLASS__);
	}
}
