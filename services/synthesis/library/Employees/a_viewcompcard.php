<?php

class a_ViewCompCard extends Action
{
	public $card;

	public function serialize( )
	{
		return serialize(array(parent::serialize(), $this->card));
	}
	public function unserialize( $rep )
	{
		list($parent, $this->card) = unserialize($rep);
		parent::unserialize($parent);
	}

	public function __construct( Context $c, e_compcard $card )
	{
		parent::__construct($c);
		if ($card->employee->_handle != $c->_employee->_handle && !$c->_employee->roleset['manager'])
			return logError("AUTHORIZATION FAILURE: A non-manager may only view hir own compensation data.");
		$this->card = $card;
	}

	public function render_me( )
	{
		list($args, $post) = $c->request;
		if ($args['*exit'])
			return self::CANCEL;
		if (isset($post['response']))
			$this->card->acceptFieldValue('response', $post['response']);
		if ($post['_act_'] == 'respond') {
			$this->card->store();
			$this->rendered = '<p class="notice">Response comments were updated.</p>';
			return self::COMPLETE;
		} else if ($post['_act_'] == 'initiate') {
			$newcard = e_compcard::create($this->card->employee);
			$newcard->initiation = 'empl';
			$newcard->adjusted = $this->card;
			$newcard->description = $this->card->description;
			$newcard->bizunit = $this->card->bizunit;
			$this->subAction = new a_EditCompCard($c, $newcard);
			return;
		} else {
			list($action, $x) = explode('=', $post['_act_']);
			if ($action == 'viewcard' && is_numeric($x)) {
				$this->subAction = new a_ViewCompCard($c, e_compcard::get($x));
				return;
			}
		}

		if ( $this->card->initiation != 'empl' && $this->card->initdate && $c->_portal == 'employee' && $this->card->employee->_id == $c->_employee->_id &&
				$this->card->initdate >= date(mktime(0,0,0,date('m'),date('d')-14,date('Y'))) ) {
			$actionable = true;
			$divID = 'actionable';
			$cardMode = "Actionable";
			$exitLabel = "Go back";
			$actions = '<div id="actions">'.
				'<button onclick="go(pageform,\'initiate\')" title="For claiming expense reimbursement or registering a dispute"><img src="images/new.png"/> Create an adjustment</button>'.
				' &nbsp; <button onclick="go(pageform,\'respond\')" title="Exit this form while retaining any updates to the response comments."><img src="images/check.png"/> Record a response</button></div>';
			$response = '<p class="fieldlabel">Comments in response</p>'. $this->card->renderField('response', $c, self::INPUT);
		} else {
			$divID = 'payable';
			$cardMode = 'Payable';
			if ($this->card->response)
				$response =  '<p class="fieldlabel">Comments in response</p><div class="richtext">'. $this->card->renderField('response', $c, self::VERBOSE) .'</div>';
			$exitLabel = "Done";
		}
		foreach ($this->card->lines as $line) {
			$c->mode = $c::COLUMNAR; $line->_context = $c;
			$linesOut .= '<tbody class="alt'. (++$alt%2) .'">'."<tr><td>$line->»earndate</td><td colspan=\"3\">$line->»memo</td></tr>".
				"<tr><td>$line->comptype</td><td>$line->»quantity</td><td>$line->»rate</td>".
					"<td class=\"extamt\">$line->»amount ". (t_boolset::is_set($line->comptype->flags, 'taxable') ? "T" : "N") ."</td></tr></tbody>\n";
		}
		// Adjustment cards as records
		if ($count = count($records = e_compcard::collection(array('sortfield'=>'initdate', 'where'=>"`{}`.adjusted = {$this->card->_id} && `{}`.initdate IS NOT NULL")))) {
			foreach ($records as $d) {
				$d->_context = $c; $c->mode = $c::COLUMNAR;
				$rowsOut .= "<tr onclick=\"go(pageform,'viewcard=$d->_id')\" class=\"trigger alt". ++$alt%2 .'">'."<td>$d->»initdate</td>".
					"<td>$d->»description</td><td>$d->»daterange</td><td>$d->»taxable</td><td>$d->»taxfree</td></tr>\n";
			}
			$relatedCards = <<<end
<p class="fieldlabel">This card has $count related card(s):</p>
<table style="margin-bottom:1em"><tr><th>Submitted</th><th>Description</th><th>Work dates included</th><th>Taxable</th><th>Non-taxable</th></tr>
$rowsOut
</table>
end;
		}
		// Other card stuff
		if ($this->card->explanation)
			$explanation = '<p><span class="fieldlabel">Explanation</span><div class="richtext">'. $this->card->renderField('explanation', $c, self::VERBOSE) ."</div></p>\n";
		$lineSummary = '<span class="fieldlabel">Compensation line items</span>: '. ($lineCount = count($this->card->lines)) ." in the <b>". $this->card->renderField('bizunit', $c, self::INLINE) ."</b> business unit";
		$commitment = $this->card->lockdate ? $this->card->»lockdate : "( no )";
		if ($this->card->adjusted)
			$adjusts = "<p><span class=\"fieldlabel\">Adjusts</span><br/>{$this->card->adjusted}</p>";
		$c->addStyles(self::ViewCardStyles, "view compcard");
		$cancelTarget = $c->get_target(array('*exit'=>1));
		$c->mode = self::COLUMNAR; $this->card->_context = $c;
		$this->rendered = <<<html
<div class="section" id="$divID">
<span class="sectionactions"><button type="button" onclick="location.replace('$cancelTarget')">&larr; $exitLabel</button></span>
<h3>$cardMode card<br/>"$this->card"</h3>
<div>
$notice
<table><tr><td><span class="fieldlabel">{$this->card->¬initiation}</span><br/>{$this->card->»initiation} on {$this->card->»initdate}</td><td><span class="fieldlabel">Committed</span><br/>$commitment</td></tr></table>
$adjusts
$explanation
<p>$lineSummary</p>
<table>
<thead>
<tr><th>Date earned<br/>Type</th><th>Memo<br/>Quantity</th><th>Rate</th><th class="extamt">Amount</th></tr>
</thead>
$linesOut
<tfoot><tr><td colspan="3" align="right">Total taxed</td><td class="extamt">{$this->card->»taxable} T</td></tr>
<tr><td colspan="3" align="right">Total untaxed</td><td class="extamt">{$this->card->»taxfree} N</td></tr></tfoot>
</table>
$response
$relatedCards
$actions
</div>
html;
		return self::PROCEED;
	}

	const ViewCardStyles = <<<'css'
#mcomp div.section table { margin:auto; width:100%;  }
#mcomp div.section>div { margin:1em 1.5em; }
#mcomp .section table th { text-align:left; vertical-align:bottom; }
#mcomp .section table tbody { border:1px solid blue; padding:3pt;}
#mcomp .section table tfoot { font-weight:bold; }
#mcomp .section table .alt0 { background-color:#EEFFEF; }
#mcomp .section table .alt1 { background-color:#F4F2FF; }
#mcomp .section table td,tr { border:none; padding:none; }
#mcomp .section table td.extamt { }
#mcomp .section h4 { margin:1em auto; width:90%; font-size:120%; }
#mcomp .section div#actions { margin:.5em auto; width:98%; text-align:center; }
#mcomp .section div#actions button { font-size:110%; height:2em; }
#mcomp .section div#actions button img { height:16px; width:16px }
#mcomp .section .richtext { width:88%; margin:.5em auto; padding:.2em 1em; max-height:2in; overflow:scroll; background-color:#FFFFF2; border:2px #FFFFF0 inset; }
#mcomp .section div textarea { width:100%; }
#mcomp .section div#rightHeading { float:right; display:inline-block; color:white; text-align:right; padding:6pt; }
css;

}
