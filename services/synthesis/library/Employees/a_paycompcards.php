<?php

class a_PayCompCards extends Action
{
	protected $paythese = array(), $payday;

	public function serialize( )
	{
		return serialize(array(parent::serialize(), $this->payday, $this->paythese));
	}
	public function unserialize( $rep )
	{
		list($parent, $this->payday, $this->paythese) = unserialize($rep);
		parent::unserialize($parent);
	}

	public function __construct( Context $c, array $cardList )
	{
		if (!$c->_portal == 'manage' || !$c->_employee->roleset['manager'])
			return logError("AUTHORIZATION FAILURE: Must be a manager in the manager portal to see all employees' pay checks.");
		$idlist = implode(',', $cardList);
		$qs = <<<sql
SELECT employee._id AS employee, CONCAT(surname, ', ', nickname, ' (', employee._id, ')') AS name,
  COUNT(DISTINCT compcard._id) AS num, GROUP_CONCAT(DISTINCT compcard._id SEPARATOR ',') AS cards,
  SUM(IF(FIND_IN_SET('taxable', flags),amount,0)) AS taxable, SUM(IF(FIND_IN_SET('taxable', flags), 0, amount)) AS taxfree
FROM compcard
  LEFT JOIN employee ON(employee._id=employee)
  LEFT JOIN person ON(person._id=person)
  LEFT JOIN compline ON(compcard=compcard._id)
  LEFT JOIN comptype ON(comptype=comptype._id)
WHERE comppaid IS NULL AND compcard._id IN ($idlist) AND initdate IS NOT NULL AND (initiation != 'empl' OR lockdate IS NOT NULL)
GROUP BY employee._id
ORDER BY surname,nickname
sql;
		foreach (capcel_get_records($qs) as $empline) {
			$this->paythese[$empline['employee']] = $empline;
			$totalCards += $empline['num'];
		}
		if ($totalCards != ($count = count($cardList)))
			throw new ErrorException("a_PayCompCards: The number of cards pulled from the system was $totalCards, while the number selected on entry was $count.");
		$this->payday = e_comppaid::create();
	}

	public function render( Context $c )
	{
		$c->action = $this;
		list($args, $post) = $c->request;
		if ($args['*exit'])
			return $this->getResult(static::CANCEL);
		if (is_array($post)) {
			foreach (array('paydate','label','notes') as $postable)
				if (array_key_exists($postable, $post))
					$postworthy[$postable] = $post[$postable];
			$this->payday->acceptValues((array)$postworthy, $c);
			if ($post['_act_'] == 'payall') {
				$this->payday->paydate = $post['paydate'];
				$this->payday->label = $post['label'];
				$this->payday->notes = $post['notes'];
				$this->payday->trackChanges("Store CompPaid #{$this->payday->_id} associating its CompCards");
				try {
					$paidId = $this->payday->store();
					foreach ($this->paythese as $emp)
						foreach (e_compcard::collection(array('where'=>"{}._id IN ($emp[cards])")) as $obj) {
							if ($obj->comppaid) { // We check for this because it's possible for two managers to pay the same card under separate simultaneous operations and this would be bad to allow.
								Instance::abortChanges();
								$this->rendered = '<p class="alert">The payment marking process was aborted because at least one card to be marked was found to already be marked as paid! This implies that another manager is also paying cards right now.</p>';
								return self::FAIL;
							}
							$obj->comppaid = $paidId;
							if (!$obj->lockdate)
								$obj->lockdate = $this->payday->paydate;
							$obj->store();
							$paid++;
						}
				} catch (Exception $ex) {
					$this->payday->abortChanges();
					throw $ex;
				}
				$this->payday->commitChanges();
				return $this->getResult(static::SUCCEED, '<p class="notice">'. $paid .' compensation cards were paid on '. t_date::format($this->payday, 'paydate') .'.</p>');
			}
		}
		foreach ($this->paythese as $x) {
			$rowsOut .= '<tr class="alt'. (++$alt%2) .'"><td class="empl">'. htmlentities($x['name']) .'</td><td class="count">'. $x['num'] .'</td>'.
				'<td class="amt">'. t_cents::render_columnar($x['taxable'], $c) .'</td><td class="amt">'. t_cents::render_columnar($x['taxfree'], $c) .'</td>'.
				'<td class="act"><button type="button" onclick="'. "pay(this,$x[num],$x[taxable],$x[taxfree])" .'">Click when paid</button><img style="display:none" src="images/check.png"/></td></tr>';
			$totalCards += $x['num'];
			$totalTaxed += $x['taxable'];
			$totalFree += $x['taxfree'];
		}
		$c->addScript(<<<js
var gobutton,countOut,taxedOut,freeOut;
var cards=0,taxable=0,taxfree=0;
var cards_=$totalCards,taxable_=$totalTaxed,taxfree_=$totalFree;
function pay(b,c,t,f){
	var row=b.parentNode.parentNode;
	row.className='paid';
	b.style.display='none';
	b.nextSibling.style.display='inline';
	cards+=c;taxable+=t;taxfree+=f;
	countOut.firstChild.innerHTML = cards;
	taxedOut.children[0].innerHTML = (taxable/100).toFixed(2);
	freeOut.children[0].innerHTML = (taxfree/100).toFixed(2);
	if(cards==cards_&&taxable==taxable_&&taxfree==taxfree_)
		gobutton.disabled=false;
}
var okflags={};
function chkgo(x){
	for(n in okflags)
		if(!okflags[n]){
			alert('Please provide valid entries for all required fields (marked with the blue arrow).');
			return;}
	go(pageform,x);
}
function capcel_validated(c,n,ok){
	if(n)okflags[n]=ok;
}
js
			);
		$c->addStyles(<<<css
#mcomp .help { color:darkcyan; font-style:italic; }
#mcomp p.help { margin:1em; }
#mcomp div.actions { text-align:center; margin:1em; }
#mcomp div.actions button { font-size:110%; }
#mcomp div.section table td { padding:5pt; }
#mcomp div.section table tr.alt0 { background-color:#EEEEFF; }
#mcomp div.section table tr.alt1 { background-color:#DDFFFF; }
#mcomp div.section table tr.paid { background-color:#888888; }
#mcomp div.section table tr.totals { background-color:white; font-weight:bold; }
#mcomp div.section table td.act { width:20%; }
#mcomp table#payday td select { width:100%; }
#mcomp td#labeltd { width:65%; }
#mcomp td input#notes { width:90%; }
#mcomp .section table tr#cardprimary select { font-size:110% }
#mcomp .section table tr#cardprimary input { font-size:110% }
#mcomp textarea { width:100%; border:2px solid black; }
#mcomp .fieldlabel { font-weight:bold; color:darkblue; }
css
			);
		$emptyColumn = t_cents::render_columnar(0, $c);
		$sumLine = "<tr class=\"totals\"><th>Paid so far &rarr;</th><td id=\"countOut\" class=\"count\"><span>0</span></td><td id=\"taxedOut\">$emptyColumn</td><td id=\"freeOut\">$emptyColumn</td></tr>";
		$totalsLine = '<tr class="totals"><th>Totals expected &rarr;</th><td class="count">'. $totalCards .'</td><td>'. t_cents::render_columnar($totalTaxed, $c) .'</td><td>'. t_cents::render_columnar($totalFree, $c) .'</td></tr>';
		$cancelTarget = $c->get_target(array('*exit'=>1));
		foreach (array('paydate','label','notes') as $f)
			$cardFields[$f] = "<span class=\"fieldlabel\">{$this->payday->{¬.$f}}</span><br/>". $this->payday->renderField($f, $c, self::INPUT);
		$this->rendered = <<<end
<div class="section">
<span class="sectionactions"><button type="button" onclick="$confirm location.replace('$cancelTarget')">&larr; Don't pay cards</button></span>
<h2>Pay compensation cards</h2>
$notice
<table id="payday">
<tr id="cardprimary"><td>$cardFields[paydate]</td><td id="labeltd">$cardFields[label]</td></tr>
<tr><td colspan="3">$cardFields[notes]</td></tr>
</table>
<p class="help">This page facilitates a careful transfer of compensation totals from this system to the method of payment.
We presume you will be entering taxable and non-taxed totals for each employee into a payment system which calculates and deducts the taxes and disburses the funds. </p>
<p class="help">As you enter the totals for each employee into the payment system, click the employee's "Paid" button to mark that row complete.
Once all employees have been paid in this way, you can then commit the entire payday by clicking "Done".</p>
<div class="active">
<table id="payments">
<tr><th class="empl">Employee</th><th class="count"># of Cards</th><th class="amt">Taxable</th><th class="amt">Non-taxed</th><th class="act">&nbsp;</th></tr>
$rowsOut
<tfoot><tr><td colspan="5"><hr/></td></tr>
$sumLine
$totalsLine</tfoot>
</table>
</div>
<div class="actions">
<button id="gobutton" type="button" onclick="chkgo('payall')" disabled="1"> Done ! </button>
</div>
<script>
gobutton=document.getElementById('gobutton');
countOut=document.getElementById('countOut');
taxedOut=document.getElementById('taxedOut');
freeOut=document.getElementById('freeOut');
</script>
</div>
end;
		return $this->getResult(self::PROCEED);
	}
}
