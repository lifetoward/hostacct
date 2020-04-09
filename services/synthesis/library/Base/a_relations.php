<?php
/**
* a_Relations
* Full-detail viewing of relation records.
*
* Created: 12/30/14 for Lifetoward LLC
*
* All original code.
* @package Synthesis/Base
* @author Biz Wiz <bizwiz@SyntheticWebApps.com>
* @copyright (c) 2014 Lifetoward LLC; All rights reserved.
* @license proprietary
*/
class a_Relations extends Action
{
	protected $focus; // Persistent action properties are handled like this
	private static $savedProperties = array('focus');
	use SerializationHelper;

	public function __construct( Context $c, $args = null )
	{
		parent::__construct($c);
		// Initialize based on contruction context
		$this->focus = "Initialized value";
	}
	
	protected function render_me( HTMLRendering $returned = null )
	{
		$c = $this->context; // this is just for shorthand... our parent set this up for us.
		extract($c->request, EXTR_PREFIX_INVALID, 'r');
		
		$R = new HTMLRendering($c); // Accumulate results into this object
		$focalClass = get_class($this->focus);
		$hints = $focalClass::$hints[__CLASS__];
		
		if ($returned) {
		
			// Handle returning sub-actions...
			
		} else if ($args['example'] = 'do_something') {
		
			// Process args or other input conditions
			
		} else if ($args['exit'])
			return new HTMLRendering(HTMLRendering::CANCEL);
		
		else if ($args['operate']) {
			// In this example we handle operation triggers by saving pending input and then calling a subAction.
//			try { $this->instance->acceptValues($post, $this->context); } catch (BadFieldValuesX $ex) { } // we don't care about bad values right now... we take what we can get and proceed to allow further editing later.
//			if ($this->subAction = static::newOperation($args['focus'], $args['operation'])) // assignment intended
//				return; // allow caller to perform subaction manipulation
		}

		// Your action code here.
		
//		$R->addReadyScript("/* ecmascript */", __CLASS__);
//		$R->addStyles("div { }", __CLASS__);
	
		return $R->prep(HTMLRendering::PROCEED, <<<html
<!-- This is the final rendering -->
html
		);
	}
	
	// Supporting methods...
	
}
