<?php
/**
* a_ProducePayTaxReports
* This action produces the reports required by all relevant tax authorities according to their reporting schedule.
*
* Created: 11/30/14 for Lifetoward LLC
*
* All original code.
* @package Synthesis/Payroll
* @author Biz Wiz <bizwiz@SyntheticWebApps.com>
* @copyright (c) 2014 Lifetoward LLC; All rights reserved.
* @license proprietary
*/
class a_ProducePayTaxReports extends Action
{
	protected $example; // Persistent action properties are handled like this
	private static $savedProperties = array('example');
	use SerializationHelper;

	public function __construct( Context $c, $args = null )
	{
		parent::__construct($c);
		// Initialize based on contruction context
		$this->example = "Initialized value";
	}
	
	protected function render_me( ActionResult $returned = null )
	{
		$c = $this->context;
		extract($c->request, EXTR_PREFIX_INVALID, 'r');
		
		if ($returned) {
		
			// Handle returning sub-actions...
			
		} else if ($args['example'] = 'do_something') {
		
			// Process args or other input conditions
			
		} else if ($args['exit'])
			return new ActionResult(ActionResult::CANCEL);
		
		else if ($args['operate']) {
			// In this example we handle operation triggers by saving pending input and then calling a subAction.
//			try { $this->instance->acceptValues($post, $this->context); } catch (BadFieldValuesX $ex) { } // we don't care about bad values right now... we take what we can get and proceed to allow further editing later.
//			if ($this->subAction = static::newOperation($args['focus'], $args['operation'])) // assignment intended
//				return; // allow caller to perform subaction manipulation
		}

		$result = new ActionResult; // Accumulate results into this object
		
		// Your action code here.
		
//		$result->addReadyScript("/* ecmascript */", __CLASS__);
//		$result->addStyles("div { }", __CLASS__);
	
		return $result->prep(ActionResult::PROCEED, <<<html
<!-- This is the final rendering -->
html
		);
	}
	
	// Supporting methods...
	
}
