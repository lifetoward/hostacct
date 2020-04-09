<?php
/**
* e_identity
* An identity is an authentication credential identity as established by some specific provider (e_idprovider)
*
* All original code.
* @package Synthesis/Identities
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2007-2014 Lifetoward LLC
* @license proprietary
*/
class e_identity extends AuthorizedElement
{
	public static $table = "ids_identity", $singular = "Identity", $plural = "My Identities", $descriptive = "My Credentials",
		$fielddefs = array(
			 'idprovider'=>array('name'=>'idprovider','class'=>'e_idprovider','label'=>"Identity Provider",'type'=>'require','identitying'=>true,'sort'=>true)
			,'idname'=>array('name'=>'idname','class'=>'t_string','label'=>"ID Name",'identifying'=>true,'required'=>true,'sort'=>'ASC',
				'help'=>"This is the primary handle by which you are known according to this provider. It could be an ID number, user name, or email address.")
			,'passhint'=>array('name'=>'passhint','class'=>'t_passhint','label'=>"Password hint",'roleAccessField'=>'role',
				'help'=>"Use a consistent collection of password mnemonics here.")
			,'emailias'=>array('name'=>'emailias','class'=>'t_email','label'=>"Email alias",
				'help'=>"This is the email address (usually an alias) you provided to the ID provider for them to contact you regarding this ID.")
            ,'role'=>array('name'=>'role', 'class'=>'e_role', 'type'=>'refer', 'label'=>"Authorized role",
                'help'=>"System users who possess this role are allowed to see and use the identity.")
			,'notes'=>array('name'=>'notes','class'=>'t_richtext','label'=>"Notes",'help'=>"History, purpose, etc")
		), $hints = array(
			 'a_Browse'=>array('role'=>'Staff'
				,'include'=>array('idprovider','idname','passhint','emailias')
				,'triggers'=>array('banner'=>"create", 'row'=>array('update','display'), 'multi'=>"delete") )
			,'a_Edit'=>array('triggers'=>array('banner'=>'delete'),'role'=>array('=role'))
			,'a_Display'=>array('role'=>'*role', 'tiles'=>array(
				 array('method'=>'a_Display::fields', 'operations'=>array('head'=>'update'))
				,array('method'=>'e_identity::listFacilities','title'=>"Facilities available",'width'=>12)
				) )
			,'a_Delete'=>array('role'=>array('*super','*owner'))
		), $operations = array(
			 'display'=>array(),'update'=>array(),'delete'=>array(),'create'=>array(),'list'=>array()
		);

	// * * * * * * * AUTHORIZATION CONTROL * * * * * * * *
	protected static function loadInstanceData( $keysel, array $args = array() )
	{
		global $root;
		if (!($authId = $root->getAuthId())) // assignment intended
			throw new ErrorException("Can't load elements of class e_identity without an authorized context.");
		if (is_string($args['where']))
			$args['where'] = array($args['where']);
		$args['where'][] = "`{}`._creator = $authId OR (`{}`.role IS NOT NULL AND `{}`.role IN (". $root->getAuthRoleIds() ."))";
		return parent::loadInstanceData($keysel, $args);
	}

	// This a_Display tile rendering method lists facilities that I have access to using this ID by chaining through the provider.
	public static function listFacilities( Instance $focus, array $hints, HTMLRendering $R )
	{
		$R->mode = $R::COLUMNAR;
		foreach (e_facility::getUltimateClasses() as $uclass) {
			if (!count($coll = $uclass::collection(['where'=>"`{}_facility`.provider = {$focus->idprovider->_id}"])))
				continue;
			$labels = ''; $rows = '';
			$exclude = $hints['exclude'] ? $hints['exclude'] : $uclass::$hints['a_Browse']['exclude'];
			$include = $hints['include'] ? $hints['include'] : $uclass::$hints['a_Browse']['include'];
			foreach ((array)$uclass::getFieldDefs($R, $exclude, $include) as $fn=>$fd)
				$labels .= "	<th>". (htmlentities($hints['relabel'][$fn] ? $hints['relabel'][$fn] : $uclass::getFieldLabel($fn))) ."</th>\n";
			foreach ($coll as $d)
				$rows .= '<tr><td>'. implode("</td><td>", $d->renderFields($R, $exclude, $include)) ."</td></tr>\n";
			$heading = htmlentities($uclass::$plural);
			$out .= "<h3>$heading</h3>\n<table class=\"table table-striped table-hover table-responsive\">\n".
				"<thead><tr class=\"browse\">$labels</tr></thead>\n<tbody>$rows\n</tbody></table>";
		}
		return $out ? "<p>This ID provides access to...</p>$out" : "<p>No known facilities are accessible using this ID.</p>";
	}

	public function formatted()
	{
		return "$this->°idname @ $this->°idprovider";
	}
}
