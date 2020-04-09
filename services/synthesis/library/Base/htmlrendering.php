<?php
// namespace Lifetoward\Synthesis\_
/**
* Any method throughout the system with "render" in its name is intended to work with an object of this class to
* produce HTML content.
* Its methods facilitate accumulating all the pieces of an HTML document and finally rendering that complete document via $R->outputDocument().
* Nothing about this object persists across multiple requests... it's entirely for assembling the result of a single request; it is not persistent in the session.
*
* All original code.
* @package Synthesis
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2007-2015 Lifetoward LLC
* @license proprietary
*/
class HTMLRendering extends Result
{
	/**
	* When rendering an HTML action, typically you will render a commonly structured (consider "themed") set of semantic elements nested within the body.
	* That body will be accompanied by some supporting scripts and styles as carried in the document header (<head>).
	* The accumulators listed below assemble those head items, as those cannot be rendered except with the whole document.
	* You could decide to directly set the content value to represent the entirety of the body portion.
	* However, it's suggested that you use these custom values in addition to or instead of the content property:
	*	- header - This is the content which will be rendered in a fixed (non-scrolling) portion of the action-specific visible area. Typically composed of an <h1></h1>
	*	- triggers - These are actually operation specifications which will be rendered
	*	- datahead - If you're rendering tabular data in columns, set this to the rendered aspects to appear inside (not including) the <th> elements. This is a simple numerically-indexed array of column headers.
	*	- databody - Provide the data rows to match the datahead here. This is a two-level numerically-indexed matrix of information, columns within rows.
	*	- classes - (Optional) Our content renderer recognizes indexes 'action' (for the overall container), 'table' for both the head and body tables, 'row' for any row in the body.
	*		Numerically indexed portions of this array are used in conjunction with matching columns in datahead and databody, i.e. both the <th> and <td> elements of the table header and data rows respectively.
	* 	- content - Any content you do set will appear BELOW any data in databody.
	* In summary, when one gets $R->content, one gets a <div class="container action {classes[action]}">...</div> inside of which are all of these subportions duly assembled.
	*/
	// The following constants are the rendering modes and are used to populate $this->mode
	const
		  INPUT = 10 // when we want an fully editable user interface for the field
		, INLINE = 0 // when we want a simple rendering which can flow inline with text or stand alone as a concise string
		, VERBOSE = 12 // when we want a complete and perhaps spaciously elaborate rendition
		, COLUMNAR = 13 // when rendering things in columns, ie. one row per record, one field per column
		; // Note that when a general purpose renderable string representation is desired, you'd pass null or no value at all as the mode to renderField(); usually this will equate to INLINE

	// Actions should set these non-session-persistent rendering parameters before delegating rendering
	public $idprefix = null; // just an arbitrary HTML attribute value-compatible string; we provide no enforcement of syntactic suitability
	protected $tabindex = null // we return this rendered and set it as numeric
		, $accumulators = [
			  'links' => [] // The external content items which are typically accessed using a link element in the header of a frame
			,'scripts' => [] // Javascript content which is placed simply in the header to run as it's read within the document
			,'onready' => [] // Javascript content which is placed inside the JQuery "ready" function, ie. to execute AFTER the document completes loading
			,'styles' => [] // CSS style content relevant to this action... note that some of this may be duplicate with nesting actions
			// Note that the parent->content attribute is interpreted as the "HTML belonging in an appropriately nested portion of the body of the document"
			]
		;

	// Actions should set these non-session-persistent attributes before delegating rendering
	public $mode = self::INLINE
		, $datahead = null // If one populates this with a list, it enables the fixed-header/scrollable-body tabular data rendering feature.
		, $databody = null // If one populates the datahead, one should also populate this with a list of rows of data
		, $dataheadrow = null // You can choose to populate this instead of datahead if you want more specific control of how the content maps into the table elements (tr, th)
		, $databodyrows = null // You can choose to populate this instead of databody if you want more specific control of how the content maps into the table elements (tr, td)
		, $triggers = [] // Another array accumulator... append your rendered triggers to this
		, $cancel = [] // This gives you something to override the rendering of the "abort" or "cancel" button, which is automatically included with the triggers unless you set this to null (not array).
		;
	protected $context; // A rendering context is required on construction. Without it targets and other important facilities couldn't be available.

	public function __set( $name, $value )
	{
		if ($name == 'tabindex')
			$this->tabindex = $value * 1;
		else
			parent::__set($name, $value);
	}

	public function __get( $name )
	{
		if ($name == 'context')
			return $this->context;

		if ('tabindex' == $name)
			return $this->tabindex ? "tabindex=\"$this->tabindex\"" : null;

		if ('copyright' == $name)
			return '<p class="subito" id="smallprint">'."&copy; 2004-". date('Y') ." Lifetoward LLC</p>\n";

		if ('content' == $name)
			return $this->getAssembledContent();

		return parent::__get($name);
	}

	/**
	* @param Context $c Provide the rendering context.
	* @param Result $prior Provide the Action which returned control to the current action, if any.
	* @param Instance $focus If there is a focal instance, provide it here, initializing our return values.
	*/
	public function __construct( Context $c, Result $prior = null, Instance $focus = null )
	{
		$this->context = $c;
		if ($prior)
			$this->subResults = [$prior];
		$this->focus = $focus;
		$this->triggers = []; // triggers is for panel-level triggers, which we place according to the panel template (getAssembledContent())
	}

	/**
	* This method allows you to merge all of the accumulators from (what's usually a nested Action's) HTMLRendering into this result.
	* Note that this is quite a different matter than merely having a subResult, which are meant to notify for terminations and other messages.
	* We incorporate the focus value from the passed HTMLRendering only if we don't have one set ourselves already.
	* We merge in all subResults as preceding peers of our own.
	* We merge in all accumulators for the HTML head.
	* We only merge in content (by appending it to our own) if you tell us to by passing true as the second parm.
	* It may be just as easy in many cases to simply get the subresult and use it as your own, ie. $R = $this->subAction->render($c);
	* 	The reason not to do this (and rather to use this method) is if you're already building your own result and the subaction's result may not be final, so you're saving it until you know you want to use it.
	* @param HTMLRendering $inner The renderable result (usually of a subAction) which you'd like to incorporate into this result.
	* @param boolean $content Normally we do NOT attempt to merge the content, but if you'd like us to append the incorporated (assembled) content to existing content, set this to true.
	* @return HTMLRendering - We return $this as a convenience. The side effect is that this result is now merged with the provided result.
	*/
	final public function incorporate( HTMLRendering $inner, $content = false )
	{
		// IMPORTANT: We MUST invoke the assembling routines before incorporation because they typically include updates to accumulators, triggers, etc.
		$subContent = $inner->getAssembledContent();
		$inner->renderNotices();

		if (!$this->focus)
			$this->focus = $inner->focus;
		$this->subResults = array_merge($inner->subResults, $this->subResults);
		$this->accumulators = array_merge_recursive($this->accumulators, $inner->accumulators);
		if ($content)
			$this->content .= $subContent;
		// Notice that we don't merge the custom array nor datahead, databody, or triggers. Those are meant for "self use only", ie. to support assembling content as returned by getAssembledContent()
	}

	/**
	* We render notices using the following approach:
	* We render each notice independently as a block-level element all returned in a flat list array.
	* It's up to the caller to place these in an appropriate place in the document.
	* Order notices depth-first in order that they are added to each nested result.
	* Let each Notice render itself, trusting them to follow a convention:
	* 	Each notice is expected to be rendered as a block-level element.
	*	Each notice should provide for its own indepedent dismissal by timer or manual depending on its importance.
	*/
	public function renderNotices( )
	{
		if (!count($this->subResults))
			return null;
		foreach ($this->subResults as $result)
			$noticeList = array_merge((array)$noticeList, (array)$result->render());
		return $noticeList;
	}

	/**
	* We process these purpose-specific properties:
	* 	- triggers, which should be populated with ready-to-use operation arrays
	*	- cancel, which, if an array (could be empty), will cause a cancelation button using standard triggering to be rendered at the end of the triggers
	*/
	protected function getAssembledTriggers( )
	{
		if (is_array($this->triggers))
			foreach ($this->triggers as $op)
				$triggers .= " ". $this->renderOperationAsButton($op);
		if (is_array($this->cancel) && !$this->context->nocancel)
			$triggers .= " ". $this->renderCancelButton($this->cancel);
		return $triggers;
	}

	/**
	* Content renderings which like to fix the header at the top of the panel leaving the body to scroll independent of it
	* can call this to get such a header and then call getDisplacedBody() to get a body element that works with it.
	* @param string $innerHTML The HTML content of the header itself. Block-level wrapping is not required, but semantic and formatting tagging is correct to include.
	* @return string Populated HTML header element with a container inside and your content inside that.
	*/
	protected function getFixedHeader( $innerHTML )
	{
		return '<header style="position:fixed; z-index:100; width:100%; padding-top:0.5em; background-color:white">'.
			"<div class=\"container\" id=\"header-container\">\n$innerHTML\n</div>\n</header>\n";
	}

	/**
	* To have a scrolling body under a fixed header, call this passing the body content you want to scroll.
	* @param string $innerHTML The content of your body, which will scroll if the viewable area is too short.
	* @param string $resizeScript If you have code that needs to be executed when the window area is resized, you can pass it here
	*		and we will stick it into the resize handler along with our own stuff.
	* @return string Populated HTML body consisting of your content wrapped in a container div.
	*/
	protected function getDisplacedBody( $innerHTML, $resizeScript = null )
	{
		$this->addReadyScript(<<<js
$(window).resize(function() { // Thanks to http://jsfiddle.net/hashem/crspu/555/
$resizeScript
	// Set the vertical offset of the content within the body and below the fixed header
	$('body div.container:not(#header-container)').css('top',$('header').height()).css('position','relative');
}).resize(); // Trigger resize handler now too
js
			, 'fixed content header with table');
		return "<div class=\"container\" id=\"body-container\">\n$innerHTML\n</div>\n";
	}

	/**
	* Our approach to assembling a header is to float the panel-level triggers in the upper right of your header content
	* 	inside a fixed header which will stick with the surrounding frame rather than scrolling with the body content.
	* We also include the nifty tabular data capabilities which allow your fixed header to include properly sized column headers.
	* We use the following local custom variables to determine the content.
	*	- header, which is expected to contain the titular text which describes the body content for the user. The header will remain stationary at the top of the panel while the remainder of the body scrolls as needed.
	*	- datahead OR dataheadrow, which is only needed if columnar data is being displayed within the fixed header.
	*		If you dataheadrow contains a string, datahead will be ignored and the dataheadrow will be assumed to contain a <tr> with <th> subelements rendered out.
	*		Setting datahead makes the job of the caller even easier because it need only be an list of content strings used to populate each of the <th> elements in the header row.
	*		This second approach using datahead means all tabular markup is handled here rather than by the Action.
	*/
	protected function getAssembledHeader( )
	{
		if (!$this->header && !$this->datahead && !$this->dataheadrow)
			return null;
		if ($triggers = $this->getAssembledTriggers()) // assignment intended
			$triggers = '<span style="float:right" id="focal-triggers">'. "$triggers</span>\n";
		if ($this->dataheadrow || (is_array($this->datahead) && count($this->datahead)))
			$datahead = "<table class=\"table table-responsive {$this->classes['table']}\">\n<thead>".
				($this->dataheadrow ? : "<tr class=\"{$this->classes['row']}\"><th>". implode("</th>\n<th>", $this->datahead) .'</th></tr>') ."</thead></table>";
		return static::getFixedHeader("$triggers$this->header$datahead");
	}

	/**
	* In general we're just going to wrap $this->content in a container.
	* However, we also permit you to set up a tabular data set coordinated with a fixed header.
	* If you've defined a tabular header with $this->datahead or $this->dataheadrow, we'll also render a data table in the top of the body content and link it with scripting
	* 	to the matching header table to produce a fixed tabular header with scrolling data content.
	* We use $this->databodyrows (if it exists, a list of strings) or databody (a list of lists, each sublist matching element-for-element datahead[]).
	*	- $this->content is always rendered, but after the tabular data if any.
	*/
	protected function getAssembledBody( )
	{
		// Note that we only consider databody or databodyrows if you've already set a datahead or dataheadrow
		if ($this->dataheadrow || (is_array($this->datahead) && count($this->datahead))) {
			if (is_array($this->databodyrows)) {
				if (count($this->databodyrows))
					$databodyrows = $this->databodyrows;
			} else if (is_array($this->databody) && count(reset($this->databody)))
				foreach ($this->databody as $rowdata)
					$databodyrows[] = '<tr class=""><td>'. implode('</td><td>', $rowdata) ."</td></tr>\n";
			if (!$databodyrows)
				$databodyrows = ['<tr><td colspan="'. count($this->datahead) .'" align="center">( No data. )</td></tr>'."\n"];
			$databody = '<table class="table table-striped table-hover table-responsive '. $this->classes['table'] ."\"><tbody>\n". implode("\n", $databodyrows) ."\n</tbody>\n</table>\n";
			$tableHeaderAlignment = <<<js
	// Get the tbody columns width array
	var colWidth = $('div#body-container table.{$this->classes['table']}').find('tbody tr:first').children().map(function() { return $(this).width(); }).get();
	// Set the width of thead columns
	$('div#header-container table.{$this->classes['table']}').find('thead tr').children().each(function(i, v) { $(v).width(colWidth[i]); });\n
js;
		}
		return $this->getDisplacedBody("$databody\n$this->content", $tableHeaderAlignment);
	}

	protected function getAssembledContent( )
	{
		// To work with legacy situations and while still allowing use by subpart-ganostic actions, we have this logic:
		// If you've set a header (as header, datahead, or dataheadrow) then render the triggers inside the header and render the content as the body.
		// Otherwise just render the content because the owning Action can be assumed uninterested in the subparts.
		if ($header = $this->getAssembledHeader()) // assignment intended
			return $header . $this->getAssembledBody();
		return $this->content;
	}

	/**
	* Call this to add CSS to your rendered document. In a Frame this ends up in the <head>
	* @param $styles string The CSS content you need included for your document.
	* @param $label string An identifier which uniquely represents this content. Don't use a numeric label. You should provide a label unless the styles you're adding are dynamic, unique, and not reusable.
	* @return void
	*/
	final public function addStyles( $styles, $label = null )
	{
		if (is_string($label) && !is_numeric($label))
			$this->accumulators['styles'][$label] = $styles;
		else
			array_push($this->accumulators['styles'], $styles);
	}

	/**
	* Call this to add immediate Javascript content to your rendered document. In a Frame this ends up in the <head>.
	* @parm string $script The Javascript content you need included for your document.
	* @parm string $label An identifier which uniquely represents this content. Don't use a numeric label. You should provide a label unless the styles you're adding are dynamic, unique, and not reusable.
	* @return void
	*/
	final public function addScript( $script , $label = null )
	{
		if (is_string($label) && !is_numeric($label))
			$this->accumulators['scripts'][$label] = $script;
		else
			array_push($this->accumulators['scripts'], $script);
	}

	/**
	* Call this to add Javascript content to your rendered document which will execute after document loading is complete. In a Frame this ends up in the main JQuery $(function () { ... }); body
	* @parm string $script The Javascript content you need included for your document.
	* @parm string $label An identifier which uniquely represents this content. Don't use a numeric label. You should provide a label unless the styles you're adding are dynamic, unique, and not reusable.
	* @return void
	*/
	final public function addReadyScript( $script , $label = null )
	{
		if (is_string($label) && !is_numeric($label))
			$this->accumulators['onready'][$label] = $script;
		else
			array_push($this->accumulators['onready'], $script);
	}

	/**
	* Call this to add immediate Javascript content located within a file to your rendered document. In a Frame this ends up in the <head>
	* @param $filename string The filename containing javascript. The path given must be relative to either $sysroot/app or $sysroot/lib and the file will be sought in that sequence.
	* @return void
	*/
	final public function addScriptFile( $filename )
	{
		if (!file_exists($p="$GLOBALS[sysroot]/app/$filename"))
			if (!file_exists($p="$GLOBALS[sysroot]/lib/$filename"))
				return logWarn("Could not find jscript file \"$filename\"!");
		$label = "*file:$filename";
		if ($this->accumulators['scripts'][$label])
			return;
		$this->accumulators['scripts'][$label] = file_get_contents($p);
	}

	/**
	* Call this to add an external link to a javascript resource.
	* @param $urn string You can specify any URN/URI/path which can be referenced by the client. If you provide a relative path, it will be relative to $url
	* @return void
	*/
	final public function linkScript( $urn )
	{
		$this->accumulators['links'][$urn] = 'jscript';
	}

	/**
	* Call this to add an external link to a CSS resource.
	* @param $urn string You can specify any URN/URI/path which can be referenced by the client. If you provide a relative path, it will be relative to $url
	* @return void
	*/
	final public function linkStylesheet( $urn )
	{
		$this->accumulators['links'][$urn] = 'css';
	}

	/**
	* This finalizes the ultimate result of a runtime instance, ie. should only be called by a Root Action like Frame or its heirs.
	* This should only be done after all content producers have had a chance to contribute to the headers in the form of style sheets, scripts, etc.
	* This should only be done after you write any protocol headers you might need.
	* @return void Prints the entire HTML document to standard out or HTTP.
	*/
	public function outputDocument( )
	{
		$c = $this->context;
		$min = $c->phase == 'prod' ? '.min' : null;
		if ($this->accumulators['onready']) {
			$o = "\$(function(){\n";
			foreach ($this->accumulators['onready'] as $s) // must do this rather than implode because some keys are non-numeric
				$o .= "$s\n";
			$this->addScript("$o});", "Onready scripts");
		}
		foreach ((array)$this->accumulators['links'] as $target=>$type) {
			if ('jscript'==$type)
				$links .= "<script type=\"text/javascript\" src=\"$target\"></script>\n";
			else if ('css'==$type)
				$links .= '<link rel="stylesheet" type="text/css" media="all" href="'."$target\"/>\n";
		}
		foreach ((array)$this->accumulators['scripts'] as $label=>$content)
			$scripts .= ($label ? "\n/* $label */" : null) ."\n$content\n";
		$scripts= "<script>$scripts</script>\n";
		foreach ((array)$this->accumulators['styles'] as $label=>$content)
			$styles .= ($label ? "\n/* $label */" : null) ."\n$content\n";
		$styles= "<style>$styles</style>\n";
		$apptitle = htmlentities($_ENV['ACCOUNT_NAME']);
		$body = $this->getAssembledContent();
		print <<<html
<!DOCTYPE html>
<html lang="en">
<head>
<!--<base href="$c->urlbase" target="_self"/>-->
<title>$apptitle</title>
<meta name="Generator" content="Synthesis Information Management System"/>
<meta name="Copyright" content="Structure and function copyright Guy Mitchell Johnson and Lifetoward LLC 2004-2015; Information content is property of the site owner and may not be reproduced without permission."/>
<meta name="Distribution" content="iu"/>
<meta name="Robots" content="noindex,nofollow"/><meta name="Googlebot" content="noindex,nofollow"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<script type="text/javascript" src="$c->weblib/jquery/core.min.js"></script>
$links$scripts$styles</head>
<body>
$body
</body></html>
html;
	}

	/**
	* Obtain a Javascript statement which will effect triggering a new request to the provided target.
	* This is necessary because different actions require different conditions under which they submit requests.
	* The only inelegance here is that the target must also have been constructed to match to some small degree.
	* A target is really a hybrid gadget requiring args established by both the action and the context it runs in. Selah.
	* @param string $target A raw request string (URL or portion thereof).
	* @param boolean $confirm Whether or not to prompt the user for confirmation before taking action.
	* @param string $vmsg A present-tense-ongoing verb message snippet which can be used in confirmation or validation messages to the user.
	* @return string A Javascript statement as described above.
	*/
	public function getJSTrigger( $target, $confirm = false, $vmsg = "doing this" )
	{
		// Validation doesn't apply in this general case implementation.
		if ($confirm)
			$conf = "if(confirm('Proceed with $vmsg?'))";
		return "{$conf}location.replace('$target');";
	}

	/**
	* Call this to get a customized trigger-renderable operation spec to carry out a "CANCEL" or "EXIT" from the current Action.
	* A Cancel is not a genuine operation at all, but a trigger that produces a set of args which Action->render() can handle peremptorily on behalf of subclasses.
	* A Cancel operation never accepts data or entertains any args except for the special case '_action=CANCEL'.
	* The HTMLRendering generated by the CANCEL handler in Action->render() will have no information and carry return code HTMLRendering::CANCEL.
	* @param string[] $op (optional) If you want to customize how the trigger will render, you can provide some of your own values for the opspec.
	*	We assume a rendering something like this: [ < Go back ] in a blase' tone ('info').
	*	The overridable attributes are icon, label, tone.
	* @param string $size (optional) The Bootstrap-defined 2-char size hint that helps make the class which sets the sizing. We assume 'lg' for large.
	* @return string An HTML-rendered Cancel button which triggers
	*/
	public function renderCancelButton( array $op = [], $size = 'default' )
	{
		extract(Instance::extendOpSpec(array_merge(['icon'=>'chevron-left', 'label'=>"Go back", 'tone'=>'info'], $op)));
		if ($this->tabindex)
			$tabindex = " tabindex=\"$this->tabindex\"";
		$trigger = $this->getJSTrigger($this->context->target(['_action'=>'CANCEL']), $confirm, "abandoning this page", false);
		$this->addReadyScript("$(window).on('keydown',function(e){var x=e.which||e.keyCode;if(x==27){\$('#{$this->idprefix}trigger-CANCEL').click()}});", 'escToCancel');
		return "<button type=\"button\"$tabindex id=\"{$this->idprefix}trigger-CANCEL\" class=\"btn btn-$tone btn-$size\" onclick=\"$trigger\">$glyphicon $label</button>\n";
	}

	/**
	* Call this to obtain an HTML snippet rendering an Operation trigger as a button.
	* @param string[] $op An assembled Operation array as returned from Instance::get[Class]Operation(...).
    * @param boolean $disabled (optional) Pass true if you want the button to begin disabled.
	* @param string $size (optional) The Bootstrap-defined button sizing hint. We assume null ('default').
	* @return string HTML rendering of a Bootstrap Javascript-triggering button
	*/
	public function renderOperationAsButton( array $op, $disabled = false, $size = 'default' )
	{
		extract($op, EXTR_SKIP);
		if ($this->tabindex)
			$tabindex = " tabindex=\"$this->tabindex\"";
		return '<button type="button" id="'."{$this->idprefix}trigger-$operation\"". ($disabled ? ' disabled="1"' : null) ." class=\"btn btn-". ($tone ? $tone : 'default') .
			" btn-$size\" onclick=\"". $this->getJSTrigger($target, $confirm, $vmsg, $validate) ."\"$tabindex>$glyphicon $label</button>\n";
	}

	/**
	* Call this to obtain an HTML snippet rendering an Operation trigger as a link-styled button with only an Icon on its face.
	* @param string[] $op An assembled Operation array as returned from Instance::get[Class]Operation(...).
	* @param string $size (optional) The Bootstrap-defined button sizing hint. We assume null ('default').
	* @param string $idprefix (optional) The id of the button returned will be the concatenation of this prefix and "trigger-$op[operation]". We assume null.
	* @return string HTML rendering of a Bootstrap Javascript-triggering button
	*/
	public function renderOperationAsIcon( array $op )
	{
		extract($op, EXTR_SKIP);
		$buttonId = "{$this->idprefix}trigger-$operation";
		if ($this->tabindex)
			$tabindex = " tabindex=\"$this->tabindex\"";
		if (!$glyphicon)
			$glyphicon = "[&nbsp;?&nbsp;]";
		return "<button$tabindex type=\"button\" id=\"$buttonId\" title=\"$label\" class=\"btn btn-link\" onclick=\"". $this->getJSTrigger($target, $confirm, $vmsg, $validate) ."\">$glyphicon</button>\n";
	}

	/**
	* Call this to obtain a link (anchor HTML element with href attribute) which will trigger an Operation.
	* @param string[] $op An assembled Operation array as returned from Instance::get[Class]Operation(...).
	* @param string $innerHTML (optional) Interpreted as follows based on the type passed:
	*	false - Don't complete the A element, but just return the opening tag only.
	*	null - (assumed) Render with a label derived from the operation specification just as for a button.
	*	string - Use this text as the innerHTML of the A element, and include the closing tag.
	* @return string HTML rendering of a Bootstrap Javascript-triggering anchor/link
	*/
	public function renderOperationAsLink( array $op, $innerHTML = null )
	{
		if ($this->tabindex)
			$tabindex = " tabindex=\"$this->tabindex\"";
		$openTag = "<a id=\"{$this->idprefix}trigger-$op[operation]\" href=\"javascript:".
				$this->getJSTrigger($op['target'], $op['confirm'], $op['vmsg'], $op['validate']) ."\" title=\"$op[label]\"$tabindex>";
		if (false === $innerHTML)
			return $openTag;
		if (null === $innerHTML)
			$innerHTML = ($op['icon'] ? "$op[glyphicon]&nbsp;" : null) ."$op[label]";
		return "$openTag$innerHTML</a>";
	}

	/**
	* An Operation Selector is an HTML <select> element which triggers a new request when one of its values is selected.
	* The trigger will invoke one Operation as selected, ie. each option in the selector represents one operation.
	* We assume that the operations are all instance-oriented and not class-oriented because it's rarely consistent to aggregate multiple operations on the same class as abstract.
	* Thus we assume we will provide an id or rel argument in the trigger, or that a list of those will be submitted by POST'ing a form.
	* We differentiate these two cases based on whether we are provided an instance of Element or Relation or a mere class name (Element) or [ class name, referent ] pair (Relation)
	* Said another way: If we lack the id of the relative or focal Element, we will post the form this control inhabits, assuming id[] or rel[] will be included.
	* Regardless of how ID is included, the dynamic argument will be the operation name, and we will pass NULL for the actionArg to getOperation(...).
	* That means this calling action must handle the 'operation' argument within render_me(), ie. with setSubOperation() incorporating id[] from the appropriate place.
	* Note that the name of the selector as might post in a multi-select form will be 'operation'... consider this a reserved name throughout the rest of the form's input controls.
	* @param mixed $focus Interpreted based on its type as follows:
	*	Relation instance - Operations will be performed against this one Relation instance.
	*	Element instance - Operations will be performed against this one Element instance.
	*	string - Must be an Element class name. Will assume id[] is posted via the form this control inhabits; said form will be submitted as we trigger.
	*	[string, Element] - The string must be a Relation class name with Element an instance of its referent class. Will assume rel[] is posted via the form as described above.
	* @param string[] $oplist A list of operation names. Operations listed here which are present in the focus class's specification and pass checking will be included as options in the selector.
	* @param string $prompt (optional) An HTML rendering to place in the selector control pending a selection. This is the rendered value of the first <option>.
	*	If you don't provide a value, we will use " With selected... " in multi-id scenarios and " Options... " for single focus scenarios.
	* @param string $idprefix (optional) The id of the selector will be 'operation' unless you provide a prefix for that value.
	* @return string An HTML rendering of an entire <select> element with one <option> per validated operation in the passed $oplist.
	*/
	public function renderOperationSelector( $focus, array $oplist, $prompt = null )
	{

	}

    /**
    * Merely as a convenience shorthand for the caller, we pass-thru requests for a target directly to our context.
    */
    final public function target( /* ... */ )
    {
        return call_user_func_array([$this->context, 'target'], func_get_args());
    }

}
