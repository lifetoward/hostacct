<?php
/**
* A form is a document of record. It:
*	- Collects its data according to a user-configured instance specification
* 	- Stores its instance data as a serialized instance object
*	- Is a "flow doc": Has workflow states and actions defined for, which, when triggered, are logged in its history.
*	- Cannot be modified once created, but new versions can be cloned from existing versions.
*/
class e_Form extends Element
{
	public static $table = 'doc_form';
	public static $fielddefs = array(
		 'title'=>array('name'=>'title', 'class'=>'t_string', 'label'=>'Title', 'required'=>true, 'identifying'=>true)
		,'instructions'=>array('name'=>'instructions', 'class'=>'t_richtext', 'label'=>'Form instructions',
				'help'=>"This information is presented to the creator of a form when they first engage with it, and is available to review throughout their use of it.")
		,'formdata'=>array('name'=>'formdata', 'class'=>'t_formdata', 'label'=>'Form content')
		,'focus'=>array('name'=>'focus', 'class'=>'e_flow_focus', 'type'=>'include', 'label'=>'Workflow handle')
	);

}

class t_formdata extends Type
{
}
