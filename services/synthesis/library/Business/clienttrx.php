<?php
/**
* A Client transaction is a charge or a credit against one client's "account", which really means the shared A/R account tagged for the Client.
* We track the project in the revenue tag and the client in the A/R tag.
*/
class ClientTrx extends Element
{
	public static $table = 'biz_clienttrx',  $singular = "Client Transaction", $plural = "Client Transactions", $descriptive = "Client Transaction details"
		,$fielddefs = [
			 'project'=>['name'=>'project', 'type'=>'belong', 'class'=>'Project', 'identifying'=>true, 'sort'=>true, 'width'=>8, 'filter'=>Project::ActiveFilter ]
	// technically the client can now be understood as tagged with the A/R entry
			,'item'=>['name'=>'item', 'type'=>'require', 'class'=>'ClientItem', 'sort'=>true, 'identifying'=>true, 'width'=>8, 'label'=>"Item", 'filter'=>ClientItem::ActiveFilter]
	// item is essential
			,'quantity'=>['name'=>'quantity', 'class'=>'t_decimal', 'label'=>"Quantity", 'initial'=>1, 'width'=>4, 'format'=>'5.2', 'range'=>'0:99999', 'before'=>'$', 'after'=>'USD']
	// quantity is essential except when the rate or unit of the item is null; We use t_decimal because that's how to render without a rate or unit.
			,'extprice'=>['name'=>'extprice', 'class'=>'t_dollars', 'label'=>"Extended price", 'required'=>true, 'width'=>4, 'initial'=>0]
	// the extended price is important as it reflects any discounts vs. the nominal rate x quantity OR it may partially offset by packaged entitlements
			,'stdprice'=>['name'=>'stdprice', 'class'=>'t_dollars', 'label'=>"Standard price", 'width'=>3, 'disabled'=>true, 'initial'=>0
				, 'derived'=>"{}_item.rate * {}.quantity"]
			,'discount'=>['name'=>'discount','class'=>'t_dollars', 'label'=>"Effective discount", 'width'=>3, 'initial'=>0
				, 'derived'=>"{}_item.rate*{}.quantity-{}.extprice"]
			,'pctoff'=>['name'=>'pctoff','class'=>'t_percent', 'label'=>"Discount rate", 'width'=>2, 'initial'=>0, 'format'=>'0.2'
				, 'derived'=>"({}_item.rate*{}.quantity-{}.extprice)/({}_item.rate*{}.quantity)"]
			,'trx'=>['name'=>'trx', 'type'=>'include', 'class'=>'Transaction', 'sort'=>true, 'identifying'=>true, 'readonly'=>true,
				'override'=>['trxdate'=>['width'=>4] ] ]
	// this trx is what we are
			,'package'=>['name'=>'package', 'type'=>"refer", 'class'=>'ClientTrx', 'label'=>"Delivered under package", 'readonly'=>true ]
	// when sold under an entitlement, the package previously sold must be referenced here
			,'overage'=>['name'=>'overage', 'class'=>'t_decimal', 'label'=>"Quantity over basic entitlement", 'readonly'=>true, 'initial'=>0,
				'help'=>"It's important to retain this just to be informative and for double-checking that entitlements are remaining whole. Only relevant when package is not null."],
			'statement'=>[ 'name'=>'statement', 'class'=>'Statement', 'label'=>"Statement", 'type'=>'refer', 'readonly'=>true, ],
//			'subscription'=>[ 'name'=>'subscription', 'class'=>'Subscription', 'label'=>"Subscription", 'type'=>'refer', 'readonly'=>true, ],
	// when a package applies
			],
		$operations = [ // actions allow general purpose actions to know how to interact with this element class in various situations
			'display'=>['role'=>'Staff','action'=>'a_Trx'] ,
			'create'=>['role'=>'Finance','action'=>'a_Trx'],
			'list'=>['role'=>'Staff'],
			],
		$hints = [ // hints are provided for general purpose actions to be able to customize rendering of this particular element class
			'a_Browse'=>[ 'include'=>['project','trxdate','label','credittotal'], 'triggers'=>['banner'=>'create', 'row'=>['display'], ], ],
			];

	public function formatted()
	{
		return "$this->°trx";
	}

	public function renderTrx( TrxRendering $R )
	{
        $R->mode = $R::INPUT;
        $this->_rendering = $R;
		$R->idprefix = null;

		// PART 1: Translate the native Transaction into the UI field structure so past state is rendered.
		// Note that sorting rules for contained AcctEntry's ensure Credits appear at index 0 and Debits at index 1 in the entries array.
		if (!$this->unvalidated && count($this->entries)) { // unvalidated is only true if data was submitted that failed validation.
			if ($this->extprice != $this->entries[0]->amount || $this->extprice != $this->entries[1]->amount || count($this->entries) != 2)
				throw new Exception("The Client transaction does not appear to be self-consistent. ".
						"We have Extended price = $this->°extprice, credit amount = {$this->entries[0]->°amount}, and debit amount = {$this->entries[1]->°amount}");
		}

		// PART 2: Render the fields
		// It's important that we render the fields before adding the Javascript stuff because we override some of their configuration in our custom scripts below.
		if ($this->project && !$this->pctoff)
			$this->pctoff = $this->project->discount;
		$fds = $this->getFieldDefs();
        foreach (['trxdate','project','item','quantity','stdprice','pctoff','discount','extprice','details'] as $fn)
			$inputControls .= $R->renderFieldWithRowBreaks($this, $fds[$fn]);

		$R->addScript("var itemData = ". ($this->item ? $this->item->asJSON(['rate','unit','dplaces','rounding']) : "{rate:1,unit:null,dplaces:2,rounding:'nearest'}") .";", 'ItemData');
		$R->addScript(sprintf("var stat = { m:'%s', s:%d, e:%d, d:%d, p:%f, r:%d, u:'%s', q:%d, item:%d, project:%d }"
			,$this->extprice ? 'extprice' : 'pctoff' // 'managing' discount field
			,$this->stdprice * 100 // standard price in cents
			,$this->extprice * 100 // extended or effective price in cents
			,$this->discount * 100 // discount amount in cents
			,$this->pctoff // percentage of discount as a number between 0 and 10 inclusive
			,$this->item && $this->item->rate ? $this->item->rate*100 : 0 // item cost per unit
			,$this->item ? $this->item->unit : "" // unit label
			,$this->quantity // numeric quantity
			,$this->item ? $this->item->_id : 0
			,$this->project && $this->project->client ? $this->project->client->_id : 0
			), 'native/numeric status data');
		/* IMPORTANT: In the above stat data object we keep the "natively useful" form of these variables, often differing from in PHP or input controls. */
		$R->addStyles(".fn-extprice { font-size:larger; font-weight:bold; color:darkgreen; }\n.input-group .form-control.managing { border-color:#FF7F00; }", 'cssClientTrx');
		$this->addScript($R);
		$this->addReadyScript($R);
		$R->header = "<h1>". ($this->_stored ? null : "New ") . htmlentities(static::$singular) ."</h1>";
		$R->content = "<div class=\"row\">\n$inputControls\n</div>\n";
	}

	public function populateTrx( array $values, Context $c = null )
	{
		$disp = count($this->trx->entries) ? 'change' : 'new';
		// We need to validate and obtain the client and item now so we can assemble a suitable transaction label.
		$problems = [];
		if (!is_numeric($values['item']) || $values['item'] < 1 || !($item = ClientItem::get($values['item']))) // assignment intended
			$problems['item'] = "Item must be set.";
		if (!is_numeric($values['project']) || $values['project'] < 1 || !($project = Project::get($values['project']))) // assignment intended
			$problems['project'] = "Project must be set.";
		if (!is_numeric($values['quantity']) || $values['quantity'] <= 0)
			$problems['quantity'] = "Quantity must be greater than 0.";
		if (count($problems))
			throw new BadFieldValuesX($problems, "Preliminary required items were not adequately provided.");

		if ($values['discount'] > 0)
			$special = " ($values[pctoff]% off)";

		$receivable = isset($this->entries[0]) && $this->entries[0]->account instanceof Account ? $this->entries[0]->account :
			Account::get(['where'=>"`{}`.`class`= 130 AND ". Account::ActiveFilter, 'filter'=>['class'=>130, 'flags'=>'!inactive']]);
		$revenue = isset($this->entries[1]) && $this->entries[1]->account instanceof Account ? $this->entries[1]->account :
			Account::get(['where'=>"`{}`.`class`=410 AND ". Account::ActiveFilter, 'filter'=>['class'=>410, 'flags'=>'!inactive']]);
		if (!$receivable instanceof Account || !$revenue instanceof Account)
			throw new Exception("Posting a Client transaction requires active Earned revenue and Earned receivables accounts.");

		$converted =
			[	'trxdate' => $values['trxdate'],
				'project'=>$values['project'],
				'item'=>$values['item'],
				'quantity'=>$values['quantity'],
				'extprice'=>$values['extprice'],
				'details' => $values['details'],
				'label' => strtoupper($item->°which) ." $project->client for $item: $values[quantity] {$item->°unit}s$special",
				'entries' => [
					[ 	'_id'=>$this->entries[0]->_id,
						'type' =>AcctEntry::Credit,
						'account' =>($item->which==AcctEntry::Credit ? $receivable : $revenue),
						'amount' =>$values['extprice']*1,
						'memo' =>($item->which==AcctEntry::Credit ? "$item" : "$project"),
						'tag'=>($item->which==AcctEntry::Credit ? $project->client : $project),
						'disposition' => $disp,
						],
					[	'_id'=>$this->entries[1]->_id,
						'type' => AcctEntry::Debit,
						'account' =>($item->which==AcctEntry::Credit ? $revenue : $receivable),
						'amount' =>$values['extprice']*1,
						'memo' =>($item->which==AcctEntry::Credit ? "$project" : "$item"),
						'tag'=>($item->which==AcctEntry::Credit ? $project : $project->client),
						'disposition' => $disp,
						],
					], // entries
				];
		return $this->acceptValues($converted, $c);
	}

	protected function addScript( TrxRendering $R )
	{
		$R->addScript(<<<jscript
function validate_trx() {
	// Validation (really synchronization) is performed in the change handlers, populating the stat data object.
	// Here we just render those values into their input controls and check for completeness.
	$('#discount').val(stat.d.asDollars());
	$('#extprice').val(stat.e.asDollars());
	$('#stdprice').val(stat.s.asDollars());
	(p=$('#pctoff')).val((stat.p*100).toFixed(p.prop('decimalPlaces')));
	$(':input').removeClass("managing");
	$('#'+stat.m).addClass("managing");
	var ok=stat.item && stat.project && stat.q;
	$('button#trigger-store').prop('disabled',!ok);
	return ok;
}
jscript
		, 'jsClientTrx');
	}

	protected function addReadyScript( TrxRendering $R )
	{
		if ($this->project)
			$discountEnable = ".prop('disabled',false)";
		$amountEnable = ".prop('disabled',". ($this->item ? 'false' : 'true') .")";
		$R->addReadyScript(<<<jscript
$('#discount')$discountEnable.on('change',function(e){
	stat.d = this.value.length ? this.value.asCents() : 0;
	stat.e = stat.s-stat.d;
	stat.p = stat.s ? stat.d/stat.s : 0;
	stat.m = 'discount';
	validate_trx();
});
$('#pctoff')$discountEnable.on('change',function(e){
	stat.p = this.value.length ? this.value/100 : 0;
	stat.d = stat.s*stat.p;
	stat.e = stat.s-stat.d;
	stat.m = 'pctoff';
	validate_trx();
});
$('#extprice')$amountEnable.on('change',function(e){
	stat.e = this.value.length ? this.value.asCents() : 0;
	stat.d = stat.s-stat.e;
	stat.p = stat.s ? stat.d/stat.s : 0;
	stat.m = 'extprice';
	validate_trx();
});
$('#project').on('change',function(e){
	stat.project=this.value.length ? this.value*1 : 0;
	if (!stat.project) return;
	$(this).prop('disabled',true); // pending AJAX update completion
	$('#discount').prop('disabled',true);
	$('#pctoff').prop('disabled',true);
	$.ajax({url:"ajax.php",
		data:{class:"Project", id:stat.project, include:['discount']},
		dataType:'json',
		success:function(d){
			stat.p = d.discount;
			$('#discount').prop('disabled',false);
			$('#pctoff').prop('disabled',false).val(stat.p*100).change();
			$('#project').prop('disabled',false).focus();
			},
		});
});
$('#item').on('change',function(e){
	stat.item=this.value.length ? this.value*1 : 0;
	if (!stat.item) return;
	$(this).prop('disabled',true); // pending AJAX update completion
	$('#quantity').prop('disabled',true);
	var x = $.ajax({url:"ajax.php", dataType:'json',
		data:{class:"ClientItem", id:stat.item, include:['rate','unit','dplaces','rounding']},
		success:function(d){
			itemData = d;
			var newUnit=(stat.u!=itemData.unit);
			stat.u = itemData.unit;
			stat.r = itemData.rate*100;
			var \$q=$('#quantity').prop('disabled',false);
			if (!itemData.unit) {
				$('label[for="quantity"]').html("List price");
				\$q.val(stat.r.asDollars()).prop('decimalPlaces',2).prop('roundingPref',itemData.rounding);
			} else {
				$('label[for="quantity"]').html("$this->¬quantity");
				\$q.prop('decimalPlaces',itemData.dplaces).prop('roundingPref',itemData.rounding);
				if(newUnit)\$q.val(1);
			}
			\$q.next().html(itemData&&itemData.unit?itemData.unit+'s @ \$'+itemData.rate+' / '+itemData.unit:'USD');
			if(itemData&&itemData.unit&&itemData.rate)
				\$q.prev().hide();
			else
				\$q.prev().show();
			\$q.change();
			$('#item').prop('disabled',false).focus();
			},
		});
});
(\$q=$('#quantity')).off('change')$amountEnable.on('change',function(e){
	// Special case of time-based entry, which we convert to decimal and round as defined for the field.
	if (this.value.length && this.value.search(/^\d*:\d\d\$/)>=0) {
		var colon=this.value.indexOf(':');
		var q=(1*this.value.slice(0,colon))+(1*this.value.slice(colon+1))/60;
		\$q.val(q.toString());
	}
	validate_decimal(this);
	stat.q = stat.u ? this.value*1 : this.value.asCents();
	stat.s = stat.u ? Math.round(stat.q * stat.r) : stat.q;
	stat.d = stat.s * stat.p;
	stat.e = stat.s - stat.d;
	$('#extprice').prop('disabled',false);
	validate_trx();
});
$('#item').change(); // initializes the form
$('#trxdate').focus(); // sets the cursor, ready to roll
jscript
		, 'jsClientTrxReady');
	}
}
