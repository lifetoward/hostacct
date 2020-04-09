<?php
/**
* A flow is a workflow, which is a container for a set of flow doc states connected by paths which are traversed through actions taken by users according to their roles.
*/
class e_flow extends Element
{
	public static $table = 'flow', $singular = "Work flow", $plural = "Work flows", $descriptive = "Work flow specification",
		$fielddefs = array(
			 'name'=>array('name'=>'name', 'label'=>'Flow name', 'class'=>'t_string')
		);
}
