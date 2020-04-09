<?php
/**
* A secured facility is any resource to which access is restricted by credential.
*
* All original code.
* @package Synthesis/Identities
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
class e_facility extends Element
{
	public static $table = "ids_facility", $singular = "Secured resource", $plural = "Secured resources", $descriptive = "Resource security",
		$fielddefs = array(
			 'facname'=>array('name'=>'facname','class'=>'t_string','identifying'=>true,'label'=>"Facility name",'required'=>true,'sort'=>'ASC')
			,'admin'=>array('name'=>'admin','class'=>'e_entity','type'=>'refer','label'=>"Administrator",'sort'=>true,'help'=>"This is the entity which provides or manages the facility.")
			,'description'=>array('name'=>'description','class'=>'t_richtext','label'=>"Description")
			,'provider'=>array('name'=>'provider','class'=>'e_idprovider','type'=>'require','label'=>"ID Provider",
				'help'=>"Specify the identity provider which this facility accepts and which you prefer to use for it. This will determine which identities can be used here.")
		), $hints = array( // hints are provided for general purpose actions to be able to customize rendering of this particular element class
			 'a_Browse'=>array(
				 'include'=>array('facname','provider')
				,'triggers'=>array('banner'=>'create', 'row'=>array('update','display'), 'multi'=>'delete') )
			,'a_Edit'=>array('triggers'=>array('banner'=>'delete'))
		), $operations = array(
			 'display'=>array(),'update'=>array(),'delete'=>array(),'create'=>array(),'list'=>array()
		);

	// This a_Display tile rendering method lists identities that I have access to using this ID by chaining through the provider.
	public static function listIdentities( Instance $focus, array $hints, HTMLRendering $R )
	{
		$class = get_class($focus);
		if (! $focus instanceof e_facility)
			throw new Exception("Focus element class '$class' is not compatible with ". __FUNCTION__);
		// We rely on the fact that identities cannot be loaded except by their owner, so we don't filter for that.
		if (!count($coll = e_identity::collection(array('where'=>"idprovider = {$focus->provider->_id}"))))
			return "<p>None of your identities is compatible with this ". htmlentities($focus::$singular) .".</p>";
		foreach (e_identity::getFieldDefs($R, $exclude = array(), $include = array('idname','passhint')) as $fn=>$fd)
			$labels .= "	<th>". (htmlentities($hints['relabel'][$fn] ? $hints['relabel'][$fn] : e_identity::getFieldLabel($fn))) ."</th>\n";
		foreach ($coll as $d)
			$rows .= '<tr><td>'. implode("</td><td>", $d->renderFields($R, $exclude, $include)) ."</td></tr>\n";
		return "<h3>Compatible identities</h3>\n<table class=\"table table-striped table-hover table-responsive\">\n".
			"<thead><tr class=\"browse\">$labels</tr></thead>\n<tbody>$rows\n</tbody></table>";
	}

	public function formatted()
	{
		return "$this->°facname";
	}
}
