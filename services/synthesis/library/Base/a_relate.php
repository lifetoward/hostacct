<?php
/**
* a_Relate
* Manage relations by allowing the creation, deletion, and editing of Relation records for a given referent Element instance and relation class.
*
* Created: 11/28/14 for Lifetoward LLC
*
* All original code.
* @package Synthesis/Base
* @author Biz Wiz <bizwiz@SyntheticWebApps.com>
* @copyright (c) 2014 Lifetoward LLC; All rights reserved.
* @license proprietary
*/
class a_Relate extends Action
{
	protected $ref, $class, $annointed = array(), $doomed = array();
	private static $savedProperties = array('ref','class');
	use SerializationHelper;

	/**
	* This static method processes passed-in args in a flexible way to obtain the scope of a set of possible relations.
	* Specifically it figures out the referent Element and the Relation class which it anchors.
	* This defines the scope of Relation instances which are relevant in a particular focus context.
	* Our argument handling approach is described here:
	* The arg itself may be adequate if it is itself a Relation instance.
	*	Otherwise the arg must be an array of keyed arguments, because more info would be required.
	* Though a Relation instance is sufficient to be completely informed, it should be noted that we will not be limited to working with just that instance.
	* If a 'focus' is provided, it represents an instance which might be the relation itself (done!) or the referent Element.
	* If we have a referent Element, then all that remains is a matching relation class which we'll get from 'class'.
	* Without 'focus', we'd need a 'class' which represents a Relation, and the id of the referent which we'll accept as 'ref' or 'id'.
	* Thus the only recognized args are 'focus','class','ref', and 'id'.
	* @param mixed $args A Relation instance or an array of keyed arguments of mixed type.
	* @return array[referentElement, relationClassname]
	*/
	public static function resolveRelationScopeFromArgs( $args )
	{
		if (!$args)
			throw new Exception("Arguments are required to construct a_Relation");

		if ($args instanceof Relation || is_string($args))
			$args = array('focus'=>$args); // normalize an all-as-one into keyed value form for common processing

		if (!is_array($args))
			throw new Exception("Argument received must be a Relation instance or an array of keyed values.");

		unset($args['args']);

		$focus = $class = $id = $ref = null;
		extract($args, EXTR_IF_EXISTS);

		if ($focus) { // focus supplies an instance or a handle to an instance
			if (is_string($focus)) { // focus can be a handle
				list($focal, $key) = explode('=', $focus);
				if (is_subclass_of($focal,'Element'))
					$focus = Element::get($focus);
				else if (is_subclass_of($focal,'Relation'))
					$focus = Relation::get($focus);
				else
					throw new Exception("'Focus' arg provided as string, but couldn't identify its class as Element or Relation.");
			}
			if ($focus instanceof Relation)
				return array($focus->_referent, get_class($focus));
			if ($focus instanceof Element)
				$referent = $focus;
			else
				throw new Exception("Focus provided but can't resolve it to an Element or Relation.");
		}
		// If we are here, we MAYBE have a referent, but nothing else, and 'focus' is expended.

		if (!is_subclass_of($class, 'Relation'))
			throw new Exception("Need Relation class name as 'class' arg! (class is '$class')");
		$refclass = reset($class::$keys);

		if (!$referent) {
			if (is_string($ref) && !is_numeric($ref))
				$referent = Element::get($ref);
			else if ($ref instanceof $refclass)
				$referent = $ref;
			else if (is_numeric($ref) && $ref > 0)
				$referent = $refclass::get($ref);
			else if (is_numeric($id) && $id > 0)
				$referent = $refclass::get($id);
			else
				throw new Exception("Unable to determine the referent instance of class '$refclass' for Relation class '$class'.");
		}
		if (($rc = get_class($referent)) != $refclass)
			throw new Exception("Referent we got is of class '$rc' but the Relation class '$class' defines its referent class as '$refclass'.");

		return array($referent, $class);
	}

	public function __construct( Context $c, $args = null )
	{
		parent::__construct($c);
		list($this->ref, $this->class) = self::resolveRelationScopeFromArgs($args);
	}

	protected function render_me( HTMLRendering $returned = null )
	{
		$c = $this->context;
		extract($c->request, EXTR_PREFIX_INVALID, 'r');
		$class = $this->class;
		$hints = $class::$hints[__CLASS__];
		extract($class::obtainRenderingVars($hints));

		if ($returned) {
			// We may be returning from a delete operation or an edit the content of the relation record.
			// When that happens, we know that we have a record-content-oriented relation and anointing is irrelevant, but dooming is possible.
		}

		// Here we need to get our list of candidate Relatives and current Relatives (dataless) or Relations
		// We need filtering here, and we also will need to ensure that authorized elements are similarly limited.
		// $hints['filter']
		$relations = $class::getRelations($this->ref);
		$candidateList = $class::getRelativesList($this->ref, array('inverse'=>true));

		// The meaningful actions are as follows:
		// push = Save updates made in the UI to the database and continue with the interface.
		// done = Save updates made in the UI and then exit the action.
		// edit = Invoke a subaction to edit a new or existing relation instance.
		// create = Invoke a subaction to create a new relative and then .
		if (in_array($args['action'], array('push','done','edit','create'))) {

			// Doomed is a simple list of IDs of Relatives which have been selected for unassignment.
			foreach ((array)$post['doomed'] as $doomed) {
				if (in_array($doomed, array_keys($relations)))
					$this->doomed[$doomed] = $relations[$doomed];
			}
			// Anointed is a simple list of IDs of candidate Relatives selected for assignment.
			foreach ((array)$post['anointed'] as $anointed) {
				if (in_array($anointed, array_keys($candidateList)))
					$this->anointed[$anointed] = $candidateList[$anointedz];
			}

			if ('edit' == $args['action']) {
				// accept, validate, and setup the relative id

				// Unlike the others, the Add action does NOT post to the database and rather calls a subaction
				if ($this->subAction = static::newOperation($class::create($this->ref, $args['rel']), 'edit')) // assignment intended
					return; // allow caller to perform subaction manipulation

			} else if ('create' == $args['action']) {
				// The 'create' action is to create a Relative Element, not a Relation instance.
				// Double-check that relative creation is indicated for this relation, role, and referent.

			} else {
				$added = 0; $removed = 0;

				// Anointing is only relevant for a dataless relation context. Otherwise each relation is added explicitly through a subaction.
				foreach ($this->anointed as $anointed=>$relative) {
					// Create a dataless relation, store it, and keep our state up to date
					try {
						$newrel = $class::get($this->ref, $anointed);
						$newrel->store();
						$added++;
						$relations[$anointed] = $newrel;
						unset($this->anointed[$anointed]);
					} catch (Exception $ex) {
						$noticeMsg .= "There were problems";
					}
				}
				// Doomed relations are relevant for dataless and record-based relations. We keep track of which ones to remove, and then delete them here.
				foreach ($this->doomed as $doomed) {
					// Delete the doomed relation
					$relations[$doomed]->remove();
					unset($relations[$doomed]);
					unset($this->doomed[$doomed]);
					$removed++;
				}

				$updateMessage = "$added $relatives were $verbed. $removed $relatives were $unverbed.";
				if ('done' == $args['action'])
					return new HTMLRendering(HTMLRendering::COMPLETE, $updateMessage, $this->ref);
				$notice = "<p class=\"\">$updateMessage</p>";
			}

		} else if ($args['action'])
			logWarn("An invalid 'action' was posted to a_Relate: $args[action]; it is ignored.");

		$R = new InputRendering($c, $returned, $this->ref); // Accumulate results into this object
		$notice = "<div style=\"clear:both;margin-top:1em\">$returned->content</div>";

		if (count($candidateList))
			foreach ($candidateList as $rel=>$candidate)
				// The checkbox will only appear when dataless.
				$candidates .= '<div class="RelativeLine"'. (!$dataless ? ' onclick="$(\'form\').submit()" title="Click to '. $verb .'"' : null) .'>'.
					($dataless ?
						'<input type="checkbox" name="anointed[]" value="'. $rel .'" id="anoint_'. $rel .'">&nbsp;<label for="anoint_'. $rel .'">'. $candidate->_rendered .'</label>' :
						$candidate->_rendered) .
					"</div>\n";
		if ($addRelativeOp = $relclass::getClassOperation('create',$c,null)) // assignment intended
			$candidates .= '<div class="RelativeLine" onclick="'. $R->getJSTrigger($addRelativeOp['target']) .
				"\" title=\"Click to $verb\">$addRelativeOp[glyphicon]&nbsp;$addRelativeOp[label]</div>\n";
		if (!$candidates)
			$candidates = "( No $unverbed $relatives )";

		if (count($relations))
			foreach ($relations as $rel=>$candidate)
				$relateds .= '<div class="RelativeLine"><input type="checkbox" name="doomed[]" value="'. $rel .'" id="doom_'. $rel .'">&nbsp;<label for="doom_'. $rel .'">'.
					htmlentities($candidate) ."</label></div>\n";
		else
			$relateds = "( No $verbed $relatives )";

		$candidateFaq = $dataless ?
			"Select the $relatives you would like to $verb. They'll be $verbed when you 'Enact changes'." :
			"Click on a $relative to $verb it and complete its $descriptive." ;
		if (!$dataless)
			$relatedFaq = "Click on any $relative for which you'd like to review or update the $descriptive.";

		$R->addReadyScript(<<<js
js
			, __CLASS__);
		$R->addStyles(<<<css
div.RelativesList {
	max-height:30em;
	overflow:auto;
	background-color:#FFE;
	border:3px ridge gray;
	padding:1pc; }
h3 { /* RelativesList headings */ }
div.RelativeLine { padding:4pt 1pc; border:1px solid transparent; }
div.container { padding:1pc; }
div.RelativeLine:hover { border:1px solid blue; border-radius:3pt; cursor:pointer; background-color:white; font-weight:bold; }
div#ActionBar { text-align:center; }
div#ActioBar button { font-weight:bold; }
p.faq { font-size:smaller; color:purple; font-style:italic; }
button#relative-trigger-create { margin:1pc auto; width:auto; }
css
			, __CLASS__);

		$doneOp = array('icon'=>'ok-sign', 'label'=>"Enact Changes &amp; Exit", 'tone'=>'success', 'target'=>$c->target(array(), true, 'action', 'done'));
		$pushOp = array('icon'=>'repeat', 'label'=>"Enact Changes &amp; Continue", 'tone'=>'primary', 'target'=>$c->target(array(), true, 'action','done'));
		$enactButtons = $R->renderOperationAsButton($doneOp) ." &nbsp; ". $R->renderOperationAsButton($pushOp);

        $pushButton = "\n\t".'<button class="btn btn-primary btn-lg" id="push-button" name="action" value="push" type="button"><span class="glyphicon glyphicon-repeat"/> Enact changes & Remain</button>';
		$bannerTriggers = $R->renderCancelButton(array('label'=>"Cancel"));
		return $R->prep(HTMLRendering::PROCEED, <<<html
$notice
<span style="float:right;margin-top:2pt;" id="banner-triggers">$bannerTriggers</span>
<h1>$relatives for {$this->ref->_rendered}</h1>
<hr/>
<form name="RelativeSelector" action="$formTarget" method="post">
<div class="row">
	<div class="col-md-6">
		<h3>Possible $relatives</h3>
		<div id="AvailableRelatives" class="RelativesList">
$candidates
$addRelativeTrigger
		</div>
		<p class="faq">The $relatives in this list are NOT currently $verbed to {$this->ref->_rendered}. $candidateFaq</p>
	</div>
	<div class="col-md-6">
		<h3>Current $relatives</h3>
		<div class="RelativesList" id="RelatedRelatives">
$relateds
		</div>
		<p class="faq">The $relatives in this list ARE currently $verbed to {$this->ref->_rendered}. $relatedFaq
			Select any $relatives you would like to $unverb. They'll be $unverbed when you Enact changes.</p>
	</div>
</div>
<hr/>
<div class="row" id="ActionBar">
	$enactButtons
</div>
</form>
html
			, $this->ref);

	} // render_me()

}
