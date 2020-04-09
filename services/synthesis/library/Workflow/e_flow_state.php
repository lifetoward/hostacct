<?php
/**
* A flow state is a condition in which a focal document (flow doc) can be, representing its place in the workflow.
* Flow states exist within flows, which are just collections of flow states.
*/
class e_flow_state extends Element
{
	public static $table = 'flow_state', $singular = "Flow state", $plural = "Flow states", $descriptive = "Flow state",
		$fielddefs = array(
			 'flow'=>array('name'=>'flow', 'class'=>'e_flow', 'type'=>'require', 'label'="Flow")
			,'state'=>array('name'=>'state', 'class'=>'t_string', 'label'=>"Label")
		);
}
