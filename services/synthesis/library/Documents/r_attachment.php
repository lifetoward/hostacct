<?php
/**
* An attachment associates a simple document object about which the system is agnostic of the contents with any other element in the system.
* We maintain just enough metadata to provide or render the document to a suitable client in a suitable fashion.
*/
trait i_attach
{
	public static $fielddefs = array(
	);
}

class r_attachment extends Relation
{
	public static $table = 'doc_attach', $keys = array('focus'=>'e_attachpoint', 'doc'=>'e_document'), $singular = 'Attachment', $plural = 'Attachments', $descriptive = 'Attachment';
}

class r_attachedto extends Relation
{
	public static $table = 'attach', $keys = array('doc'=>'e_document','focus'=>'e_attachpoint'), $singular = 'Attached to', $plural = 'Attached to', $descriptive = 'Attachment';
}
