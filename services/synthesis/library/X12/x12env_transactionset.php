<?php

class x12seg_ST extends x12_segment
	implements x12_env_header
{
	static $transactionTypes = array(
		 '837'=>'Health care insurance claim'
		,'277'=>'Health care claim acknowledgement'
		,'999'=>'Functional acknowledgement'
		,'270'=>'Eligibility check request'
		,'271'=>'Eligibility check response'
		,'276'=>'Claim status check request'
		,'277'=>'Claim status check response'
		);

	function __construct( )
	{
		parent::__construct('Transaction set header', array('ST'
			,new x12el_code('Transaction Set Identifier Code', static::$transactionTypes)
			,new x12el_text('Transaction Set Control Number', array(1, 9))
			,new x12el_text('Implementation Convention Reference', array(1, 35))
			));
	}

	public function initialize( $typecode = '837', $controlNumber = null )
	{
		if (!$controlNumber)
			$controlNumber = substr(uniqid(), -9);
		$this->assign($typecode, x12env_transactionset::$transetid = $controlNumber, '005010X222A1');
	}

	public function payload_class()
	{
		if ($this->elements[1])
			return "x12_{$this->elements[1]}";
	}

	public function render()
	{
		x12_segment::$segmentCount = 0;
		$result = parent::render();
		return $result;
	}
}

class x12seg_SE extends x12_segment
{
	function __construct( )
	{
		parent::__construct('Transaction set trailer', array('SE'
			,new x12el_fixdec('Transaction Segment Count', array(1, 10))
			,new x12el_text('Transaction Set Control Number', array(4, 9))
			));
	}

	public function initialize( )
	{
		$this->assign(parent::$segmentCount+1, x12env_transactionset::$transetid); // the +1 includes the SE envelope trailer which has not rendered yet
	}
}

class x12env_transactionset extends x12_envelope
{
	public static $transetid; // control number uniquely identifying this transaction set

	function __construct( $payload = null ) // when we're just building an envelope
	{
		parent::__construct('Transaction set', new x12seg_ST(), new x12seg_SE($this->transetid), 'R', -1, $payload);
	}

	function initialize( )
	{
		$this->transetid = substr(uniqid(), -9);
		$this->header->initialize($this->payload->idcode, $this->transetid);
		$this->trailer->initialize($this->transetid);
		// we assume that the creator of the payload also initializes it as appropriate so we don't do that here.
	}

	public function render( )
	{
		$this->trailer->element[1]->assign($this->payload->count_segments()+2); // payload plus header and trailer
		return parent::render();
	}

	public function parse( $content )
	{
		$content = parent::parse($content);
		if ("{$this->trailer->element[2]}" != "{$this->header->element[2]}")
			throw new Exception("Transaction set ID did not match between trailer ({$this->trailer->element[2]}) and header ({$this->header->element[2]}).");
		if (($counted = $this->payload->count_segments()+2) != "{$this->trailer->element[1]}") // payload plus header and trailer
			logWarn("Transaction set segment count ($counted) did not match parsed value ({$this->trailer->element[1]}).");
		return $content;
	}
}

?>
