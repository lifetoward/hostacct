<?php

class a_Delete extends Action
{
	protected $instance; // the item deleted (yes it still exists)

	use SerializationHelper;
	private static $savedProperties = array('instance');

	public function getResult( $status = 0 )
	{
		return array_merge(parent::getResult($status), array('instance'=>$this->instance));
	}

	public function __construct( Context $c, Instance $x )
	{
		parent::__construct($c);
		$this->instance = $x;
	}

	public function render_me( $notice = null )
	{
		list($args, $post) = $c->request;

		// Legally and safely delete the object.
		// Process postings
		if ($args['_act_'] == 'post') {
			try {
				if (!$this->instance->acceptValues($post, $c))
					return new HTMLRendering(HTMLRendering::COMPLETE, '<p class="notice">No changes were made.</p>');
				$this->instance->store();
				return new HTMLRendering(HTMLRendering::SUCCEED, "<p class=\"notice\">The record for $this->instance was updated.</p>");
			} catch (BadFieldValuesX $ex) {
				$problems = $ex->problems;
				// fall thru to re-rendering the input form
			} catch (Exception $ex) {
				$c->dbAbort();
				logError($ex);
				return new HTMLRendering(HTMLRendering::FAIL, '<p class="notice">We encountered a problem storing the information. No changes were made.</p>');
			}
		} else if ($args['exit'])
			return new HTMLRendering(HTMLRendering::CANCEL);

		return new HTMLRendering(HTMLRendering::PROCEED, '<p class="notice">We encountered a problem storing the information. No changes were made.</p>');
	}

}
