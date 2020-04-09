<?php

class a_ViewCompPaid extends Action
{
	public $payday, $employee;

	public function serialize( )
	{
		return serialize(array(parent::serialize(), $this->payday, $this->employee));
	}
	public function unserialize( $rep )
	{
		list($parent, $this->payday, $this->employee) = unserialize($rep);
		parent::unserialize($parent);
	}

	public function __construct( Context $c, e_comppaid $comppaid, $employee = null )
	{
		parent::__construct($c);
		if (!($mgrAuth = ($c->_portal == 'manage' && $c->_employee->roleset['manager'])) && !$employee)
			return logError("AUTHORIZATION FAILURE: Must be a manager in the manager portal to see all employees' pay checks.");
		if ($employee && $employee->_handle != $c->_employee->_handle)
			return logError("AUTHORIZATION FAILURE: An employee may only view hir own compensation data.");
		$this->payday = $comppaid;
		$this->employee = $employee;
	}

	public function render_me( $notice = null )
	{
		$c = $this->context;
		$managerMode = ($c->_portal == 'manage' && $c->_employee->roleset['manager']);
		if (!$managerMode && !$this->employee)
			$this->rendered = "Cannot view a payday without being a manager or specifying an employee.";

		list($args, $post) = $c->request;
		if ($post['_act_']=='exit')
			return $this->getResult(static::CANCEL);
		list($action,$id) = explode('=', $post['_act_'], 2);
		if ('viewcard'==$action && is_numeric($id) && $id > 0) {
			$this->subAction = new a_ViewCompCard($c, e_compcard::get($id));
			return;
		}

		$doneTarget = $c->get_target(array('exit'=>true), null, true);
		$thing = $c->_portal == 'manage' ? 'Payment record' : 'Paycheck';
		$paydate = htmlentities(t_date::format($this->payday->paydate, 'D M Y'));
		$heading = "\"{$this->payday->label}\" on $paydate". ($this->employee && $c->_portal != 'employee' ? "<br/>for $this->employee" : null);
		$data = e_compline::collection(array('sortfield'=>'earndate', 'where'=>
				"`{}`.compcard IN (SELECT _id FROM compcard WHERE ". ($this->employee ? "employee={$this->employee->_id} AND " : null) ."comppaid={$this->payday->_id})"));
		foreach ($data as $d) {
			$d->_context = $c; $c->mode = $c::COLUMNAR;
			$rows .= "<tr class=\"alt". (++$alt%2) ."\" onclick=\"go(pageform,'viewcard={$d->compcard->_id}')\" style=\"cursor:pointer\">".
				($this->employee ? null : "<td>{$d->compcard->employee}</td>") .
				"<td>$d->»earndate</td><td>{$d->comptype->»label}</td><td>$d->»quantity</td>". ($this->employee ? "<td>$d->»memo</td>" : null) .
				"<td>$d->»amount ". (($taxable = t_boolset::is_set($d->comptype->flags, 'taxable')) ? 'T' : 'N') ."</td></tr>\n";
			if ($taxable)
				$taxed += $d->amount;
			else
				$untaxed += $d->amount;
		}
		$tabels = $managerMode ? '<tr><th>Employee</th><th>Date earned</th><th>Type</th><th>Detail</th><th>Amount</th></tr>' :
			'<tr><th>Date earned</th><th>Type</th><th>Detail</th><th>Memo</th><th>Amount</th></tr>';
		$taxedTotal = t_cents::render_columnar($taxed);
		$untaxedTotal = t_cents::render_columnar($untaxed);
		$c->addStyles("#mcomp .section th { text-align:left; }", "a_ViewCompPaid");
		$this->rendered = <<<html
<div class="section" id="paydays">
<span class="sectionactions"><button class="section" onclick="go(pageform,'exit')">&larr; Done</button></span>
<h3>$thing</br>$heading<p>Click any line to view it in full detail within its card.</p></h3>
$notice
<table>
<thead>$tabels</thead>
<tbody>
$rows
</tbody>
<tfoot><tr><th colspan="3" rowspan="2" align="right">Totals</th><th align="right">Taxed:</th><th>$taxedTotal T</th></tr>
<tr><th align="right">Untaxed:</th><th>$untaxedTotal N</th></tr></tfoot>
</table>
</div>
html;
		return $this->getResult(static::PROCEED);
	}

}
