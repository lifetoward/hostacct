<?php

class a_ManageCompensation extends Action
{
	protected $selectedUnpaid;  // keeps the list of selected unpaid compcards to ensure interface continuity
	protected $employee; // determines whether we get a manager's view or just limited to the authenticated employee.

	public function serialize( )
	{
		return serialize(array(parent::serialize(), $this->selectedUnpaid, $this->employee));
	}
	public function unserialize( $rep )
	{
		list($parent, $this->selectedUnpaid, $this->employee) = unserialize($rep);
		parent::unserialize($parent);
	}

	public function __construct( Context $c, e_employee $employee = null )
	{
		parent::__construct($c);
		$this->employee = $employee;
	}

	public function render_me( $notice = null )
	{
		if (!$this->employee && !($mgrMode = ($c->_portal == 'manage' && $c->_employee->roleset['manager']))) {
			$this->rendered = '<p class="notice">You are not authorized to use the compensation system as a manager.</p>';
			return $this->getResult(static::FAIL);
		}

		try {

			$c->addStyles(self::ManageCompStyles, 'Manage Compensation');

			list($args, $post) = $c->request;
			if (count($post)) {
				// Process actions... note that we need to save the id values which might be selected
				$this->selectedUnpaid = $post['id'];
				list($action,$id) = explode('=', $post['_act_'], 2);
				if ('addcard'==$action && is_numeric($id) && $id > 0)
					$this->subAction = new a_EditCompCard($c, e_employee::get($id));
				else if ('editcard'==$action && is_numeric($id) && $id > 0)
					$this->subAction= new a_EditCompCard($c, e_compcard::get($id));
				else if ('viewcard'==$action && is_numeric($id) && $id > 0)
					$this->subAction= new a_ViewCompCard($c, e_compcard::get($id));
				else if ('viewpaid'==$action && is_numeric($id) && $id > 0)
					$this->subAction= new a_ViewCompPaid($c, e_comppaid::get($id), $this->employee);
				else if ('paycards'==$action)
					$this->subAction= new a_PayCompCards($c, $post['id']);
				if ($this->subAction)
					return;
			}

			$rc = Action::PROCEED;

			if (!$renderable) {

				if ($mgrMode) { // manager perspective

					$topActions = '<span class="sectionactions"><select size="1" onchange="go(form,\'addcard=\'+value)"><option value="">Add new Compensation card for...</option>'.
							e_employee::as_options(null, "status='active'") ."</select></span>\n";
					$draftOut = $this->render_section($c, array('style'=>'draft'
						,'title'=>"Cards in draft state"
						,'help'=>'These cards have not been initiated or committed. They are private until initiated. Click one to edit it.'
						,'none'=>'There are currently no compensation cards in draft state.'
						,'where'=>"{}.initdate IS NULL && {}.initiation='mgmt'"
						,'trigger'=>"edit"
						,'columns'=>array('employee','bizunit','description','daterange','taxable','taxfree')
						));
					$actionableOut = $this->render_section($c, array('style'=>'actionable'
						,'title'=>"Cards requiring approval"
						,'help'=>'These are cards which require management approval before they are committed to be paid. Click one to view or act on it.'
						,'none'=>'There are currently no compensation cards requiring approval.'
						,'where'=>"{}.initdate IS NOT NULL && {}.lockdate IS NULL"
						,'trigger'=>"edit"
						,'columns'=>array('initdate','employee','bizunit','description','initiation','taxable','taxfree')
						));
					$c->addScript("function selecto(f,v){\ny=f.elements;\nfor(x=0;x<y.length;x++)\nif(y[x].type=='checkbox')\ny[x].checked=v;}", 'Unpaid cards');
					$payableOut = $this->render_section($c, array('style'=>'payable'
						,'title'=>"Payable cards"
						,'help'=>'Though these cards have been committed for payment, payment has not yet been arranged.<br/>Click one to view its details, or select and pay a set of them.'
						,'none'=>'There are currently no compensation cards awaiting payment.'
						,'where'=>"{}.lockdate IS NOT NULL && {}.comppaid IS NULL"
						,'trigger'=>"view"
						,'columns'=>array('*selector','lockdate','employee','description','taxable','taxfree')
						,'bannertrigger'=>'<span class="sectionactions"><button type="button" onclick="if(selected(form))go(form,\'paycards\')"><img src="images/select.png"/> Pay selected cards</button></span>'
						));

				} else { // employee perspective

					$draftOut = $this->render_section($c, array('style'=>'draft'
						,'title'=>"Unsubmitted cards"
						,'help'=>'These cards have not been submitted or they were returned after management review. Click one to edit it.'
						,'none'=>'You do not currently have any unsubmitted compensation cards.'
						,'where'=>"{}.employee={$c->_employee->_id} && {}.initdate IS NULL && {}.initiation='empl'"
						,'trigger'=>"edit"
						,'columns'=>array('description','linecount','daterange','taxable','taxfree')
						));
					$actionableOut = $this->render_section($c, array('style'=>'actionable'
						,'title'=>"Actionable cards"
						,'help'=>'These cards are new enough to allow for comment, dispute, or expense reimbursement claims. Click one to view or take action on it.'
						,'none'=>'You do not currently have any actionable compensation cards.'
						,'where'=>"{}.employee={$c->_employee->_id} && {}.initdate IS NOT NULL && {}.initiation!='empl' && {}.initdate + INTERVAL 2 WEEK >= CURDATE()"
						,'trigger'=>"view"
						,'columns'=>array('lockdate','description','linecount','daterange','taxable','taxfree')
						));
					$payableOut = $this->render_section($c, array('style'=>'payable'
						,'title'=>"Payable cards"
						,'help'=>'Cards which have been submitted but for which payment has not yet been made are listed here. Click one to view it.<br/>Note that cards which have not been committed by management may not be paid as requested.'
						,'none'=>'You do not currently have any payable compensation cards.'
						,'where'=>"{}.employee={$c->_employee->_id} && {}.initdate IS NOT NULL && {}.comppaid IS NULL"
						,'trigger'=>"view"
						,'columns'=>array('*empStatus','description','linecount','daterange','taxable','taxfree')
						,'totals'=>true
						));
				}

				$paydaysOut = $this->Paydays($c, $mgrMode);

				$c->addScript(<<<jscript
function selected(f){
	y=f.elements;
	for(x=0;x<y.length;x++)
		if(y[x].type=='checkbox'&&y[x].checked)
			return true;
	return false; }
jscript
					,'ManageCompensation(top)');

				$renderable = "$draftOut\n$actionableOut\n$payableOut\n$paydaysOut\n";
			} // When there is no subaction

		} catch (Exception $ex) {
			if ($GLOBALS['firebug'])
				fb($ex);
			else
				logError(array('Uncaught exception from within the Compensation subsystem'=>$ex));
			$notice = "<p class=\"notice\">The compensation subsystem encountered an error. The action you were taking was aborted. Please reload this page to continue.</p>";
			$this->subAction= null;
		}

		$c->addScript(<<<jscript
function go(f,x) {
	f._act_.value=x;
	f.submit()
}
jscript
			,'ManageCompensation(envelope)');
		$targetPostForm = htmlentities($c->get_target(null, null, true));
		$overVerb = $this->employee ? "$this->employee's" : 'Manage';
		$this->rendered = <<<end
<div id="mcomp">
<form method="post" action="$targetPostForm" id="pageform">
<input type="hidden" name="_act_"/>
$topActions
<h1>$overVerb compensation</h1>
$notice$renderable
</form>
</div>
<script>pageform=document.getElementById('pageform');</script>
end;
		return $this->getResult($rc);
	}

	private function render_section( Context $c, array $x )
	{
		extract($x);
		if (!($count = count($records = e_compcard::collection(array('sortfield'=>$sortfield, 'where'=>$where)))))
			return "<p>$none</p>";
		$result = "<div id=\"$style\" class=\"section\">\n$bannertrigger<h3>$title ($count)<p>$help</p></h3>\n<table><thead>\n<tr>$selectall";
		foreach ($columns as $col) {
			if ($col == '*selector')
				$result .= '<th class="sel"><input type="checkbox" onchange="selecto(form,checked)"/></th>';
			else if ($col == '*empStatus')
				$result .= '<th>Status</th>';
			else
				$result .= "<th>{$records[0]->{¬.$col}}</th>";
		}
		$result .= "</tr>\n</thead><tbody>\n";
		foreach ($records as $d) {
			$result .= "<tr onclick=\"go(pageform,'{$trigger}card=$d->_id')\" class=\"trigger alt". ++$alt%2 .'">';
			$c->mode = $c::COLUMNAR; $d->_context = $c; // set a default rendering context
			foreach ($columns as $col) {
				if ($col == '*empStatus') {
					$result .= '<td title="'. ($d->initiation == 'mgmt' ? 'Committed by management' : ($d->lockdate ? 'Approved by mgmt; ' : null) ."Submitted by {$d->»initiation}")
							.'"> &nbsp; <img src="images/'. ($d->lockdate ? 'check' : 'examine') .'.png"/></td>';
				} else if ($col == '*selector') {
					$result .= "<td onclick=\"event.stopPropagation()\" style=\"cursor:default\" class=\"sel\"><input type=\"checkbox\" value=\"$d->_id\" name=\"id[$d->_id]\" ".
						($this->selectedUnpaid[$d->_id] ? 'checked="1"' : null) ."/></td>";
				} else
					$result .= "<td>{$d->{».$col}}</td>";
			}
			$result .= "</tr>\n";
			if ($totals) {
				$taxable += $d->taxable;
				$taxfree += $d->taxfree;
			}
		}
		$result .= "</tbody>";
		if ($totals)
			$result .= '<tfoot><tr><th colspan="'. (count($columns)-2) .'" align="right">Unpaid totals:</th>'.
				'<th>'. t_cents::render_columnar($taxable) .'</th><th>'. t_cents::render_columnar($taxfree) ."</th></tr></tfoot>\n";
		return "$result</table>\n</div>\n";
	}

	private function Paydays( Context $c, $mgrMode )
	{
		$thisYear = date('Y');
		$lastYear = $thisYear - 1;
		$c->mode = $c::COLUMNAR;
		if (!($count = count($records = e_comppaid::collection(array('sortfield'=>'paydate',
				'where'=>"paydate >= '$lastYear-01-01'". ($this->employee ? " AND `{}_compcard`.employee = {$this->employee->_id}" : null))))))
			return "<p>There are currently no paydays on record.</p>";
		foreach ($records as $d) {
			$d->_context = $c;
			$rowsOut .= "<tr onclick=\"go(pageform,'viewpaid=$d->_id')\" class=\"trigger alt". ++$alt%2 .'">'.
				"<td>$d->»label</td><td>$d->»paydate</td>".	($mgrMode ? "<td class=\"count\">$d->»employees</td>" : null).
				"<td>$d->»cards</td><td>$d->»taxable</td><td>$d->»taxfree</td></tr>";
			if (mb_substr($d->paydate, 0, 4) == $thisYear) {
				$YTDTaxed += $d->taxable;
				$YTDNotax += $d->taxfree;
			}
		}
		$help = $mgrMode ?
				"<p>Select one to view a detailed list of all compensation line items which were paid on that day.</p>" :
				"<p>These are your past or scheduled compensation payments. Select one to see the details.</p>";
		return '<div id="paydays" class="section">'.
			"\n<h3>". ($mgrMode ? "Payment records" : "Paychecks") ." ($count)$help</h3>\n<table>\n".
			'<tr><th>'. ($mgrMode ? 'Purpose' : 'Paycheck') .'</th><th>Pay date</th>'.	($mgrMode ? '<th>Employees</th>' : null) .
			'<th>Cards</th><th>Taxed</th><th>Untaxed</th></tr>'."\n$rowsOut\n<tr><th colspan=\"". ($mgrMode ? 4 : 3) ."\" align=\"right\">Paid year-to-date:</th><th>".
			t_cents::render_columnar($YTDTaxed) ."</th><th>". t_cents::render_columnar($YTDNotax) ."</th></tr></table>\n</div>\n";
	}

	const ManageCompStyles = <<<'css'
#mcomp .notice { color:red; font-style:italic; }
#mcomp { border:none; margin:0 auto; padding:1em; width:10in }
#mcomp h1 { padding:0;margin:0; }
#mcomp span.sectionactions { float:right; margin:1em}
#mcomp div.section { margin:1em 0; border:1pt solid black; padding:2pt }
#mcomp div.section table { width:100%; margin:auto; }
#mcomp div.section .trigger { cursor:pointer; }
#mcomp div.section table tr.alt0 { background-color:#EEEEFF; }
#mcomp div.section table tr.alt1 { background-color:#DDFFFF; }
#mcomp div.section h3>p { font-size:80%; font-weight:normal; font-style:italic; margin:3pt 0 0; }
#mcomp .section table th { font-weight:normal; font-style:italic; text-align:left; }
#mcomp h2 { background-color:darkmagenta; padding:1em; margin:0; color:white; }
#mcomp h3 { background-color:darkgray; padding:.5em; margin:0; color:white; }
#mcomp #draft h3 { background-color:#CC8800; }
#mcomp #draft { border-color:#CC8800; }
#mcomp #paydays h3 { background-color:darkgreen; }
#mcomp #paydays { border-color:darkgreen; }
#mcomp #actionable h3 { background-color:darkmagenta; }
#mcomp #actionable { border-color:darkmagenta; }
#mcomp #payable h3 { background-color:darkblue; }
#mcomp #payable { border-color:darkblue; }
#mcomp .fieldlabel { font-weight:bold; color:darkblue; }
css;

}
