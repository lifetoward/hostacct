<?php

class x12_999 extends x12_loop
{
	public function report( $indent = 0 )
	{
		$header = $this->members[1]->members[0];
		$trailer = $this->members[2];
		return "Response to ". $header->element[1]->report(false) .' "'. $header->element[2]->report(false) ."\":\n". $trailer->element[1]->report(false) ."\n\n".
			$this->members[1]->members[1]->report();
	}

	public static $funcGroupSyntaxErrorCodes = array(
		 '1'=>'Functional Group Not Supported'
		,'2'=>'Functional Group Version Not Supported'
		,'3'=>'Functional Group Trailer Missing'
		,'4'=>'Group Control Number in the Functional Group Header and Trailer Do Not Agree'
		,'5'=>'Number of Included Transaction Sets Does Not Match Actual Count'
		,'6'=>'Group Control Number Violates Syntax'
		,'10'=>'Authentication Key Name Unknown'
		,'11'=>'Encryption Key Name Unknown'
		,'12'=>'Requested Service (Authentication or Encryption) Not Available'
		,'13'=>'Unknown Security Recipient'
		,'14'=>'Unknown Security Originator'
		,'15'=>'Syntax Error in Decrypted Text'
		,'16'=>'Security Not Supported'
		,'17'=>'Incorrect Message Length (Encryption Only)'
		,'18'=>'Message Authentication Code Failed'
		,'19'=>'Functional Group Control Number not Unique within Interchange'
		,'23'=>'S3E Security End Segment Missing for S3S Security Start Segment'
		,'24'=>'S3S Security Start Segment Missing for S3E End Segment'
		,'25'=>'S4E Security End Segment Missing for S4S Security Start Segment'
		,'26'=>'S4S Security Start Segment Missing for S4E Security End Segment'
		);

	public static $impTranSetSyntaxErrors = array(
		 '1'=>'Transaction Set Not Supported'
		,'2'=>'Transaction Set Trailer Missing'
		,'3'=>'Transaction Set Control Number in Header and Trailer Do Not Match'
		,'4'=>'Number of Included Segments Does Not Match Actual Count'
		,'5'=>'One or More Segments in Error'
		,'6'=>'Missing or Invalid Transaction Set Identifier'
		,'7'=>'Missing or Invalid Transaction Set Control Number'
		,'8'=>'Authentication Key Name Unknown'
		,'9'=>'Encryption Key Name Unknown'
		,'10'=>'Requested Service (Authentication or Encrypted) Not Available'
		,'11'=>'Unknown Security Recipient'
		,'12'=>'Incorrect Message Length (Encryption Only)'
		,'13'=>'Message Authentication Code Failed'
		,'15'=>'Unknown Security Originator'
		,'16'=>'Syntax Error in Decrypted Text'
		,'17'=>'Security Not Supported'
		,'18'=>'Transaction Set not in Functional Group'
		,'19'=>'Invalid Transaction Set Implementation Convention Reference'
		,'23'=>'Transaction Set Control Number Not Unique within the Functional Group'
		,'24'=>'S3E Security End Segment Missing for S3S Security Start Segment'
		,'25'=>'S3S Security Start Segment Missing for S3E Security End Segment'
		,'26'=>'S4E Security End Segment Missing for S4S Security Start Segment'
		,'27'=>'S4S Security Start Segment Missing for S4E Security End Segment'
		,'I5'=>'Implementation One or More Segments in Error'
		,'I6'=>'Implementation Convention Not Supported'
		);

	public function __construct( )
	{
		parent::__construct('X12 999 Functional acknowledgement transaction', array(
			 new x12_segment('Functional group response header', array('AK1'
				,new x12el_code('Functional identifier code')
				,new x12el_fixdec('Group control number', array(1,9))
				,new x12el_text('Version/Release/Industry identifier code', array(1,12), 'S', 1)
				), 'R', 1)
			,new x12_loop('2000: Transaction set response', array(
				 new x12_segment('Transaction set response header', array('AK2'
					,new x12el_code('Transaction set identifier code', x12seg_ST::$transactionTypes)
					,new x12el_text('Transaction set control number', array(4,9))
					,new x12el_text('Implementation convention reference', array(1,35), 'S')
					), 'R', 1)
				,new x12loop_999_2100()
				,new x12_segment('Transaction set response trailer', array('IK5'
					,new x12el_code('Transaction set acknowledgement code', array(
						 'A'=>'Accepted'
						,'E'=>'Accepted But Errors Were Noted; The transaction set indicated in this AK2 loop contained errors but was forwarded for further processing.'
						,'M'=>'Rejected, Message Authentication Code (MAC) Failed'
						,'R'=>'Rejected; The transaction set indicated in this AK2 loop contained errors and was NOT forwarded for further processing. It will need to be corrected and resubmitted.'
						,'W'=>'Rejected, Assurance Failed Validity Tests'
						,'X'=>'Rejected, Content After Decryption Could Not Be Analyzed'
						))
					,new x12el_code('Implementation transaction set syntax error code', self::$impTranSetSyntaxErrors, 'S')
					,new x12el_code('Implementation transaction set syntax error code', self::$impTranSetSyntaxErrors, 'S')
					,new x12el_code('Implementation transaction set syntax error code', self::$impTranSetSyntaxErrors, 'S')
					,new x12el_code('Implementation transaction set syntax error code', self::$impTranSetSyntaxErrors, 'S')
					,new x12el_code('Implementation transaction set syntax error code', self::$impTranSetSyntaxErrors, 'S')
					), 'S', -1)
				), 'S', -1) // loop 2000
			,new x12_segment('Functional group response trailer', array('AK9'
				,new x12el_code('Function group acknowledge code', array(
					 'A'=>"Accepted; This code value can only be used if there are no AK2 loops or all IK501 values = `A´."
					,'E'=>"Accepted, But Errors Were Noted. The functional group indicated in this 999 contained errors but was forwarded for further processing."
					,'M'=>"Rejected, Message Authentication Code (MAC) Failed"
					,'P'=>"Partially Accepted, At Least One Transaction Set Was Rejected"
					,'R'=>"Rejected; The functional group indicated in this 999 contained errors and was NOT forwarded for further processing. It will need to be corrected and resubmitted."
					,'W'=>"Rejected, Assurance Failed Validity Tests"
					,'X'=>"Rejected, Content After Decryption Could Not Be Analyzed"
					))
				,new x12el_fixdec('Number of transaction sets included', array(1,6))
				,new x12el_fixdec('Number of received transaction sets', array(1,6))
				,new x12el_fixdec('Number of accepted transaction sets', array(1,6))
				,new x12el_code('Functional group syntax error code', self::$funcGroupSyntaxErrorCodes, 'S')
				,new x12el_code('Functional group syntax error code', self::$funcGroupSyntaxErrorCodes, 'S')
				,new x12el_code('Functional group syntax error code', self::$funcGroupSyntaxErrorCodes, 'S')
				,new x12el_code('Functional group syntax error code', self::$funcGroupSyntaxErrorCodes, 'S')
				,new x12el_code('Functional group syntax error code', self::$funcGroupSyntaxErrorCodes, 'S')
				))
			)); // 999 transaction set
	}
}

class x12loop_999_2100 extends x12_loop
{
	public function report( $indent = 0 )
	{
		if (!$this->assigned())
			return null;
		for ($x = 0; $x < $indent; $x++)
			$tabs .= "\t";
		$segError = $this->members[0]->element;
		if ($this->members[2]->assigned())
			$els[] = $this->members[2]->report($indent+1);
		return "Error in segment ". (($loop="$segError[3]") ? "$loop:" : null) ."$segError[1] (#$segError[2]): ". $segError[4]->report(false) ."\n$tabs".
			(count($els) ? "\t". implode("\n\t$tabs", $els). "\n$tabs" : null) .($this->repetition ? $this->repetition->report($indent) : null);
	}

	public function __construct()
	{
		parent::__construct('2100: Error identification', array(
			 new x12_segment('Error identification header', array('IK3',
				 new x12el_code('Segment ID code', array())
				,new x12el_fixdec('Segment position in transaction set', array(1,10))
				,new x12el_text('Loop identifier code', array(1,4), 'S')
				,new x12el_code('Implementation Segment Syntax error code', array(
						 1=>"Unrecognized segment ID"
						,2=>"Unexpected segment"
						,3=>"Required segment missing"
						,4=>"Loop occurs over maximum times"
						,5=>"Segment exceeds maximum use"
						,6=>"Segment not in defined transaction set"
						,7=>"Segment not in proper sequence"
						,8=>"Segment has data element errors"
						,'I4'=>"Implementation \"Not used\" segment present"
						,'I6'=>"Implementation dependent segment missing"
						,'I7'=>"Implementation loop occurs under minimum times"
						,'I8'=>"Implementation segment below minimum use"
						,'I9'=>"Implementation dependent \"Not used\" segment present"), 'S')
				))
			,new x12seg_CTX('Segment context', 'S', 10)
			,new x12loop_999_2110()
			), 'S', -1); // loop 2100
	}
}

class x12loop_999_2110 extends x12_loop
{
	public function report( $indent = 0 )
	{
		if (!$this->assigned())
			return null;
		for ($x = 0; $x < $indent; $x++)
			$tabs .= "\t";
		$error = $this->members[0]->element;
		$c1 = $error[1]->component[1];
		$c2 =$error[1]->component[2];
		return "Element {$error[1]->component[0]}". ($c1->assigned() ? ".$c1" : null) . ($c2->assigned() ? ".$c2" : null) .": ".
			$error[3]->report(false) .("$error[4]" ? " (\"$error[4]\" given)" : null). ($this->repetition ? "\n$tabs". $this->repetition->report($indent) : null);
	}

	public function __construct()
	{
		parent::__construct('2110 Implementation data element note', array(
			 new x12_segment('Implementation data element note', array('IK4'
				,new x12el_comp('Position in segment', array(
					 new x12el_fixdec('Element position in segment', array(1,2))
					,new x12el_fixdec('Component data element position in composite', array(1,2), 'S')
					,new x12el_fixdec('Repeating data element position', array(1,4), 'S')
					))
				,new x12el_fixdec('Data element reference number', array(1,4), 'S')
				,new x12el_code('Implementation data element syntax error code', array(
						 '1'=>'Required Data Element Missing'
						,'2'=>'Conditional Required Data Element Missing'
						,'3'=>'Too Many Data Elements'
						,'4'=>'Data Element Too Short'
						,'5'=>'Data Element Too Long'
						,'6'=>'Invalid Character In Data Element'
						,'7'=>'Invalid Code Value'
						,'8'=>'Invalid Date'
						,'9'=>'Invalid Time'
						,'10'=>'Exclusion Condition Violated'
						,'12'=>'Too Many Repetitions'
						,'13'=>'Too Many Components'
						,'I10'=>'Implementation "Not Used" Data Element Present'
						,'I11'=>'Implementation Too Few Repetitions'
						,'I12'=>'Implementation Pattern Match Failure'
						,'I13'=>'Implementation Dependent "Not Used" Data Element Present'
						,'I6'=>'Code Value Not Used in Implementation'
						,'I9'=>'Implementation Dependent Data Element Missing'
						))
				,new x12el_text('Copy of bad data element', array(1,99), 'S')
				))
			,new x12seg_CTX('Element context', 'S', 10)
			), 'S', -1); // loop 2110
	}
}

class x12seg_CTX extends x12_segment
{
	public function __construct( $label, $usage = 'R', $repeat = 1 )
	{
		parent::__construct($label, array('CTX'
			,new x12el_comp('Context identification', array(
				 new x12el_text('Context name', array(1,35)) // Special note: if this is CLM01, we're referring to an 837 transaction. Not sure how that's clear or helpful.
				,new x12el_text('Context reference', array(1,35), 'S')
				))
			,new x12el_code('Segment ID code', array(), 'S')
			,new x12el_fixdec('Segment position in transaction set', array(1,10), 'S')
			,new x12el_text('Loop identifier code', array(1,4), 'S')
			,new x12el_comp('Position in segment', array(
				 new x12el_fixdec('Element position in segment', array(1,2))
				,new x12el_fixdec('Component data element position in composite', array(1,2), 'S')
				,new x12el_fixdec('Repeating data element position', array(1,4), 'S')
				), 'S', 1)
			,new x12el_comp('Reference in segment', array(
				 new x12el_fixdec('Data element reference number', array(1,4))
				,new x12el_fixdec('Data element reference number', array(1,4), 'S')
				), 'S', 1)
			), $usage, $repeat);
	}
}

?>
