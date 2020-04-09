<?php
namespace Lifetoward\Synthesis\Infrastructure;
/**
*/
class EmailAlias extends ProxyElement
{
	public static $singular = "Internet domain", $plural = "Domains", $descriptive = "Domain configuration"
		, $fielddefs = [
			  'domain'=>[ 'class'=>'Domain', 'name'=>'domain', 'type'=>'require', 'label'=>"Domain", 'identifying'=>true, 'sort'=>true ]
			, 'address'=>[ 'class'=>'t_string', 'name'=>'address', 'pattern'=>'^[[:alnum:]._]$', 'label'=>"Address (without domain)", 'identifying'=>true, 'sort'=>'ASC' ]
			, 'target'=>[ 'class'=>'t_email', 'name'=>'target', 'label'=>"Target address (with domain)", 'identifying'=>true, 'sort'=>'ASC' ]
			];

	protected function __constructor( Domain $domain, $address, $target )
	{
		$this->acceptValues(func_get_args());
	}

	public static function collection( Domain $domain, $addressOrTarget = null )
	{
		$em = $domain->EmailManager;
		if (strchr($addressOrTarget, '@')) {
			$address = null; $target = $addressOrTarget;
		} else {
			$address = $addressOrTarget; $target = null;
		}
		foreach ($em->listEmailAliases("$domain", $address, $target) as $forwarder) {

		}
	}

	public static function get( $domain, $address, $target )
	{
	}

	public static function create( $domain, $address, $target )
	{
	}

	public function delete()
	{
	}

	protected function storeInstanceData( Database $db )
	{
	}
}

