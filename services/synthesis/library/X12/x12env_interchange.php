<?php
include "x12_envelope.php";

class x12seg_ISA extends x12_segment
	implements x12_env_header
{
	public static $delimiters = array('element'=>'|' ,'segment'=>"~" ,'repetition'=>'^');

	static $partyIDQualifiers = array(
		 '01'=>'Unknown'
		,'14'=>'Unknown'
		,'20'=>'Unknown'
		,'27'=>'Unknown'
		,'28'=>'Unknown'
		,'29'=>'Unknown'
		,'30'=>'Federal Tax ID'
		,'33'=>'Unknown'
		,'ZZ'=>'Mutually defined'
		);

	function __construct( )
	{
		// annotations after each element line indicate x12 content string array indices of this positioned field since ISAs are fixed-position
		parent::__construct('Interchange control header', array('ISA'
			,new x12el_code("Authorization information qualifier", array('00'=>'No authorization information', '03'=>'Unknown'), 'R', true) // 4-5
			,new x12el_text("Authorization information", 10) // 7-16
			,new x12el_code("Security information qualifier", array('00'=>'No security information', '01'=>'Unknown'), 'R', true) // 18-19
			,new x12el_text("Security information", 10) // 21-30
			,new x12el_code('Interchange sender ID qualifier', static::$partyIDQualifiers) // 32-33
			,new x12el_text("Interchange sender id", 15) // 35-49
			,new x12el_code('Interchange receiver ID qualifier', static::$partyIDQualifiers) // 51-52
			,new x12el_text("Interchange receiver ID", 15) // 54-68
			,new x12el_date("Interchange date", 6) // 70-75
			,new x12el_time("Interchange time", 4) // 77-80
			,new x12el_text("Repetition separator", 1) // 82
			,new x12el_text("Interchange control version number", 5) // 84-88
			,new x12el_fixdec("Interchange control number", 9) // 90-98
			,new x12el_code('Acknowledgement requested', array('0'=>'No', '1'=>'Yes')) // 100
			,new x12el_code('Test indicator', array('T'=>'Test', 'P'=>'Production')) // 102
			,new x12el_text('Component element separator', 1) // 104
			));
	}

	public function initialize( array $sender, array $receiver, $response = false, $test = true, $intchgID = null )
	{
		if (!$intchgID)
			$intchgID = time()%1000000;
		x12env_interchange::$intchgControl = sprintf("%05d", $intchgID);
		$this->assign(array('00',null,'00',null,$sender[0],$sender[1],$receiver[0],$receiver[1],null,null,self::$delimiters['repetition'],'00501',$intchgID,$response?1:0,$test?'T':'P',x12el_comp::$delimiter));
	}

	// We override the parse method because unlike any other segment, we SET the delimiter values rather than use them
	public function parse( $content )
	{
		if (0 !== strpos($content, 'ISA'))
			throw new Exception("Invalid segment identifier! Expecting 'ISA'");
		if (strlen($content) < 106)
			throw new Exception("Unexpected end of input!");
		self::$delimiters['element'] = $content[3];
		self::$delimiters['repetition'] = $content[82];
		x12el_comp::$delimiter = $content[104];
		self::$delimiters['segment'] = $content[105];
		return parent::parse($content);
	}

	public function payload_class()
	{
		return 'x12env_funcgroup';
	}

	public function render( )
	{
		// The ISA segment is special in that it must NOT substitute values which contain delimiters because this segment is NORMATIVE as regards delimiters
		foreach ($this->elements as $e)
			$rendered[] = "$e"; // must do it this way to ensure delimiters are sub'd out of the stream
		return implode(self::$delimiters['element'], $rendered) . self::$delimiters['segment'] .(self::$delimiters['segment'] == "\n" ? null : "\n");
	}
}

class x12seg_IEA extends x12_segment
{
	function __construct( )
	{
		parent::__construct('INTERCHANGE CONTROL TRAILER', array('IEA'
			,new x12el_fixdec('Number of included functional groups', 5)
			,new x12el_fixdec('Interchange control number', 9)
			));
	}

	function initialize( )
	{
		$this->elements[1]->assign(1); // only 1 functional group is allowed under 5010 spec
		$this->elements[2]->assign(x12env_interchange::$intchgControl);
	}
}

class x12env_interchange extends x12_envelope
{
	public static $intchgControl;

	public function __get( $name )
	{
		switch ($name) {
			case 'interchangeControlID':
				return $this->intchgControl;
			case 'delimiters': // pass thru delimiters access to the header segment where they are actually defined
				return x12seg_ISA::$delimiters;
		}
	}

	public function __construct( )
	{
		parent::__construct('Electronic Data Interchange (X12)', new x12seg_ISA(), new x12seg_IEA(), 'R', 0, new x12env_funcgroup('R', 0));
	}

	public function parse( $content ) // unlike any other x12_struct, we ignore the delimiters argument and create our own set instead
	{
		$content = $this->header->parse($content); // this will actually set the delimiters values
		self::$intchgControl = $this->header->element[13];
		$content = $this->payload->parse($content);
		$content = $this->trailer->parse($content);
		if (self::$intchgControl ."" !== "{$this->trailer->element[2]}")
			throw new Exception("Interchange control number mismatch! ISA's ". self::$intchgControl ." != IEA's {$this->trailer->element[2]}");
		return $content;
	}

	public function initialize( )
	{
		$this->header->initialize($this->intchgControl = time());
		$this->trailer->initialize($this->intchgControl);
	}

	public function render( )
	{
		return parent::render();
	}
}

?>
