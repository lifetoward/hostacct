<?php
/**
* This ActionResult subclass is used when input is being accepted in the action. It takes care of the following standard things:
* 	- It wraps the entire content area of the action in a form with ID "actionform"
*	- It provides rendering parameters "readonly" and "disabled" which are to be interpreted by field renderings with mode = INPUT.
* 	- It sets a trap on the submit event on the form and checks to see whether default submit behavior has been prevented, popping up an alert to fix the form content if so.
*	- It sets the focus on the first input field in the form after rendering in the browser.
*	- It provides a JS trigger generator which includes interactive confirmation logic
*	- It provides a convenience function for rendering a single field of an instance as structured input control.
* Actions which accept input worth hanging onto even while under edit should use this Action subclass to handle triggers and (future) obtain autosave capabilities.
*
* All original code.
* @package Synthesis
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2007-2015 Lifetoward LLC
* @license proprietary
*/
class InputRendering extends HTMLRendering
{
	// Actions should set these non-session-persistent rendering parameters before delegating rendering
	protected $readonly = null, $disabled = null; // we return these rendered and set them as boolean

	public function __set( $name, $value )
	{
		if (in_array($name, ['readonly','disabled']))
			$this->$name = $value && true;
		else
			parent::__set($name, $value);
	}

	public function __get( $name )
	{
		if ('readonly' == $name)
			return $this->readonly ? "readonly=\"1\"" : null;
		// Some types of input renderings work better as disabled when attempting to demonstrate read-only
		// If that's what you need, get this disableRO property instead of the standard readonly.
		if ('disableRO' == $name)
			return $this->readonly ? "disabled=\"1\"" : null;

		if ('disabled' == $name)
			return $this->disabled ? "disabled=\"1\"" : null;

		return parent::__get($name);
	}

	protected function getInputForm( $innerHTML )
	{
		return '<form method="post" action="'. htmlentities($this->context->target(['post'=>true], true)) .'" id="actionform" role="form">'. "\n$innerHTML\n</form>\n";
	}

	protected function getAssembledContent( )
	{
		return $this->getInputForm(parent::getAssembledContent());
	}

	public function __construct( Context $c, Result $prior = null, Instance $focus = null )
	{
		parent::__construct($c, $prior, $focus);
		$this->addReadyScript("$('form#actionform').on('submit',function(e){if(e.isDefaultPrevented())alert('Please correct or complete the marked fields, then try again.')})",
				"instance update form validation");
		$this->addReadyScript("$('#body-container :input:first').focus().select();", "focus on first input field");
	}

	/**
	* We add a validate paramenter to the trigger-making call because sometimes we care that the input is whole and other times we don't.
	* @param boolean $validate If the current action accepts input, this flag can indicate whether the target should be triggered even if the input form is not valid in the UI.
	*/
	public function getJSTrigger( $target, $confirm = false, $vmsg = "doing this", $validate = true )
	{
		if ($confirm)
			$conf = "if(confirm('Proceed with $vmsg?'))";
		$offValidation = $validate ? null : ".off('submit')";
		return "\$('form#actionform')$offValidation.prop('action','$target').trigger('submit')";
	}

	// This keeps track of when we need to make row breaks for columnar output chunks.
	// If the number of columns requested by the fielddef passed to renderInputField() plus this value together exceeds 12,
	// we make a row break. The trick is that we can't know all situations when this might be reset. For example,
	// if you render some fields and then render a big block, and then render some more field, you'd need to reset
	// this to prevent an unnecessary row break from appearing and messing up the DOM.
	// That's why this is public. If you "break in" to the natural field rendering sequence with intervening content,
	// reset this to 0.
	public $columnsInCurrentRow = 0;

	/**
	* A typical input form can render one of its fields with this.
	* These are intended to render as 1 or 2 columns responsively via Bootstrap's 12-col model.
	* The assumed width is therefore 6, but this can be overridden by setting 'width'=>[1-12] in the fielddef.
	* @param Instance $x The Instance object being rendered.
	* @param array $fd The field definition array for the field to render.
	* @return string HTML rendering of one
	*/
    public function renderFieldWithRowBreaks( Instance $x, array $fd )
    {
		if (!($cols = $fd['width']))
			if (!($cols = constant("$fd[class]::InputWidth")))
				$cols = 6;
		if ($cols + $this->columnsInCurrentRow > 12) {
			$rowBreak = "</div><div class=\"row\">\n";
			$this->columnsInCurrentRow = 0;
		}
		$this->columnsInCurrentRow += $cols;
		return "$rowBreak<div class=\"col-md-$cols\">". $this->renderFieldInForm($x, $fd) ."</div>";
    }

	/**
	* A typical input form can render one of its fields with this.
	* These are intended to render as 1 or 2 columns responsively via Bootstrap's 12-col model.
	* The assumed width is therefore 6, but this can be overridden by setting 'width'=>[1-12] in the fielddef.
	* @param Instance $x The Instance object being rendered.
	* @param array $fd The field definition array for the field to render.
	* @return string HTML rendering of one
	*/
    public function renderFieldInForm( Instance $x, array $fd )
    {
		$fn = $fd['name'];
		$this->addStyles(".form-group>div.form-control { border:none; box-shadow:none; height:inherit; } .form-group { margin-bottom:1em; min-height:5em; }", 'form rows');
		if ($fd['help'])
			$helper = '&nbsp;<span class="glyphicon glyphicon-info-sign" title="'. htmlentities($fd['help']) .'" style="color:lightblue"></span>';
		return "<div class=\"form-group\"><label for=\"$this->idprefix$fn\">{$x->{¬.$fn}}$helper</label><div class=\"form-control\">{$x->{».$fn}}</div></div>";
    }
}
