<?php
/**
* An flow path is an available phase transition for a workflow. There can be many flow paths from a given starting state.
*/
class r_flow_path extends Relation
{
	public static $table = 'flow_path', $keys = array('from'=>'e_flow_state','to'=>'e_flow_state')
		,$singular = 'Flow path', $plural = 'Flow paths', $descriptive = 'Path definition' ;

	public static $fielddefs = array(
		 'role'=>array('name'=>'role', 'label'=>'Role permitted', 'class'=>'e_role', 'type'=>'refer')
		,'action'=>array('name'=>'action', 'class'=>'t_flow_action', 'label'=>'Action')
	);
}
