<?php
namespace Lifetoward\Synthesis\Infrastructure;
/**
*	This object represents an internet domain as manageable by Synthesis
*/
class Domain extends Element
{
	protected static $table = 'inf_domain', $singular = "Internet domain", $plural = "Domains", $descriptive = "Domain configuration"
		, $fielddefs = [
			  'name'=>[ 'name'=>'name', 'class'=>'t_string', 'label'=>"Canonical domain name", 'pattern'=>'^()', 'identifying'=>true, 'sort'=>'ASC' ]
			, 'purpose'=>[ 'name'=>'purpose', 'class'=>'t_richtext', 'label'=>"Purpose and notes" ]
			, 'EmailManager'=>[ 'name'=>'EmailManager', 'class'=>'EmailManager', 'label'=>"Email manager", 'type'=>'refer' ]
//			, 'DNSManager'=>[ 'name'=>'DNSManager', 'class'=>'DNSManager', 'label'=>"DNS manager", 'type'=>'refer' ]
			];
}
