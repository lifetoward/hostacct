<?php
/**
* Any element which can serve as the central vehicle in a workflow is known as a "flow focus".
* Flow foci are notable in that they have a status which can only be updated through defined workflow actions.
* In addition and as a consequence of the preceding, a flow focus also retains a document historical record of the actions taken on the document and by whom.
*/
class e_flow_focus extends Element
{
	public static $table = 'flow_focus', $singular = 'Flow control object', $plural = "Flow control objects", $descriptive = "Workflow data",
		$fielddefs = array(
			 'flow'=>array('name'=>'flow', 'class'=>'e_flow', 'type'=>'require', 'label'=>'Workflow', 'readonly'=>true)
			,'status'=>array('name'=>'status', 'class'=>'t_flow_state', 'label'=>'Status', 'readonly'=>true)
			,'log'=>array('name'=>'log', 'class'=>'t_flow_log', 'label'=>'Form history', 'readonly'=>true)
		);
}

