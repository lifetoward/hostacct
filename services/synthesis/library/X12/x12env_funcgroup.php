<?php

class x12seg_GS extends x12_segment
	implements x12_env_header
{
	function __construct( $usage = 'R' )
	{
		parent::__construct('Functional group header', array('GS'
			,new x12el_code('Functional Identifier Code', array('HC'=>'Health care claim'))
			,new x12el_text('Application Sender Code', array(2, 15))
			,new x12el_text('Application Receiver Code', array(2, 15))
			,new x12el_date('Date', 8)
			,new x12el_time('Time', 4)
			,new x12el_fixdec('Group Control Number', array(1, 9))
			,new x12el_code('Responsible Agency Code', array('X'=>'only'))
			,new x12el_text('Version Identifier Code', array(1, 12))
			), $usage);
	}

	public function initialize( $senderID, $receiverID, $groupControl = null )
	{
		if (!$groupControl)
			$groupControl = time()%1000000000;
		x12env_funcgroup::$groupControl = $groupControl;
		$this->assign('HC', $senderID, $receiverID, null, null, x12env_funcgroup::$groupControl, 'X', '005010X222A1');
	}

	public function payload_class( )
	{
		return 'x12env_transactionset';
	}
}

class x12seg_GE extends x12_segment
{
	function __construct( $usage = 'R' )
	{
		parent::__construct('Functional group trailer', array('GE'
			,new x12el_fixdec('Number of Transaction Sets Included', 6)
			,new x12el_fixdec('Group Control Number', 9)
			), $usage);
	}

	public function initialize( )
	{
		$this->assign(1,x12env_funcgroup::$groupControl);
	}

}

class x12env_funcgroup extends x12_envelope
{
	public static $groupControl;

	function __construct( )
	{
		parent::__construct("X12 Functional Group", new x12seg_GS(), new x12seg_GE(), 'R', 0, new x12env_transactionset());
	}

	function initialize( )
	{
		$this->groupControl = time();
		$this->header->initialize($this->groupControl);
		$this->trailer->initialize($this->groupControl);
		$this->payload->initialize();
	}

	public function parse( $content )
	{
		$remainder = parent::parse($content);
		if ("{$this->trailer->element[2]}" != "{$this->header->element[6]}")
			throw new Exception("Functional group ID did not match between trailer ({$this->trailer->element[2]}) and header ({$this->header->element[6]}).");
		if (($counted = $this->payload->count()) != "{$this->trailer->element[1]}")
			throw new Exception("Count of transaction sets in the functional group ($counted) did not match parsed value ({$this->trailer->element[1]}).");
		return $remainder;
	}

	public function render( )
	{
		$this->trailer->element[1]->assign($this->payload->count());
		return parent::render();
	}
}

?>
