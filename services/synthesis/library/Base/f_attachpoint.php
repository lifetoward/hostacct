<?php
/**
* An attachpoint provides a database-compatible attachment linkage for any other object. Just include this to be able to have attachments.
*
* All original code.
* @package Synthesis/Finance
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
class f_attachpoint extends Fieldset
{
	public static $table = 'attachpoint', $singular = "Attachments", $plural = "Attachment points", $descriptive = "Attachment point",
		 $description = "An attachpoint provides a database-compatible attachment linkage for any other object. Just include this to be able to have attachments."
		,$relatives = array()
		,$fielddefs = array(
			 'slug'=>array('name'=>'slug', 'class'=>'t_string', 'pattern'=>"^[\\w]*$", 'label'=>'Filing label', 'readonly'=>true,
				'help'=>"This value is used like the label of a file folder. It will be used in links, directory names, etc. It should be short and easily recognized as representing the item or type of items to which documents are attached. Only letters, digits, and underscores may be used.")
			,'attachments'=>array('name'=>'attachments', 'derived'=>"NULL", 'label'=>'Attachments')
		);

	public function renderField( $fn, HTMLRendering $R, $format = null )
	{
		if ($fn == 'attachments') {
			$R->addStyles("div.attachments { padding:2pt 6pt; }","attachments control");
			return '<div class="attachments form-control">Attachments are coming soon...</div>';
		}
		return parent::renderField($fn, $R, $format);
	}
}
