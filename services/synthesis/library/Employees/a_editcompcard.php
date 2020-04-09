<?php

class a_EditCompCard extends Action
{
	public $card; // compensation card in use
	public $types; // Compensation types which define how lines can be made. Our copy includes dereferences of the rate as "userate".
	protected $editline; // The line which is in the editor, perhaps not added to the card yet.

	public function serialize( )
	{
		return serialize(array(parent::serialize(), $this->card, $this->types, $this->editline));
	}
	public function unserialize( $rep )
	{
		list($parent, $this->card, $this->types, $this->editline) = unserialize($rep);
		parent::unserialize($parent);
	}

	public function __sleep()
	{
		return array_merge(parent::__sleep(), array('card','types','editline'));
	}

	// Pass me an employee instance if you want to create a card.
	// Otherwise just pass me the card you want to edit.
	public function __construct( Context $c, Element $x )
	{
		parent::__construct($c);
		$mgrAuth = $c->_portal == 'manage' && $c->_employee->roleset['manager'];
		if ($x instanceof e_compcard) {
			if (!$mgrAuth && ($x->employee->_handle != $c->_employee->_handle || $x->initiation != 'empl'))
				return logError("AUTHORIZATION FAILURE: Must be a manager in the manager portal or an employee editing a compcard zie initiated to execute a_EditCompCard.");
			$this->card = $x;
		} else if ($x instanceof e_employee) {
			if (!$mgrAuth)
				return logError("AUTHORIZATION FAILURE: Must be a manager to create a new compcard.");
			$this->card = e_compcard::create($x); // for an employee
			$this->card->initiation = 'mgmt';
		}

		if (!$this->card->employee)
			throw new ErrorException("We don't have an employee for the card!");

		// We process the types once at the beginning because it's mildly expensive to go get the employee-specific values, and hey, we already know employee.
		foreach (e_comptype::collection() as $type)
			if ($type->userate = (is_numeric($ratename = $type->rate) ? $type->rate : $this->card->employee->$ratename) * 1)
				$this->types[$type->_id] = $type;
		// Note that if we don't have a rate value then the type is not valid and we don't consider it.
	}

	public function render_me( $notice = null )
	{
		$mgrMode = $c->_portal == 'manage' && $c->_employee->roleset['manager'];

		list($args, $post) = $c->request;
		if ($args['*exit'])
			return $this->getResult(static::CANCEL);
		if ($this->editline) // Because we have two independently serialized references to the comptype in the editor and the one we really want for its aux value "userate" is in our compcard object, we take this extra step
			$this->editline->comptype = $this->types[$this->editline->comptype->_id];
		if (is_array($post))
			foreach (array('description','bizunit','explanation','response') as $postable)
				if (array_key_exists($postable, $post))
					$this->card->$postable = $post[$postable];
		list($action, $x) = explode('=', $post['_act_'], 2);

		if ($action == 'viewcard' && is_numeric($x)) {
			$this->subAction = new a_ViewCompCard($c, e_compcard::get($x));
			return;
		}

	// LINE actions

		else if ('newline' == $action && is_numeric($x) && !$this->editline) { // User wishes to add a line to the existing list; the x-arg is the comptype
			$this->editline = e_compline::create($this->card, $this->types[$x]);
			$this->editline->EDITMODE = 'ADD';

		} else if ('editline' == $action && is_numeric($x) && !$this->editline) { // User wishes to update the contents of an existing line in the card
			if ($this->card->lines[$x]) {
				$this->editline = $this->card->lines[$x]; // note that ID here is only an array index. Some of these lines don't have IDs
				$this->editline->comptype = $this->types[$this->editline->comptype->_id]; // same issue as above... the lines as they are kept just have ID references to comptypes which would be loaded, not those with our userate field
				unset($this->editline->DELETE);
			}

		} else if ('noline' == $action) {
			$this->editline = null;

		} else if ('postline' == $action && $this->editline) { // User has entered the details for a line and we must now put it in the card
			foreach (array('rate','earndate','quantity','memo') as $postable)
				if ($post[$postable]) // we can use this simple test because we know actual values are needed for all of these.
					$this->editline->$postable = $post[$postable];
			$this->editline->rate = $this->editline->comptype->userate;
			$typeclass = "t_{$this->editline->comptype->unittype}";
			$this->editline->amount = $this->editline->rate * $typeclass::numeric($this->editline, 'quantity') * $this->editline->comptype->factor;
			if ($this->editline->EDITMODE == 'ADD')
				$this->card->lines[] = $this->editline;
			unset($this->editline->EDITMODE); // really against the object in the list now
			$this->editline = null;

		} else if ('removeline' == $action && is_numeric($x)) { // User wishes to remove a line from the card
			if ($this->card->lines[$x]) {
				if ($this->card->lines[$x]->_stored)
					$this->card->lines[$x]->DELETE = true;
				else
					unset($this->card->lines[$x]);
			}

	// CARD actions

		} else if ('reject' == $action && $c->_employee->roleset['manager']) {
			if (!$this->card->response)
				$notice = '<p class="alert">You must provide some explanatory notes as a response when you reject a card.</p>';
			else {
				$this->card->initdate = null;
				$this->card->lockdate = null;
				$this->card->store();
				return $this->getResult(static::COMPLETE, '<p class="alert">The compensation card was rejected and returned to '. $this->card->»initiation .' in draft state.</p>');
			}

		} else if ('retain' == $action) {
			if (!$this->card->bizunit || !$this->card->description)
				$notice = '<p class="notice">You must set the business unit and provide a description for the card before you can retain it.</p>';
			else {
				$this->card->store();
				return $this->getResult(static::COMPLETE, '<p class="notice">The compensation card was retained. It is not yet payable and may be modified before it is committed.</p>');
			}

		} else if ('commit' == $action) {
			foreach ($this->card->lines as $line)
				if (!$line->DELETE)
					{ $validline = true; break; }
			if (!$this->card->bizunit || !$this->card->description || !$validline )
				$notice = '<p class="notice">You must set the business unit, provide a description, and include at least one payable line for the card before you can initiate it.</p>';
			else {
				if (!$this->card->initidate)
					$this->card->initdate = date('Y-m-d');
				if ($c->_portal == 'manage' && $c->_employee->roleset['manager'])
					$this->card->lockdate = date('Y-m-d');
				$this->card->store();
				return $this->getResult(static::SUCCEED, '<p class="notice">The compensation card was committed. It may no longer be edited and is now available for review and eventually payment.</p>');
			}

		} else if ('delete' == $action) {
			if (!$this->card->_stored)
				return $this->getResult(static::CANCEL);
			try {
				$this->card->delete();
			} catch (UndeletableX $ex) {
				return $this->getResult(static::FAIL, '<p class="notice">The compensation card could not be deleted because elements of the system depend on it.</p>');
			}
			return $this->getResult(static::RESET, '<p class="notice">The compensation card was deleted from the system.</p>');
		}

	// Rendering

		$this->addStyles(self::DraftCardStyles, 'Draft Compensation card');
		$this->addScript(self::CompLineFormHandler, 'Draft Compensation card');

		// Scenario-based configuration
		if ($c->_portal == 'manage') {
			if ($this->card->initiation == 'mgmt') { // management's own drafts
				$fieldModes = array('bizunit'=>self::INPUT, 'description'=>self::INPUT, 'explanation'=>self::INPUT);
				$config = array('style'=>'draft', 'title'=>'Card in draft state', 'commit'=>'Commit', 'delete'=>true);
			} else if ($this->card->initdate) { // for approval
				$fieldModes = array('bizunit'=>self::INPUT, 'description'=>self::INPUT, 'explanation'=>self::VERBOSE, 'response'=>self::INPUT);
				$config = array('style'=>'actionable', 'title'=>'Card requiring approval', 'commit'=>'Approve');
				$initiation = "<br/>Initiated by {$this->card->»initiation} on {$this->card->»initdate}";
			}
		} else if ($this->card->initiation == 'empl') { // employee perspective, draft
			$fieldModes = array('bizunit'=>self::READONLY ,'description'=>self::INPUT ,'explanation'=>self::INPUT ,'response'=>self::VERBOSE);
			$config = array('emptypes'=>true ,'style'=>'draft' ,'title'=>'Unsubmitted card' ,'commit'=>"Submit" ,'delete'=>true);
		}
		if (!$fieldModes['explanation'])
			return $this->getResult(static::FAIL, '<p class="notice">An invalid context was detected.</p>');

		// Assembly
		$cancelTarget = $R->target(array('*exit'=>1));
		$activePanel = $this->editline ? $this->line_editor($c, $config) : $this->lines_list($c, $config);
		$this->card->_rendering = $R;
		foreach ($fieldModes as $fn=>$mode) // HANDLE THIS USING FIELD AUTHORIZATION AT THE RENDERING LEVEL
			if ($mode && ($this->card->$fn || $mode == self::INPUT))
				$fieldOut[$fn] = "<span class=\"fieldlabel\">{$this->card->{¬.$fn}}</span><br/>{$this->card->{».$fn}}";
		if ($this->card->adjusted)
			$refCard = "<p><span class=\"fieldlabel\">This card adjusts: </span><a href=\"javascript:go(pageform,'viewcard={$this->card->adjusted->_id}')\">{$this->card->adjusted}</a></p>";
		$this->rendered = <<<end
<div id="$config[style]" class="section">
<span class="sectionactions"><button type="button" onclick="location.replace('$cancelTarget')">&larr; Abandon changes</button></span>
<h3>$config[title]<br/>"$this->card"$initiation</h3>
$notice
<div class="active">
$refCard
<table id="card">
<tr id="cardprimary"><td>$fieldOut[description]</td><td style="width:30%">$fieldOut[bizunit]</td></tr>
</table>
<p>$fieldOut[explanation]</p>
$activePanel
$fieldOut[response]
</div>
</div>
end;
		return $this->getResult(static::PROCEED);
	}

	private function lines_list( Context $c, array $config )
	{
		if (count($this->card->lines) < 1)
			$linesOut = '<tr><td>&nbsp;</td></tr><tr><td colspan="4" align="center">There are no payable lines for this card yet.<br/>Add one by choosing a type from the selector above.</td></tr>'.
					"<tr><td>&nbsp;</td></tr>\n";
		else foreach ($this->card->lines as $x => $line) {
			$c->mode = $c::COLUMNAR;
			$linesOut .= "<tbody class=\"line". ($line->DELETE ? " deleted" : null) ."\">".
				"<tr><td>$line->»earndate</td><td colspan=\"2\">$line->»memo</td>".
					"<td>". ($line->DELETE ? null : "<img class=\"trigger\" onclick=\"go(pageform,'removeline='+$x)\" src=\"images/delete.png\"/> ") ."<img class=\"trigger\" onclick=\"go(pageform,'editline='+$x)\" src=\"images/edit.png\"/></td></tr>".
				"<tr><td>$line->comptype</td><td>$line->»quantity</td><td>$line->»rate</td>".
					"<td class=\"extamt\">$line->»amount ". (t_boolset::is_set($line->comptype->flags, 'taxable') ? "T" : "N") ."</td></tr></tbody>\n".
				"<tr><td colspan=\"4\">&nbsp;</td></tr>";
		}
		foreach ($this->types as $type)
			if (!$config['emptypes'] || in_array('employee', explode(',', $type->flags)))
				$comptypeOptions .= "<option value=\"$type->_id\">$type</option>";
		if ($config['delete'])
			$deleteOrReject = '&nbsp; <button type="button" onclick="if(confirm(\'If you proceed, this card will be irrevocably removed from the system.\'))chkgo(form,\'delete\')">'.
				"<img src=\"images/delete.png\"/> Delete card</button> &nbsp;";
		else
			$deleteOrReject = "&nbsp; <button type=\"button\" onclick=\"if(confirm('If you proceed, this card will be returned to {$this->card->»initiation} in draft state. ".
				"Don&rsquo;t proceed unless you have provided an appropriate response.'))chkgo(form,'reject')\"><img src=\"images/delete.png\"/> Reject card</button> &nbsp;";
		return <<<end
<span class="fieldlabel">Compensation line items</span>
<table>
<thead>
<tr><th>Date earned</th><th>Memo</th><th colspan="2" align="right" id="lineactions">
	<select size="1" onchange="go(form,'newline='+value)"><option value="">Add a new line... </option>$comptypeOptions</select></th></tr>
<tr><th>Type</th><th>Quantity</th><th>Rate</th><th class="extamt">Amount</th></tr>
</thead>
$linesOut
</table>
<div id="actions">
<button type="button" onclick="chkgo(form,'retain')"><img src="images/edit.png"/> Retain card</button> &nbsp;
<button type="button" onclick="if(confirm('Once you $config[commit] a compensation card it cannot be retracted.'))chkgo(form,'commit')"><img src="images/check.png"/> $config[commit] card</button>
$deleteOrReject
</div>
end;
	}

	private function line_editor( Context $c, array $config )
	{
		$this->editline->rate = 1 * $this->editline->comptype->userate;
		$unitclass = "t_{$this->editline->comptype->unittype}";
		$inputLines = "<tr><td class=\"fieldlabel\">Date earned: </td><td>". $this->editline->renderField('earndate', $c, self::INPUT) ."</td></tr>\n".
			"<tr><td class=\"fieldlabel\">{$this->editline->comptype->unit}s: </td><td>". $unitclass::render($this->editline, 'quantity', $c, self::INPUT) ."</td></tr>\n".
			"<tr><td class=\"fieldlabel\">Memo / explanation: </td><td>". $this->editline->renderField('memo', $c, self::INPUT) ."</td></tr>\n";
		$c->addStyles(self::EditComplineStyles, "Edit compline");
		return <<<end
<div id="editline">
<h3>Add {$this->editline->comptype}<br/>{$this->editline->»rate}</h3>
<table>\n$inputLines\n</table>
</table>
<div id="actions">
<button type="button" onclick="chkgo(form,'postline')"> &nbsp; OK &nbsp; </button> <button type="button" onclick="go(form,'noline')">Cancel</button>
</div>
</div>
end;
	}

	const DraftCardStyles = <<<'css'
#mcomp div.section div.active { margin:0; padding:8pt; }
#mcomp .section table { margin:1em auto; width:100%; cellpadding:0}
#mcomp .section p { margin:1em auto; }
#mcomp .section table th { text-align:left; }
#mcomp .section table tbody { border:1px solid blue; padding:5pt;}
#mcomp .section table tbody.line { background-color:#DDEEFF; }
#mcomp .section table tbody.deleted { background-color:#AA9999; text-decoration:line-through; }
#mcomp .section table td,tr { border:none; padding:none; }
#mcomp .section table tr#cardprimary select { font-size:110% }
#mcomp .section table tr#cardprimary input { font-size:110% }
#mcomp table#card td select { width:100%; }
#mcomp td#carddesc { width:65%; }
#mcomp td input#description { width:90%; }
#mcomp .section table .extamt { text-align:right; font-weight:bold; }
#mcomp .section h4 { margin:auto; width:98%; background-color:lightyellow; }
#mcomp .section div#actions { margin:.5em auto; width:98%; text-align:center; }
#mcomp .section div#actions button { font-size:110%; height:2em; }
#mcomp .section div#actions button img { height:16px; width:16px }
#mcomp textarea { width:100%; border:2px solid black; }
#mcomp th#lineactions { text-align:right }
#mcomp img.trigger { cursor:pointer; }
css;

	const EditComplineStyles = <<<'css'
#mcomp div#editline { width:6in; margin:auto; border:1px solid blue; background-color:lightcyan }
#mcomp div#editline input#memo { width:4in; }
#mcomp td.fieldlabel { text-align:right; padding:.5em }
#mcomp div#editline h3 { padding:.5em; text-align:center }
css;

	const CompLineFormHandler = <<<'jscript'
var okflags={};
function chkgo(f,x){
	if(x!='noline')
	for(n in okflags)
		if(!okflags[n]){
			alert('Please provide valid entries for all required fields (marked with the blue arrow).');
			return;}
	go(f,x);}
function go(f,x){
	f._act_.value=x;
	f.submit(); }
function capcel_validated(c,n,ok){if(n)okflags[n]=ok;}
jscript;

}
