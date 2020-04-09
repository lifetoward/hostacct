<?php

class TrxRendering extends InputRendering
{
	public function __construct( Context $c, Result $prior = null, Instance $focus )
	{
		parent::__construct($c, $prior, $focus);
		// this undisables all the input controls before submitting so they will post; you can require validity or not
		$this->addScript(<<<jscript
function triggerAction(chk,conf,target,vmsg) {
	if(chk&&(!validate_trx()||$('#actionform .has-error').length>0))
		return alert("This transaction is not complete and balanced. Please complete it before "+vmsg+" it.");
	if(conf&&!confirm("Proceed with "+vmsg+" this transaction?"))
		return;
	$(':input','#actionform').prop('disabled',false); // undisable so even blocked inputs post so we have a complete set of entries
	$('#actionform').off().prop('action',target).submit(); // if we're here, we're adequately validated so we remove submit prevention handlers
}
jscript
			, 'input form trigger action');
	}

	static $actionOps = [
		 'store'=>['operation'=>'store', 'tone'=>'success', 'icon'=>'ok', 'label'=>"Commit", 'validate'=>true, 'vmsg'=>"Posting"]
		,'iterate'=>['operation'=>'iterate', 'tone'=>'primary', 'icon'=>'step-forward', 'label'=>"Duplicate", 'validate'=>false, 'vmsg'=>"Duplicating"]
		,'delete'=>['operation'=>'delete', 'tone'=>'danger', 'icon'=>'remove', 'label'=>"Delete", 'confirm'=>true, 'validate'=>false, 'vmsg'=>"Deleting"]
		];

	// This is called by the button renderers to get something with a target, etc.
	public function getActionOp( $tag )
	{
		$op = static::$actionOps[$tag];
		$op['target'] = $this->context->target([], true, 'operation', $tag);
		return Instance::extendOpSpec($op);
	}

	public function addFocalTrigger( $op, $disabled = false, $tabindex = 305 )
	{
		$this->tabindex = $tabindex;
		$this->triggers[] = $this->renderOperationAsButton($this->getActionOp($op), $disabled, 'lg');
	}

	// The common trigger rendering methods will call this to get the JS trigger... ours is specially designed
	public function getJSTrigger( $target, $confirm = false, $vmsg = "doing so", $validate = false )
	{
		$confirm = $confirm ? 'true' : 'false'; // get rendered into jScript
		$validate = $validate ? 'true' : 'false'; // get rendered into jScript
		return "triggerAction($validate,$confirm,'$target','$vmsg');";
	}

	/**
	* We do 2 things not handled by a typical InputRendering:
	* 1. We provide a place to put "template" HTML which should not appear inside the overall input form of the panel.
	* 2. We render our triggers differently, ie. at the bottom of the page as part of the body.
	*/
	protected function getAssembledContent( )
	{
		$templates = $this->templates ? '<div class="templates" style="display:none">'. $this->templates ."</div>\n" : null;
		$triggers = "<div id=\"triggers\">". implode("&nbsp; ", $this->triggers) .'</div>';
		$this->addStyles("div#triggers { margin:auto; text-align:center; }\ndiv#triggers button { width:10pc; }", 'a_Trx triggers styles');
		return $templates . parent::getInputForm($this->getFixedHeader($this->header) . $this->getDisplacedBody("$this->content\n<hr/>\n$triggers"));
	}
}
