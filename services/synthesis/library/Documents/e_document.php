<?php
/**
* A document is stored in the database as a large object block (TEXT or BINARY depending on whether mimetype ~= ^text/)
* We keep track of its original filename to suggest it the same way on download.
*
*/
class e_document extends Element
{
	public static $table = 'doc_document',$singular = "Document", $plural = "Documents", $descriptive = "Document properties",
		$fielddefs = array(
			,'name'=>array('name'=>'name', 'class'=>'t_string', 'label'=>'Title', 'sort'=>'ASC', 'identifying'=>true )
			,'description'=>array('name'=>'description', 'class'=>'t_text', 'label'=>'Description', 'identifying'=>true )
			,'updated'=>array('name'=>'updated', 'class'=>'t_datetime', 'label'=>'Updated', 'sort'=>'DESC', 'readonly'=>true , 'identifying'=>true )
			,'added'=>array('name'=>'added', 'class'=>'t_datetime', 'label'=>'Added', 'sort'=>'DESC', 'readonly'=>true )
			,'authored'=>array('name'=>'authored', 'class'=>'t_datetime', 'label'=>'Authored', 'sort'=>'DESC')
			,'content'=>array('name'=>'storage', 'class'=>'t_docdata', 'label'=>'Content')
			,'mimetype'=>array('name'=>'mimetype', 'class'=>'t_mimetype', 'label'=>'Type', 'sort'=>'ASC', 'readonly'=>true)
			,'size'=>array('name'=>'size', 'class'=>'t_integer', 'label'=>'Size', 'sort'=>'DESC', 'readonly'=>true)
			,'md5'=>array('name'=>'md5', 'class'=>'t_string', 'label'=>'MD5 Hash', 'readonly'=>true )
			,'saveas'=>array('name'=>'saveas', 'class'=>'t_string', 'label'=>'Filesystem name',
				'help'=>"You can optionally provide the name which this document should suggest for itself when it is downloaded to be saved on a computer.")
			,'public'=>array('name'=>'public', 'class'=>'t_boolean', 'label'=>"Share with Public",
				'help'=>'Select this if you want to be able to freely share the URL to this document with others')
		);

	public function formatted()
	{
		return $this->name;
	}

	public function getFieldDefs( $c = null, array $exclude = array(), array $include = array() )
	{
		if (!$c && !$c->mode)
			return parent::getFieldDefs();

		if ($c->mode == $c::INPUT) // various fields are simply not editable
			$exclude = array_merge((array)$exclude, array('size','updated','added','storage'));

		if ($c->mode != $c::VERBOSE) // the following fields only apply in complete scenarios with real estate
			$exclude = array_merge((array)$exclude, array('md5'));

		// Exclude fields which are not well-rendered in small areas
		if ($c->mode == $c::INLINE || $c->mode == $c::COLUMNAR)
			$exclude = array_merge((array)$exclude, array('description'));

		// Here we further constrain the max include list depending on the action we're in
		if (!count($include)) {
			if ($c->_action == 'base:action:display') {
				if ($c->_subaction == 'relative_records_table')
					$exclude = array_merge((array)$exclude, array('updated','added','authored','flags'));
			}
		}

		return parent::getFieldDefs($c, $exclude, $include);
	}

	public function store( )
	{
		$this->trackChanges("store e_document");
		$this->registerChanges();
		try {

/* BEGIN LEGACY CODE from "BEFORE_CREATE" scenario
	// obtain the uploaded file... find an appropriate name for it, move it there, and set that name in the storage value
	// set the size, hash, type, add & modify dates, and if available set the saveas.
		$upfile =& $_FILES['storage'];
		if ($upfile['error'] === UPLOAD_ERR_OK) {
			// A posting agent is expected to provide name, description, and authored values if it can.
			// We take care of the rest of the element deriving values from the uploaded file itself.
			$data['saveas'] = $upfile['name'];
			$data['mimetype'] = $upfile['type'];
			$data['size'] = $upfile['size'];
			$data['md5'] = md5_file($upfile['tmp_name']);
			// At this point if you were nice you could do a quick scan of the database for any existing simpledoc with the same hash
			// and if you find one and other items check out about it, throw a VerifyPostX being sure to share the name and ID.
			global $sysroot;
			$ext = strrchr($upfile['name'], '.');
			$class = $_SESSION['docClass'] ? "$_SESSION[docClass]/" : null;
			while (file_exists("$sysroot/doc/". ($data['storage'] = $class. uniqid('sDoc-') . $ext)));
			$data['added'] = ($data['updated'] = date('Y-m-d H:i:s'));
		} else
			capcel_error('bad file upload; error code is '. $_FILES['storage']['error']);
		return $upfile['tmp_name'];  // feeds to following ACU

// AND LEGACY CODE from AFTER_CREATE/UPDATE scenario:
	if ($tmpfile)
		if (!move_uploaded_file($tmpfile, "$sysroot/doc/$data[storage]"))
			throw new Exception('Failed to ingest uploaded file');

*/
		} catch (Exception $ex) {
			$this->abortChanges();
			throw $ex;
		}
		$this->commitChanges();
	}
}

/**
	<type name="content" render="doc/types:content_type">
		<storage mysql="VARCHAR(255)"/>
		</type>

function content_type( $cx, $field, $data )
{
	// Right now we only allow for create/upload input
	if ($cx['class'] == 'create')
		return '<input class="input" type="file" name="'. $field['name'] .'"/>';
	$args['filepath'] = $data[$field['name']];
	if ($data['mimetype'])
		$args['mimetype'] = $data['mimetype'];
	if ($data['saveas'])
		$args['saveas'] = $data['saveas'];
	return '<a href="getdoc.php'. capcel_expect_trigger($args) .'&amp;sk='. $_SESSION['sessionkey'] .'" target="docview">( View it ! )</a>';
}

*/
abstract class t_docdata extends Type
{
}
