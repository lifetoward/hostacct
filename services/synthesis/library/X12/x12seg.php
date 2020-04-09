<?php

class x12seg_OI extends x12_segment
{
	public function __construct( )
	{
		parent::__construct('Other insurance', array('OI'
			,new x12el_notused('Claim filing indicator code')
			,new x12el_notused('Claim submission reason code')
			,new x12el_code('Assignment of Benefits indicator; Yes/No condition response', array('Y'=>"Yes", 'N'=>'No', 'W'=>"?"))
			,new x12el_code('Patient signature source code', array('P'=>'Provider(?)'), 'S')
			,new x12el_notused('Provider agreement code')
			,new x12el_code('Release of information code', array('I'=>'?', 'Y'=>'Yes(?)'))
			), 'R', 0);
	}
}

class x12seg_SV1 extends x12_segment
{
	public function __construct( )
	{
		parent::__construct('Professional service', array('SV1'
			,new x12el_comp('Composite medical procedure', array(
				 new x12el_code('Composite identifier', array('ER','HC','IV','WK'), 'R')
				,new x12el_code('Procedure code', array(), 'R')
				,new x12el_code('Procedure modifier', array(), 'S')
				,new x12el_code('Procedure modifier', array(), 'S')
				,new x12el_code('Procedure modifier', array(), 'S')
				,new x12el_code('Procedure modifier', array(), 'S')
				,new x12el_code('Description', array(), 'S')
				), 'R') // 01
			,new x12el_numeric('Line item charge amount', array(1,18), 'R') // 02
			,new x12el_code('Unit or basis for measurement') // 03
			,new x12el_numeric('Service unit count', array(1,15)) // 04
			,new x12el_code('Facility code', x12seg_CLM::$facilityTypeCodes, 'S') // 05
			,new x12el_notused('Service type code') // 06
			,new x12el_comp('Composite diagnosis pointer', array( // 07
				 new x12el_fixdec('Diagnosis code pointer', array(1,2), 'R')
				,new x12el_fixdec('Diagnosis code pointer', array(1,2), 'S')
				,new x12el_fixdec('Diagnosis code pointer', array(1,2), 'S')
				,new x12el_fixdec('Diagnosis code pointer', array(1,2), 'S')
				), 'R')
			// remainder are not used or situational and not needed by us.
			), 'R', 0);
	}

	public function initialize( $code, $price, $units, $desc = null, $diagnosisPointers = array(1), $facility = '11' )
	{
		// We may need to feed the facility value from the scrsession location information! I'm not setting it at this time, because it's an override on the claim.
		$this->assign(array(array('HC', $code, null, null, null, null, $desc), $price, 'UN', $units, $facility, null, $diagnosisPointers));
	}
}

class x12seg_LX extends x12_segment
{
	public static $lineCounter = 0;

	public function __construct( )
	{
		parent::__construct('Service line counter', array('LX'
			,new x12el_numeric('Count', array(1,15))
			), 'R', 0);
	}

	public function render( )
	{
		$this->assign(++self::$lineCounter);
		return parent::render();
	}

	public function initialize( ) { /* do nothing */ }

}

class x12seg_NM1 extends x12_semantic
{
	public static $purposeCodes = array(
		 '41'=>'Submitter'
		,'40'=>'Receiver'
		,'85'=>'Billing provider'
		,'87'=>'Pay-to provider'
		,'IL'=>'Insured or subscriber'
		,'PR'=>'Payer'
		,'QC'=>'Patient'
		);
	public static $entityTypes = array(
		 '1'=>'Person'
		,'2'=>'Non-person'
		);

	public function __construct( $code, $label= 'Entity Name', $usage = 'R', $repeat = 0 )
	{
		parent::__construct($label, array(array('NM1', $code, $purposeCodes)
			,new x12el_code('Entity identifier code')
			,new x12el_code('Entity type qualifier', static::$entityTypes)
			,new x12el_text('Last or Organization name', array(1,60))
			,new x12el_text('First name', array(1,35), 'S') // only if x[2] == 1
			,new x12el_text('Middle name', array(1,25), 'S')
			,new x12el_text('Name prefix', array(1,10), 'S')
			,new x12el_text('Name suffix', array(1,10), 'S')
			,new x12el_code('Identification code qualifier', array('46'=>'only'))
			,new x12el_text('Identifier', array(2,80))
			), $usage, $repeat);
	}

	public function initialize( Element $entity, $ident = null )
	{
		if (!$entity->includes('e_entity'))
			throw new ErrorException("NM1 initializer requires an Element which includes e_entity.");
		if ($entity->includes('e_person'))
			$this->assign(array(2=>1, 3=>$entity->surname, 4=>$entity->nickname, 7=>$entity->suffix));
		else
			$this->assign(array(2=>2, 3=>$entity->name));
		if (is_array($ident))
			$this->assign(array(8=>$ident[0], 9=>$ident[1]));
	}
}

class x12seg_N3 extends x12_segment
{
	public function __construct( $usage = 'R', $repeat = 0 )
	{
		parent::__construct('Address line(s)', array('N3'
			,new x12el_text('Address line 1', array(1,55))
			,new x12el_text('Address line 2', array(1,55),'S')
			), $usage, $repeat );
	}

}

class x12seg_N4 extends x12_segment
{
	static $countrySubdivisions = array(
		);
	static $countryCodes = array(
		 'USA'=>'United States of America'
		);

	public function __construct( $usage = 'R', $repeat = 0 )
	{
		parent::__construct('City, State, Zip', array('N4'
			,new x12el_text('City', array(2,30))
			,new x12el_code('State', x12el_code::$statesAndProvinces, 'S')
			,new x12el_text('ZIP code', array(3,15), 'S')
			,new x12el_code('Country', static::$countryCodes, 'S')
			,new x12el_notused('Location qualifier')
			,new x12el_notused('Location identifier')
			,new x12el_code('Country subdivision', static::$countrySubdivisions, 'S')
			), $usage, $repeat );
	}

}

class x12seg_BHT extends x12_semantic
{
	public static $purposeCodes = array( );

	public function __construct( $code, $label = 'Begin Hierarchical Transaction' )
	{
		parent::__construct($label, array(array('BHT', $code, static::$purposeCodes)
			,new x12el_code('Hierarchical structure code', array('0019'=>'Information Source, Subscriber, Dependent'))
			,new x12el_code('Transaction Set Purpose Code', array('00'=>'Unknown', '18'=>'Unknown'))
			,new x12el_text('Originator Application Transaction ID', array(1,50))
			,new x12el_date('Transaction Set Creation Date', 8)
			,new x12el_time('Transaction Set Creation Time', 6)
			,new x12el_code('Claim or Encounter ID', array('31'=>'Unknown','CH'=>'Chargeable fee for service','RP'=>'Unknown'))
			), 'R');
	}

	public function initialize( $appID, $claimType = 'CH' )
	{
		$this->assign(array(2=>'00', $appID, null, null, 'CH'));
	}

	public function render( )
	{
		x12seg_HL::$renderCount = 0;
		return parent::render();
	}

}

class x12seg_HL extends x12_semantic
{
	public static $levelCodes = array(
		 '20'=>'Submitter'
		,'22'=>'Subscriber'
		,'23'=>'Patient'
		);

	public static $renderCount = 0; // The value in HL01 is a continually increasing count of these segments' appearance in the data.

	public function __construct( $level, $hasSub = true, $usage = 'R', $repeat = 0 )
	{
		parent::__construct('Hierarchical level', array(array('HL', $level, static::$levelCodes, 3)
			,new x12el_text('Hierarchical ID number', array(1,12))
			,new x12el_text('Hierarchical Parent ID number', array(1,12), 'S')
			,new x12el_code('Hierarchical level code')
			,new x12el_code('Hierarchical child code', array('0'=>'Bottom of hierarchy','1'=>'Hierarchy continues below'))
			), $usage, $repeat );
	}

	public function initialize( $hasSub )
	{
		// Note that we are ignoring the parent ID element!
		$this->assign(array(4=>$hasSub ? 1 : 0));
	}

	public function render( )
	{
		// We must keep track of our parent HL's ID and we do this by maintaining an ordered stack of different HL codes and the latest IDs of each code.
		static $codeStack, $codeIDs;
		if (!self::$renderCount) {
			$codeStack = array();
			$codeIDs = array();
		}
		$myID = ++self::$renderCount;
		$myCode = $this->element[3]->value;
		$parentID = null; // This will remain untouched only when I find my code to be the last one in the stack, ie. the top level. That's because the loop below will break naturally immediately after a code match and ID update.
		foreach ($codeStack as $code) {
			if ($codeIDs[$myCode] == $myID) { // Happens only when our code was found in the stack on the preceding iteration
				$parentID = $codeIDs[$code]; // This iteration points to the next code in the stack, so the ID on record for that code is my parent ID
				break; // Having gotten parentID, I am done.
			}
 			if ($code == $myCode) // Once we hit, we own this code and let the loop go around one more time to get the next code's ID as our parent ID
				$codeIDs[$myCode] = $myID; // I overwrite my sibling's ID with my own because my sibling can no longer have children, but now I can.
		}
		if ($codeIDs[$myCode] != $myID) { // this just means that my code was not found in the stack... I'm a new HL level
			$parentID = $codeIDs[reset($codeStack)]; // appropriately gets null when the stack has been empty so far... in such cases I have no parent
			array_unshift($codeStack, $myCode); // add this code which has not been seen before to the front of the stack
			$codeIDs[$myCode] = $myID; // register my ID for this brand new code... I may be someone's parent later
		}
		$this->assign(array(1=>$myID, 2=>$parentID));
		return parent::render();
	}

	public function parse( $content )
	{
		if (!$this->is_next($content))
			return $content;
		$this->assigned = true;
		return parent::parse($content);
	}

}

class x12seg_PER extends x12_semantic
{
	public static $purposeCodes = array(
		 'IC'=>'Contact information'
		);

	public static $commQualifiers = array(
		 'TE'=>'Telephone number'
		,'EM'=>'Email address'
		,'FX'=>'Fax number'
		,'EX'=>'Extra?'
		);

	public function __construct( $code = 'IC', $label = 'Contact information', $usage = 'R', $repeat = 0 )
	{
		parent::__construct($label, array(array('PER', $code, static::$purposeCodes)
			,new x12el_code('Contact function code') // IC requires following Contact Name.. we presume it
			,new x12el_text('Name', array(1,60),'S')
			,new x12el_code('Communication number qualifier', static::$commQualifiers)
			,new x12el_text('Communication number', array(1,256))
			,new x12el_code('Communication number qualifier', static::$commQualifiers,'S')
			,new x12el_text('Communication number', array(1,256),'S')
			,new x12el_code('Communication number qualifier', static::$commQualifiers,'S')
			,new x12el_text('Communication number', array(1,256),'S')
			// The effect of the usage of the elements above is that you can stop with these numbers at 1, 2, or 3 instances
			), $usage);
	}

	public function initialize( $name, array $comms = null )
	{
		$this->assign(array(2=>$name));
		if ($comms) {
			$base = 3;
			foreach ($comms as $qual=>$val) {
				$this->assign(array($base=>$qual, $base+1=>$val));
				$base += 2;
				if ($base >= 9)
					break;
			}
		}
	}
}

class x12seg_REF extends x12_semantic
{
	public static $purposeCodes = array(
		 'EI'=>'Employer Identification Number'
		,'SY'=>'Social Security Number'
		,'0B'=>'Unknown'
		,'1G'=>'Unknown'
		);

	public function __construct( $code, $label = 'Additional reference / identifier', $usage = 'R', $repeat = 0 )
	{
		parent::__construct($label, array(array('REF', $code, static::$purposeCodes)
			,new x12el_code('Reference Identification qualifier')
			,new x12el_text('Additional identifier', array(1,50))
			), $usage, $repeat);
	}

	public function initialize( $value )
	{
		$this->assign(array(2=>$value));
	}
}

class x12seg_DTP extends x12_semantic
{
	public static $purposeCodes = array(
		 '431'=>'Date of Onset of current illness or symptom'
		,'454'=>'Date of Initial treatment'
		,'304'=>'Date last seen'
		,'453'=>'Date of acute manifestation'
		,'439'=>'Date of accident'
		,'472'=>'Date of service'
		,'484'=>'Date of last menstrual period'
		,'455'=>'Date of last X-ray'
		,'471'=>'Date of hearing and vision prescription'
		,'472'=>'Service date'
		,'314'=>'Date of disability 314'
		,'360'=>'Date of disability 360'
		,'361'=>'Date of disability 361'
		,'297'=>'Date last worked'
		,'435'=>'Date admitted'
		,'096'=>'Date discharged'
		,'090'=>'Date care assumed'
		,'091'=>'Date care relinquished'
		,'444'=>'Property and casualty date of first contact'
		,'050'=>'Date repricer received'
		);

	public static $formatCodes = array(
		 'D8'=>'CCYYMMDD'
		,'RD8'=>'CCYYMMDD-CCYYMMDD'
		);

	public function __construct( $code, $label= 'Date or time', $usage = 'R', $repeat = 0 )
	{
		parent::__construct($label, array(array('DTP', $code, static::$purposeCodes)
			,new x12el_code('Date or time purpose qualifier')
			,new x12el_code('Date or time period format qualifier', static::$formatCodes)
			,new x12el_date('Date or time', 8) // this is a fake... in reality its a text(1,35) to allow for ranges and perhaps other possibilities. But until that's needed we get formatting for free with this approach.
			), $usage, $repeat);
	}

	public function render( )
	{
		if ($this->element[2]->value[0] == 'D')
			$this->element[3]->format = $this->element[2]->value[1];
		return parent::render();
	}
}

class x12seg_QTY extends x12_semantic
{
	static $qualifiers = array('PT'=>'Ambulance patient count', 'FL'=>'Obstetric anesthesia additional units');

	public function __construct( $code, $label = 'Service line quantity', $usage = 'R', $repeat = 0 )
	{
		parent::__construct($label, array(array('QTY', $code, static::$qualifiers)
			,new x12el_code('Quantity qualifier')
			,new x12el_numeric('Count', array(1,15))
			// remaining elements unused
			), $usage, $repeat);
	}
}

class x12seg_PAT extends x12_segment
{
	public function __construct( $usage = 'R', $repeat = 0 )
	{
		parent::__construct('Patient information', array('PAT'
			,new x12el_code('Individual relationship code', x12el_code::$relationshipCodes)
			,new x12el_notused('Patient location code')
			,new x12el_notused('Employment status code')
			,new x12el_notused('Student status code')
			,new x12el_code('Date time period format qualifier', x12seg_DTP::$formatCodes, 'S')
			,new x12el_text('Patient death date', array(1,35), 'S')
			,new x12el_code('Unit or basis for measurement code', array('01'=>'01'), 'S')
			,new x12el_numeric('Patient weight', 10, 'S')
			,new x12el_code('Pregnancy indicator', x12el_code::$yesNo, 'S')
			), $usage, $repeat);
	}
}

class x12seg_PWK extends x12_segment
{
	public static $paperworkCodes = array(
		'03'=>'03',  '04'=>'04',  '05'=>'05',  '06'=>'06',  '07'=>'07',  '08'=>'08',  '09'=>'09',  '10'=>'10',
		'11'=>'11',  '13'=>'13',  '15'=>'15',  '21'=>'21',  'A3'=>'A3',  'A4'=>'A4',  'AM'=>'AM',  'AS'=>'AS',
		'B2'=>'B2',  'B3'=>'B3',  'B4'=>'B4',  'BR'=>'BR',  'BS'=>'BS',  'BT'=>'BT',  'CB'=>'CB',  'CK'=>'CK',
		'CT'=>'CT',  'D2'=>'D2',  'DA'=>'DA',  'DB'=>'DB',  'DG'=>'DG',  'DJ'=>'DJ',  'DS'=>'DS',  'EB'=>'EB',
		'HC'=>'HC',  'HR'=>'HR',  'I5'=>'I5',  'IR'=>'IR',  'LA'=>'LA',  'M1'=>'M1',  'MT'=>'MT',  'NN'=>'NN',
		'OB'=>'OB',  'OC'=>'OC',  'OD'=>'OD',  'OE'=>'OE',  'OX'=>'OX',  'OZ'=>'OZ',  'P4'=>'P4',  'P5'=>'P5',
		'PE'=>'PE',  'PN'=>'PN',  'PO'=>'PO',  'PQ'=>'PQ',  'PY'=>'PY',  'PZ'=>'PZ',  'RB'=>'RB',  'RR'=>'RR',
		'RT'=>'RT',  'RX'=>'RX',  'SG'=>'SG',  'V5'=>'V5',  'XP'=>'XP'
		);
	public static $transmissionCodes = array(
		 'AB'=>'Previously submitted to payer'
		,'AD'=>'Certification included in this claim'
		,'AF'=>'Narrative Segment included in this claim'
		,'AG'=>'No documentation is required'
		,'NS'=>'Not specified (available on request)'
		);

	public function __construct( $label = "Paperwork attachment", $usage = 'R', $repeat = 0 )
	{
		parent::__construct($label, array('PWK'
			,new x12el_code('Report type code', static::$paperworkCodes)
			,new x12el_code('Report transmission code', static::$transmissionCodes)
			// remainder of elements are not used
			), $usage, $repeat);
	}
}

class x12seg_AMT extends x12_semantic
{
	public static $purposeCodes = array(
		 'MA'=>'Credit/debit card maximum' // sets the limit on how much can be credited to the card provided
		,'F5'=>'Amount paid by the patient' // to be used at the claim or service line level, but not both
		,'NE'=>'Total purchased service amount' // "Net billed" amount for the entire claim
		);

	public function __construct( $code, $label = "Monetary amount", $usage = 'R', $repeat = 0 )
	{
		parent::__construct($label, array(array('AMT', $code, static::$purposeCodes)
			,new x12el_code('Amount code')
			,new x12el_numeric('Monetary amount', array(1,18))
			,new x12el_notused('Credit/debit flag code')
			), $usage, $repeat);
	}
}

class x12seg_DMG extends x12_segment
{
	public function __construct( $usage = 'R', $repeat = 0 )
	{
		parent::__construct("Demographic info", array('DMG'
			,new x12el_code('Demo qualifier', x12seg_DTP::$formatCodes)
			,new x12el_text('Date', array(1,35))
			,new x12el_code('Gender', array('F'=>'Female','M'=>'Male','U'=>'Unknown'))
			// remainder unused
			), $usage, $repeat);
	}
}

class x12seg_CRC extends x12_semantic
{
	public static $purposeCodes = array(
		 '07'=>'Ambulance certification'
		,'70'=>'Hospice employee indicator'
		,'09'=>'Condition indicator durable medical equipment'
		,'ZZ'=>'EPSDT referral'
		,'75'=>'Homebound indicator'
		,'E1'=>'Patient condition: Vision'
		,'E2'=>'Patient condition: Vision'
		,'E3'=>'Patient condition: Vision'
		);
	public static $conditionCodes = array(
		'01'=>'01', '04'=>'04', '05'=>'05', '06'=>'06', '07'=>'07', '08'=>'08', '09'=>'09', '12'=>'12', '65'=>'65', '38'=>'38', 'ZV'=>'ZV'
		);

	public function __construct( $code, $label, $usage = 'R', $repeat = 0 )
	{
		parent::__construct($label, array(array('CRC', $code, static::$purposeCodes)
			,new x12el_code('Code category')
			,new x12el_code('Certification condition indicator', x12el_code::$yesNo)
			,new x12el_code('Condition code', static::$conditionCodes, 'R', 5)
			), $usage, $repeat);
	}
}

class x12seg_HI extends x12_segment
{
	public static $qualifiers = array(
		 'BG'=>'Code list qualifier BG'
		,'ABK'=>'Code list qualifier ABK'
		,'BK'=>'Code list qualifier BK'
		,'ABF'=>'Code list qualifier ABF'
		,'BF'=>'Code list qualifier BF'
		,'BP'=>'Code list qualifier BP'
		,'BO'=>'Code list qualifier BO'
		);

	public function __construct( $usage = 'R', $repeat = 0 )
	{
		parent::__construct("Health Care Diagnosis Code", array('HI'
			,new x12el_comp('Health care code information', array(
				 new x12el_code('Code list qualifier', static::$qualifiers)
				,new x12el_text('Condition code', array(1,35))
				// remainder of elements within this component are not used and are omitted here and in the other situational elements below
				)) // HI01
			,new x12el_comp('Health care code information', array( new x12el_code('Code list qualifier', static::$qualifiers) ,new x12el_text('Condition code', array(1,35)) ), 'S')
			,new x12el_comp('Health care code information', array( new x12el_code('Code list qualifier', static::$qualifiers) ,new x12el_text('Condition code', array(1,35)) ), 'S')
			,new x12el_comp('Health care code information', array( new x12el_code('Code list qualifier', static::$qualifiers) ,new x12el_text('Condition code', array(1,35)) ), 'S')
			,new x12el_comp('Health care code information', array( new x12el_code('Code list qualifier', static::$qualifiers) ,new x12el_text('Condition code', array(1,35)) ), 'S')
			,new x12el_comp('Health care code information', array( new x12el_code('Code list qualifier', static::$qualifiers) ,new x12el_text('Condition code', array(1,35)) ), 'S')
			,new x12el_comp('Health care code information', array( new x12el_code('Code list qualifier', static::$qualifiers) ,new x12el_text('Condition code', array(1,35)) ), 'S')
			,new x12el_comp('Health care code information', array( new x12el_code('Code list qualifier', static::$qualifiers) ,new x12el_text('Condition code', array(1,35)) ), 'S')
			,new x12el_comp('Health care code information', array( new x12el_code('Code list qualifier', static::$qualifiers) ,new x12el_text('Condition code', array(1,35)) ), 'S')
			,new x12el_comp('Health care code information', array( new x12el_code('Code list qualifier', static::$qualifiers) ,new x12el_text('Condition code', array(1,35)) ), 'S')
			,new x12el_comp('Health care code information', array( new x12el_code('Code list qualifier', static::$qualifiers) ,new x12el_text('Condition code', array(1,35)) ), 'S')
			,new x12el_comp('Health care code information', array( new x12el_code('Code list qualifier', static::$qualifiers) ,new x12el_text('Condition code', array(1,35)) ), 'S')
			), $usage, $repeat);
	}
}

class x12seg_CLM extends x12_segment
{
	public static $relatedCausesCodes = array(
		 'AA'=>"Auto accident"
		,'AB'=>"Abuse"
		,'AP'=>"Another party responsible"
		,'EM'=>"Employment"
		,'OA'=>"Other accident"
		);

	public static $claimFrequencyCodes = array(
		 '1'=>"ORIGINAL (Admit thru Discharge Claim)"
		,'6'=>"CORRECTED (Adjustment of Prior Claim)"
		,'7'=>"REPLACEMENT (Replacement of Prior Claim)"
		,'8'=>"VOID (Void/Cancel of Prior Claim)"
		);

	// See http://www.cms.gov/Regulations-and-Guidance/Guidance/Manuals/downloads/clm104c26.pdf for what appears to be a detailed and complete list of facility type codes.
	public static $facilityTypeCodes = array(
		 '11'=>"Office"
		,'12'=>"Home"
		,'21'=>"Inpatient Hospital"
		,'22'=>"Outpatient Hospital"
		,'23'=>"Emergency Room - Hospital"
		,'24'=>"Ambulatory Surgical Center"
		,'25'=>"Birthing Center"
		,'26'=>"Military Treatment Facility"
		,'31'=>"Skilled Nursing Facility"
		,'32'=>"Nursing Facility"
		,'33'=>"Custodial Care Facility"
		,'34'=>"Hospice"
		,'41'=>"Ambulance - Land"
		,'42'=>"Ambulance - Air or Water"
		,'49'=>"Independent Clinic" // This code was specifically requested for use by InnerActive for MVP claims in an email from Kate Finke dated 20 Dec 2012
		,'51'=>"Inpatient Psychiatric Facility"
		,'52'=>"Psychiatric Facility Partial Hospitalization"
		,'53'=>"Community Mental Health Center"
		,'54'=>"Intermediate Care Facility/Mentally Retarded"
		,'55'=>"Residential Substance Abuse Treatment Facility"
		,'56'=>"Psychiatric Residential Treatment Center"
		,'50'=>"Federally Qualified Health Center"
		,'60'=>"Mass Immunization Center"
		,'61'=>"Comprehensive Inpatient Rehabilitation Facility"
		,'62'=>"Comprehensive Outpatient Rehabilitation Facility"
		,'65'=>"End Stage Renal Disease Treatment Facility"
		,'71'=>"State or Local Public Health Clinic"
		,'72'=>"Rural Health Clinic"
		,'81'=>"Independent Laboratory"
		,'99'=>"Other Unlisted Facility"
		);

	public function __construct( $usage = 'R', $repeat = 100 )
	{
		parent::__construct('Claim information', array('CLM'
			,new x12el_text('Patient Account Number', array(1,38))
			,new x12el_numeric('Total Claim Charge Amount', 18)
			,new x12el_notused('Claim filing indicator code')
			,new x12el_notused('Non-institutional claim type code')
			,new x12el_comp('Health care service location information', array(
				 new x12el_code('Facility Type Code', self::$facilityTypeCodes)
				,new x12el_code('Facility Code Qualifier', array('B'=>'only'))
				,new x12el_code('Claim frequency type', self::$claimFrequencyCodes)
				))
			,new x12el_code('Reponse code', x12el_code::$yesNo) // MVP: "Provider signature on file"
			,new x12el_code('Provider accept Medicare assignment code', array('A'=>'A','B'=>'B','C'=>'C'))
			,new x12el_code('Response code', array('W'=>'Assign of benefits'))
			,new x12el_code('Release of information', array(
					 'I'=>'Informed Consent to Release Medical Information for Conditions or Diagnoses Regulated by Federal Statutes'
					,'Y'=>'Yes, Provider has a Signed Statement Permitting Release of Medical Billing Data Related to a Claim'
					))
			,new x12el_code('Patient signature source code', array('P'=>'On file with Payer'), 'S')
			,new x12el_comp('Related causes information', array( // primarily to get information about accidents
				 new x12el_code('Related causes code', static::$relatedCausesCodes)
				,new x12el_code('Related causes code', static::$relatedCausesCodes, 'S') // optional, if two factors apply
				,new x12el_notused('Related causes code')
				,new x12el_code('Auto accident state or province code', x12el_code::$statesAndProvinces, 'S') // not used by MVP
				,new x12el_code('Auto accident country code', x12seg_N4::$countryCodes, 'S') // not used by MVP
				))
			,new x12el_code('Special program indicator', array('02'=>'02','03'=>'03','05'=>'05','09'=>'09'), 'S')
			,new x12el_notused('(trailing claim elements)')
			,new x12el_notused('(trailing claim elements)')
			,new x12el_notused('(trailing claim elements)')
			,new x12el_notused('(trailing claim elements)')
			,new x12el_notused('(trailing claim elements)')
			,new x12el_notused('(trailing claim elements)')
			,new x12el_notused('(trailing claim elements)')
			,new x12el_code('Delay reason code', array(1=>1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, '15'=>'15'), 'S')
			), $usage, $repeat);
	}

}

class x12seg_SBR extends x12_segment
{
	static $filingCodes = array(
		 '09'=>'Self-pay'
		,'11'=>'Other Non-Federal Programs'
		,'12'=>'Preferred Provider Organization (PPO)'
		,'13'=>'Point of Service (POS)'
		,'14'=>'Exclusive Provider Organization (EPO)'
		,'15'=>'Indemnity Insurance'
		,'16'=>'Health Maintenance Organization (HMO) Medicare Risk'
		,'17'=>'Dental Maintenance Organization'
		,'AM'=>'Automobile Medical'
		,'BL'=>'Blue Cross/Blue Shield'
		,'CH'=>'CHAMPUS'
		,'CI'=>'Commercial Insurance Co.'
		,'DS'=>'Disability'
		,'FI'=>'Federal Employees Program'
		,'HM'=>'Health Maintenance Organization'
		,'LM'=>'Liability Medical'
		,'MA'=>'Medicare Part A'
		,'MB'=>'Medicare Part B'
		,'MC'=>'Medicaid'
		,'OF'=>'Other Federal Program (Use "OF" when submitting Medicare Part D Claims.)'
		,'TV'=>'Title V'
		,'VA'=>'Veterans Affairs Plan'
		,'WC'=>'Workers Compensation Health Claim'
		,'ZZ'=>'Type of Insurance is not known'
		);

	public function __construct( $usage = 'R', $repeat = 1 )
	{
		parent::__construct('Subscriber information', array('SBR'
			,new x12el_code('Payer responsibility code', array('P'=>'Primary payer' ,'S'=>'Secondary payer', 'T'=>'Tertiary payer'))
			,new x12el_code('Individual relationship code', x12el_code::$relationshipCodes, 'S') // for MVP, subscriber is patient is self
			,new x12el_text('Insured group or policy number', array(1,50), 'S')
			,new x12el_text('Insured group name', array(1,60), 'S')
			,new x12el_code('Insurance type code', array('12'=>1, 1, 1, 1, 1, '41'=>1, 1, 1, '47'=>1), 'S')
			,new x12el_notused('Coordination of benefits code')
			,new x12el_notused('Yes/No condition or response code')
			,new x12el_notused('Employment status code')
			,new x12el_code('Claim filing indicator code', static::$filingCodes)
			), $usage, $repeat);
	}

}


?>
