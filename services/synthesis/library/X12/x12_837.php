<?php
require_once 'x12seg.php';

class x12loop_claim extends x12_loop
{
	public static $noteRefCodes = array(
		 'ADD'=>'Note ref code ADD'
		,'CER'=>'Note ref code CER'
		,'DCP'=>'Note ref code DCP'
		,'DGN'=>'Note ref code DGN'
		,'TPO'=>'Note ref code TPO'
		);

	public function __construct( $usage = 'R', $repeat = 100 )
	{
		parent::__construct('2300:Claim information', array(
			 new x12seg_CLM()
			// Here the spec spells out each DTP segment separately but for expedience we specify all 17 possible DTP segments as a single situational repeating segment
			,new x12seg_DTP(array(431,454,304,453,439,484,455,471,314,360,361,297,296,435,'096','090','091',444,'050'), 'Claim-associated dates', 'S', 17)
				// of these, the only date required by MVP appears to be 304 "Date last seen" which we presume can be set to match the date of service
			,new x12seg_PWK('Claim paperwork attachment', 'S', 10)
			,new x12_segment('Contract information', array('CN1'), 'S')
			,new x12seg_AMT('F5', 'Amount paid by patient', 'S')
			,new x12seg_REF(array('4N','F5','EW','9F','G1','F8','X4','9A','9C','LX','D9','EA','P4','1J'), 'Claim reference info', 'S', 14) // the spec spells out all 14 REF segments separately, but here we just encode as a single situational repeating set
			,new x12_segment('File information', array('K3'), 'S')
			,new x12_segment('Claim note', array('NTE'
				,new x12el_code('Note reference code', static::$noteRefCodes)
				,new x12el_text('Claim note text', array(1,80))
				), 'S')
			,new x12_segment('Ambulance transport information', array('CR1'), 'S')
			,new x12_segment('Spinal manipulation service information', array('CR2'), 'S')
			,new x12seg_CRC(array('07','E1','E2','E3','75','ZZ'), 'Claim conditions', 'S', 6) // the spec spells out 4 CRC segments, one of which can appear 3 times
			,new x12seg_HI('R', 4) // the spec spells out 3 HI segments, one of which can appear twice and only the first is required. We implement here as a repeatable up to 4
			,new x12_segment('Claim pricing/repricing information', array('HCP'), 'S')
			,new x12_equiv('2310', array(
				 new x12_loop('2310A:Referring provider', array(
					 new x12seg_NM1(array('DN', 'P3'), "Referring provider name")
					,new x12seg_REF(array('0B','1G','G2'), 'Referring provider secondary identification', 'S', 3)
					), 'S', 2)
				,new x12_loop('2310B:Rendering provider', array(
					 new x12seg_NM1('82', "Rendering provider name")
					,new x12_segment('Rendering provider specialty information', array('PRV'), 'S')
					,new x12seg_REF(array('0B','1G','G2','LU'), 'Rendering provider secondary identification', 'S', 4)
					), 'S', 1)
				,new x12_loop('2310C:Service facility location', array(
					 new x12seg_NM1('77', "Service facility location name")
					,new x12seg_N3()
					,new x12seg_N4()
					,new x12seg_REF(array('0B','LU','G2'), 'Service facility location secondary identification', 'S', 3)
					,new x12seg_PER('IC', 'Service facility contact information', 'S') // spec says this is required, but my test data didn't have this segment, so not sure
					), 'S', 1)
				,new x12_loop('2310D:Supervising provider', array(
					 new x12seg_NM1('DQ', "Supervising provider name")
					,new x12seg_REF(array('0B','1G','LU','G2'), 'Supervising provider secondary identification', 'S', 4)
					), 'S', 1)
				,new x12_loop('2310E:Ambulance pickup location', array(
					 new x12seg_NM1('PW', "Ambulance pickup location name")
					,new x12seg_N3()
					,new x12seg_N4()
					), 'S', 1)
				,new x12_loop('2310F:Ambulance drop-off location', array(
					 new x12seg_NM1('45', "Ambulance drop-off location name")
					,new x12seg_N3()
					,new x12seg_N4()
					), 'S', 1)
				))
			// The 2320 loop (Other subscriber information) is situational and is unlikely to apply to our agreement with MVP so we are leaving it out for now
			// 5010's 2330A and D-F are not in MVP's guide, and 2330B-C are in MVP's guide but S and not obviously relevant, so we are skipping them
			,new x12_loop('2400:Service line', array(
				 new x12_segment('Service line', array('LX', new x12el_fixdec('Assigned number', array(1,6,0))))
				,new x12_segment('Professional service', array('SV1'
					,new x12el_comp('Composite medical procedure identifier', array(
						 new x12el_code('HCPCS code', array('ER'=>'ER','HC'=>'HC','IV'=>'IV','WK'=>'WK')) // It seems like HC is correct
						,new x12el_text('Procedure code', array(1,48)) // This should match our agreement for a single type of service.
						,new x12el_text('Procedure modifier', 2, 'S')
						,new x12el_text('Procedure modifier', 2, 'S')
						,new x12el_text('Procedure modifier', 2, 'S')
						,new x12el_text('Procedure modifier', 2, 'S')
						,new x12el_text('Description', array(1,80), 'S')
						// last component is not used and omitted here
						))
					,new x12el_numeric('Line item charge amount', 18)
					,new x12el_code('Unit or basis for measurement', array('MJ'=>'MJ','UN'=>'UN'))
					,new x12el_numeric('Service unit count', 15)
					,new x12el_text('Place of service code', array(1,2), 'S') // MVP does not provide a hint
					,new x12el_notused('Service type code')
					,new x12el_comp('Diagnosis code pointer', array(
						 new x12el_fixdec('Diagnosis code pointer', array(1,2))
						// Remainder are S and we don't do diagnosis, so skipping
						))
					// Remainder of elements in SV1 are S or N and presumed irrelevant to our service model
					)) // closure of SV1 segment
				,new x12_segment('Durable medical equipment service', array('SV5'), 'S')
				,new x12seg_PWK('Service line paperwork attachment', 'S', 11)
				,new x12_segment('Ambulance transport information', array('CR1'), 'S')
				,new x12_segment('Durable medical equipment certification', array('CR3'), 'S')
				,new x12seg_CRC(array('07','70','09'), 'Additional conditions',  'S', 5)
				,new x12seg_DTP(array('472','471','607','463','461','304','738','739'), 'Service line dates', 'S', 8) // note that the '472'=>'Date of service' segment is REQUIRED, but no others are, and they are all EQUIV
				,new x12seg_QTY(array('PT','FL'), 'Service line quantities', 'S', 2)
				,new x12_segment('Test results', array('MEA'), 'S', 5)
				,new x12_segment('Contract information', array('CN1'), 'S')
				,new x12seg_REF(array('9B','9D','G1','2U','6R','EW','X4','F4','BT','9F'), 'Service line references', 'S', 17) // sum of all various REF repetitions
				,new x12seg_AMT(array('T','F4'), 'Sales tax amount', 'S', 2)
				,new x12_segment('File information', array('K3'), 'S', 2)
				,new x12_segment('Service line notes', array('NTE'
					,new x12el_code('Note reference code', array('ADD'=>'Line note ADD','DCP'=>'Line note DCP','TPO'=>'Third party organization note'))
					,new x12el_text('Line note text', array(1,80))
					), 'S', 2)
				,new x12_segment('Purchased service information', array('PS1'), 'S')
				,new x12_segment('Line pricing/repricing information', array('HCP'), 'S')
				,new x12_loop('2410', array(
					 new x12_segment('Drug identification', array('LIN'))
					,new x12_segment('Drug pricing', array('CTP'))
					,new x12seg_REF(array('VY','XZ'), 'Prescription or compound drug association number', 'S')
					), 'S', -1)
				// loops 2420A-H, 2430, 2440 are unimplemented thus far
				), 'R', 50) // closure of loop 2400
			), $usage, $repeat);
	}// closure of loop 2300 (Claim information)
}

/*
class x12_837_MVP extends x12_837
{
	public function set_provider( array $mappings ) { }
	public function set_payer( array $mappings ) { }
	public function set_services( array $mappings ) { }
	public function set_patient( array $mappings ) { }
	public function set_claim( array $mappings ) { }

}
*/

class x12_837 extends x12_loop
{
	protected static $idcode = '837'; // transaction set payloads need to have this information and share it on demand

	public function __construct( )
	{
		parent::__construct('Health care claim submittal', array( // this payload itself is non-repeating, but its envelope (transactionset) is a bounded loop
			 new x12seg_BHT('0019', '837 Health care claim hierarchy')
			,new x12_loop('1000A:Submitter', array(
				 new x12seg_NM1('41', 'Submitter name') // Submitter
				,new x12seg_PER('IC', 'Submitter EDI contact info', "R", 2)
				))
			,new x12_loop('1000B:Receiver name', array(
				 new x12seg_NM1('40')
				))
			,new x12_loop('2000A:BILLING PROVIDER Hierarchical Level', array(
				 new x12seg_HL('20', true)
				,new x12_semantic('Billing provider specialty', array( array('PRV', 'BI')
					,new x12el_code('Provider code', array('BI'=>'only'))
					,new x12el_code('Reference identification qualifier', array('PXC'=>'only'))
					,new x12el_text('Provider taxonomy code', array(1,50))
					// Dropped unused elements
					), 'S')
				,new x12_segment('Currency information', array('CUR'), 'S') // situational, not needed for domestic transactions
				,new x12_equiv('2010A', array(
					 new x12_loop('2010AA:Billing Provider', array(
						 new x12seg_NM1('85', 'Billing provider name')
						,new x12seg_N3()
						,new x12seg_N4()
						,new x12seg_REF(array('EI','SY'), 'Billing provider identification') // Tax ID
						,new x12seg_REF(array('0B','1G'), 'Billing provider UPIN/License information', 'S', 2) // NOT USED by MVP
						,new x12seg_PER('IC', 'Billing provider contact info','S',2)
						))
					,new x12_loop('2010AB:Pay-to Address', array(
						 new x12seg_NM1('87', 'Pay-to name')
						,new x12seg_N3()
						,new x12seg_N4()
						), 'S')
					,new x12_loop('2010AC:Pay-to Plan Name', array( // not in MVP's guide
						 new x12seg_NM1('PE', 'Pay-to plan name')
						,new x12seg_N3()
						,new x12seg_N4()
						,new x12seg_REF(array('2U','FY','NF'), 'Pay-to plan secondary identification', 'S')
						,new x12seg_REF('EI', 'Pay-to plan tax identification')
						), 'S')
					))
				,new x12_loop('2000B:SUBSCRIBER Hierarchical Level', array(
					 new x12seg_HL('22') // for MVP, all claims are based on subscriber, as each patient has his own subscriber creds.
					,new x12seg_SBR('S')
					,new x12seg_PAT('S') // MVP skips this segment
					,new x12_equiv('2010B', array(
						 new x12_loop('2010BA:Subscriber information', array(
							 new x12seg_NM1('IL')
							,new x12seg_N3('S')
							,new x12seg_N4('S')
							,new x12_segment('Subscriber demographic information', array('DMG'
								,new x12el_code('Date format qualifier', x12seg_DTP::$formatCodes)
								,new x12el_date('Subscriber birth date', 8)
								,new x12el_code('Subscriber gender', array('F'=>'Female','M'=>'Male','U'=>'Unknown'))
								// remainder of elements are unused
								), 'S')
							,new x12seg_REF('SY', 'Subscriber secondary identification', 'S')
							,new x12seg_REF('Y4', 'Property and casualty claim number', 'S')
							,new x12seg_PER('IC', 'Property and casualty subscriber contact info', 'S')
							)) // close subscriber info
						,new x12_loop('2010BB:Payer name and address', array(
							 new x12seg_NM1('PR', 'Payer name')
							,new x12seg_N3('S')
							,new x12seg_N4('S')
							,new x12seg_REF(array('2U','EI','FY','NF'), 'Payer secondary identification', 'S', 3)
							,new x12seg_REF(array('G2','LU'), 'Billing provider secondary identification', 'S', 2)
							))
						))
					,new x12_loop('2000C:PATIENT Hierarchical Level', array( // MVP skips the Patient hierarchical level because every patient is treated like a subscriber in their system
						 new x12seg_HL('23', true)
						,new x12seg_PAT()
						,new x12seg_NM1('QC')
						,new x12seg_N3()
						,new x12seg_N4()
						,new x12seg_DMG()
						,new x12seg_REF('Y4', 'Property and Casualty claim number', 'S')
						,new x12seg_REF(array('1W','SY'), 'Property and Casualty patient identifier', 'S')
						,new x12seg_PER('IC', 'Property and Casualty patient contact information', 'S')
						,new x12loop_claim()
						), 'S', -1) // Closure of PATIENT HL loop 2000C (unused for MVP)
					,new x12loop_claim('S')
					), 'R', -1) // Closure of SUBSCRIBER HL loop 2000B
				), "R", -1 ) // Closure of BILLING PROVIDER loop 2000A

			));
	}

	public function __set( $name, $value )
	{
		if ('SubmitterName' == $name)
			$this->members[2]->members[1]->assign($value);
		else if ('SubmitterContactInfo' == $name)
			$this->members[2]->members[2]->assign($value);
		else if ('ReceiverName' == $name)
			$this->members[3]->members[1]->assign($value);
	}

	public function __get( $name )
	{
		if ('idcode'==$name)	 // transaction set payloads need to have this information and share it on demand
			return static::$idcode;
	}
}

?>
