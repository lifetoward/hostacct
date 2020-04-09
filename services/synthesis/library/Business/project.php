<?php
/**
* project
* A Project is a purposeful activity against which expenses and income can be allocated.
*
* Created: 3/25/15 for Lifetoward LLC
*
* All original code.
* @package Synthesis/Finance
* @author Biz Wiz <bizwiz@SyntheticWebApps.com>
* @copyright (c) 2015 Lifetoward LLC; All rights reserved.
* @license proprietary
*/
class Project extends Element
{
	const ActiveFilter = "(`{}`.began IS NULL OR `{}`.began <= NOW()) AND (`{}`.ended IS NULL OR `{}`.ended >= NOW())";

	public static $table = "biz_project", $singular = "Project", $plural = "Projects", $descriptive = "Project details",
		$help = "A Project is a purposeful activity against which expenses and income can be allocated.",
		$fielddefs = [
			'client'=>['name'=>'client', 'class'=>'Client', 'type'=>'refer', 'identifying'=>true, 'label'=>"Client", 'sort'=>true, 'width'=>5,
				'help'=>"If you leave this unspecified, it implies this is an internal project."],
			'label'=>['name'=>'label','class'=>'t_string','label'=>"Project name",'identifying'=>true,'sort'=>true, 'width'=>7],
			'propdate'=>['name'=>'propdate', 'class'=>'t_date', 'label'=>"Date proposed", 'sort'=>true, 'width'=>4],
			'began'=>['name'=>'began', 'class'=>'t_date', 'label'=>"Date begun", 'sort'=>true, 'width'=>4],
			'ended'=>['name'=>'ended', 'class'=>'t_date', 'label'=>"Date ended", 'sort'=>true, 'width'=>4],
			'discount'=>[ 'name'=>'discount', 'class'=>'t_percent', 'label'=>"Discount", 'initial'=>0,
				'help'=>"If you set this value, all new client transactions will be initialized with this discount." ],
			'description'=>['name'=>'description','class'=>'t_richtext','label'=>"Description", 'width'=>12],
		], $operations = [
			'display'=>['role'=>'Staff'],
			'update'=>['role'=>'Manager'],
			'delete'=>['role'=>'Manager'],
			'create'=>['role'=>'Manager'],
			'list'=>['role'=>'Staff'],
		], $hints = [ // hints are provided for general purpose actions to be able to customize rendering of this particular element class
			 'a_Browse'=>['role'=>'Staff', 'exclude'=>['description','propdate']
				,'triggers'=>['banner'=>'create', 'row'=>['update','display'], 'multi'=>'delete'] ],
			'a_Edit'=>['role'=>'Manager','triggers'=>['banner'=>'delete']],
			'AJAXAction'=>['asJSON'=>'Staff'],
		];

	public function formatted()
	{
		return "$this->client: $this->°label";
	}
}
