<?php
/**
* A client is a tracked customer of the business, typically with an account for A/R and service history.
* This is the primary representative of themselves or a company who purchases our deliverables.
* Each client can belong to a particular business unit (bizunit), which can help to designate income from their receipts as belonging in a particular account
*/
class Client extends Element
{
	public static $table='biz_client', $singular="Client", $plural="Clients", $descriptive="Client info",
		$fielddefs = [
			 'entity'=>['name'=>'entity', 'class'=>'e_entity', 'type'=>'include', 'sort'=>true, 'identifying'=>true,
				'help'=>"This is the primary label for this client. A client can be any type of entity. The entity would typically have other includers."],
			'handle'=>['name'=>'handle', 'class'=>'t_string', 'label'=>"Filing label", 'pattern'=>"^[\\w]*\$", 'sort'=>"ASC", 'unique'=>'handle', 'required'=>true,
				'help'=>"The system will use this value like the label of a file folder, in links, directory names, etc. It should be short and easily recognized as representing this client. Only letters, digits, and underscores may be used."],
			'discount'=>['name'=>'discount', 'class'=>'t_percent', 'label'=>"General discount", 'initial'=>0,
				'help'=>"If you set this value, the discount will automatically be initialized with this for all new client transactions."],
			'notices'=>['name'=>'notices', 'class'=>'t_boolset', 'label'=>"Notification preference", 'help'=>"The primary contact will automatically receive notice of these events when they happen.",
				'options'=>['statements'=>"Statements", 'trxs'=>"Transactions", 'tasks'=>"Tasks completed"], ],
			],
		$hints = [
			'a_Browse'=>[ 'include'=>['name','contact','handle'], 'triggers'=>[ 'banner'=>"create", 'row'=>[ 'update','display'], 'multi'=>'addresses' ], ],
			],
		$operations = [
			'list'=>['action'=>'a_Browse', 'role'=>'Staff'],
			'display'=>['action'=>'a_Display', 'role'=>['*owner','Staff']],
			'create'=>['action'=>'a_Edit', 'role'=>'Staff'],
			'update'=>['action'=>'a_Edit','role'=>['*owner','Staff']],
			'addresses'=>['action'=>'a_ProduceAddressesPDF', 'icon'=>'envelope'],
			'promote'=>['targets'=>[],],
			];

	public function formatted()
	{
		return $this->°name;
	}

	// From the old etc.php
	function notify( $msgs, $subject = 'Billing notice' )
	{
		$message = '<html><head><style type="text/css">'.
			'body { font-size:12pt; background-color:white; font-family:arial;color:black; }'.
			'p { border-width:0;position:relative;left:5%;width:60%; }'.
			"</style></head><body>\n".
			"<h3>Guy Johnson, technical services - Billing notice</h3>\n<p>".
			implode("</p>\n<p>", $msgs) ."</p>\n</body></html>\n";
		sendEmail("$client->contact <{$client->contact->°email}>", $subject, $message);
		return;
	}
}
