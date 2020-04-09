<?php
/**
* An entity with a personal identity.
*
* All original code.
* @package Synthesis/Contacts
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2007-2014 Lifetoward LLC
* @license proprietary
*/
class _e_person extends Element
{
	public static $table = "com_person", $singular = "Person", $plural = "People", $descriptive = "Personal and contact information",
		$schema = [ // This is the initial schema expected by the class. Much of it can be modified in the database where it lives actively, but such changes must be carefully considered.
			'class'=>'Contacts\Person',
			'sorting'=>[ 'nickname'=>1, 'surname'=>1, 'age'=>1, ],
			'identifying'=>[ 'nickname', 'surname', 'suffix' ],
			'fieldset'=>[ // in preferred sequence
				[ 'name'=>'nickname', 'label'=>"First name", 'class'=>'t_string', 'required'=>true,
					'help'=>"Provide the familiar name by which this person prefers to be addressed." ],
				[ 'name'=>'surname', 'label'=>"Last name", 'class'=>'t_string', 'required'=>true, 'help'=>'Provide the surname or family name.' ],
				[ 'name'=>'suffix', 'label'=>"Name suffix",'class'=>'t_string', 'help'=>"This is where to provide modifiers like \"Jr.\", \"Sr.\", \"III\", etc.; usually left blank." ],
				[ 'name'=>'givennames', 'label'=>"Given  names",'class'=>'t_string',
					'help'=>"All formal names preceding the surname belong here. Only necessary when a full legal name is required. Typically maiden names are appropriate when applicable." ],
				[ 'name'=>'fullname', 'label'=>"Full legal name", 'class'=>'t_string', 'help'=>"Complete legal name", 'assembled'=>'methodName',
					'derived'=>[ 'mysql'=>"TRIM(CONCAT(IF(LENGTH(`{}`.givennames)<1,`{}`.nickname,`{}`.givennames),' ',`{}`.surname,IF(`{}`.suffix,CONCAT(', ',`{}`.suffix),'')))", ], ],
				[ 'name'=>'gender', 'label'=>"Gender", 'class'=>'t_select', 'options'=>[ 'male'=>"Male", 'female'=>"Female", ],
					'help'=>"It's helpful to record the gender of a contact so anyone can be sure how to address a person using personal pronouns.", ],
				[ 'name'=>'age', 'label'=>"Age", 'class'=>'t_integer', 'derived'=>[ 'mysql'=>"IF(`{}`.birthday IS NOT NULL,FLOOR(YEAR(NOW())-YEAR(`{}`.birthday)),NULL)" ], ],
				[ 'name'=>'birthday', 'label'=>"Birth date", 'class'=>'t_date', 'input'=>'selectors', 'width'=>6,
					'help'=>"If age is not important but birthday anniversaries are of interest, leaving the year arbitrary or approximate is fine.", ],
				[ 'name'=>'name', 'label'=>"System unique name", 'readonly'=>true ], // Entity override
				[ 'name'=>'msgsvc', 'class'=>'t_msgsvc', 'label'=>"Text message #", 'help'=>"Please fill this in even if it matches one of the other phone numbers you're providing." ],
				[ 'name'=>'carrier', 'class'=>'t_select', 'label'=>'Mobile communications service provider', 'help'=>"This setting may be required for the system to send some kinds of messages.",
						'options'=>['verizon'=>"Verizon", 'att'=>"AT&T", 'tmobile'=>"T-Mobile", 'sprint'=>"Sprint", 'other'=>"(Other)", ], ], // only providers which we know how to use need to be in this list.
				[ 'name'=>'comments', 'label'=>"Personal  bio", 'class'=>'t_richtext', 'help'=>"Open for use as an informal public profile or bio within our system." ],
			],
			'labels'=>[ 'singular'=>"Person", 'plural'=>"People", 'descriptive'=>"Personal and contact information", ],
			'include'=>[ 'Entity'=>[ 'exclude'=>[] ] ], // the fields of included elements
			'operations'=>[ // operations are important, providing simple handles to describe actions that can be done against this and how to trigger or call them
				'display'=>[], 'update'=>[], 'delete'=>[], // standard use of instance type operations
				'create'=>[], 'list'=>[], // class type operations per standard convention
				'addresses'=>[ 'action'=>'a_ProduceAddressesPDF', 'icon'=>'envelope', 'label'=>"Print mailing addresses" ],
				'vcard'=>[ 'target'=>'dataaccess.php', 'icon'=>'download', 'label'=>"Download vCard", 'args'=>[ 'method'=>'directVCard' ] ],
			],
			'hints'=>[ // hints are for general purpose actions which can use formatting hints to make things look reasonable for this particular instance
				'a_Browse'=>[ 'include'=>[ 'nickname','surname','phone','email','enthusiasm' ],
						'triggers'=>[ 'banner'=>'create', 'row'=>[ 'update','display','vcard' ], 'multi'=>[ 'delete','addresses' ], ], ],
				'a_Display'=>[ 'role'=>'Staff', 'tiles'=>[ // all hints are tile rendering method specific... method selection is all that's reliably common within each of these.
					[ 'title'=>"Contact information", 'method'=>'a_Display::fields', 'include'=>[ 'phone','email','msgsvc','carrier','mailaddr','comnotes' ], 'operations'=>[ 'head'=>'update' ] ],
					[ 'title'=>"Reference information", 'method'=>'a_Display::fields', 'operations'=>['head'=>'update'],
						'exclude'=>[ 'category','suffix','givennames','phone','email','msgsvc','carrier','mailaddr','comnotes','fullname','nickname','surname','name' ], ],
					[ 'class'=>'r_Org', 'headfield'=>'@title', 'method'=>'a_Display::relations', 'include'=>[ 'phone','email' ], 'operations'=>[ 'head'=>[ 'view','select' ], ], ],
					[ 'class'=>'r_Workplace', 'method'=>'a_Display::relations', 'include'=>[ 'phone','unit' ], 'operations'=>[ 'head'=>[ 'view','select' ], ], ],
				],	],
				'DataDirect'=>[ 'directVCard'=>'Staff' ],
			],
		],
		$fielddefs = array(
			 'nickname'=>array('name'=>'nickname', 'label'=>"First  name",'class'=>'t_string','sort'=>'ASC', 'required'=>true, 'identifying'=>true,
				'help'=>"Provide the familiar name by which this person prefers to be addressed.")
			,'surname'=>array('name'=>'surname', 'label'=>"Last  name", 'class'=>'t_string', 'sort'=>'ASC', 'required'=>true, 'identifying'=>true,
				'help'=>'Provide the surname or family name.')
			,'suffix'=>array('name'=>'suffix', 'label'=>"Name  suffix",'class'=>'t_string',
				'help'=>"This is where to provide modifiers like \"Jr.\", \"Sr.\", \"III\", etc.; usually left blank.")
			,'givennames'=>array('name'=>'givennames', 'label'=>"Given  names",'class'=>'t_string',
				'help'=>"All formal names preceding the surname belong here. Only necessary when a full legal name is required. Typically maiden names are appropriate when applicable.")
			,'fullname'=>array('name'=>'fullname', 'label'=>"Full legal name", 'class'=>'t_string', 'identifying'=>true, 'help'=>"Complete legal name",
				'derived'=>"TRIM(CONCAT(IF(LENGTH(`{}`.givennames)<1,`{}`.nickname,`{}`.givennames),' ',`{}`.surname,IF(`{}`.suffix,CONCAT(', ',`{}`.suffix),'')))")
			,'gender'=>array('name'=>'gender', 'label'=>"Gender", 'class'=>'t_select', 'options'=>array('male'=>"Male",'female'=>"Female"),
				'help'=>"It's helpful to record the gender of a contact so anyone can be sure how to address a person using personal pronouns.")
			,'age'=>array('name'=>'age','label'=>"Age", 'class'=>'t_integer', 'derived'=>"IF(`{}`.birthday IS NOT NULL,FLOOR(YEAR(NOW())-YEAR(`{}`.birthday)),NULL)",'sort'=>true)
			,'birthday'=>array('name'=>'birthday', 'label'=>"Birth date", 'class'=>'t_date', 'input'=>'selectors', 'width'=>6,
				'help'=>"If age is not important but birthday anniversaries are of interest, leaving the year arbitrary or approximate is fine.")
			,'entity'=>array('name'=>'entity', 'label'=>'Entity', 'class'=>'e_entity', 'type'=>'include','override'=>array('name'=>array('label'=>"System unique name", 'readonly'=>true)))
			,'msgsvc'=>array('name'=>'msgsvc', 'class'=>'t_msgsvc', 'label'=>"Text message #", 'help'=>"Please fill this in even if it matches one of the other phone numbers you're providing.")
			,'carrier'=>array('name'=>'carrier', 'class'=>'t_select', 'label'=>'Mobile communications service provider', 'help'=>"This setting may be required for the system to send some kinds of messages.",
					'options'=>array('verizon'=>"Verizon", 'att'=>"AT&T", 'tmobile'=>"T-Mobile", 'sprint'=>"Sprint",
							'boost'=>"Boost mobile", 'metropcs'=>"MetroPCS", 'cricket'=>"Cricket", 'uscellular'=>"US Cellular", 'virgin'=>"Virgin mobile", 'ting'=>"Ting"))
			,'comments'=>array('name'=>'comments', 'label'=>"Personal  bio", 'class'=>'t_richtext', 'help'=>"Open for use as an informal public profile or bio within our system.")
		), $hints = array( // hints are provided for general purpose actions to be able to customize rendering of this particular element class
			 'a_Display'=>array('role'=>array('*owner','Staff'), 'tiles'=>array(
					 array('method'=>'a_Display::fields','title'=>"Contact information", 'include'=>array('phone','email','msgsvc','carrier','mailaddr','comnotes'),
						'operations'=>array('head'=>'update'))
					,array('method'=>'a_Display::fields','title'=>"Reference information",
                        'exclude'=>array('category','suffix','givennames','phone','email','msgsvc','carrier','mailaddr','comnotes','fullname','nickname','surname','name'),
						'operations'=>array('head'=>'update'))
					,array('method'=>'a_Display::relations','class'=>'r_Org', 'headfield'=>'title', 'include'=>array('phone','email'), 'operations'=>array('head'=>array('view','select')))
					,array('method'=>'a_Display::relations','class'=>'r_Workplace','include'=>array('phone','unit'),
						'operations'=>array('head'=>array('view','select')))
//					,array('method'=>'a_Display::relations','class'=>'r_Destination','include'=>array('unit','phone'),
//						'operations'=>array('head'=>array('view','select')))
//					,array('method'=>'a_Display::relations','class'=>'r_Home','include'=>array('phone'),
//						'operations'=>array('head'=>array('view','select')))
					,array('method'=>'a_Display::fields','title'=>"Names", 'include'=>array('fullname','name','nickname','givennames','surname','suffix'),
						'operations'=>array('head'=>'update')) )
				)
			,'a_Browse'=>array(
				 'include'=>array('nickname','surname','phone','email','enthusiasm')
				,'triggers'=>array('banner'=>'create', 'row'=>array('update','display','vcard'), 'multi'=>array('delete','addresses')) )
			,'DataDirect'=>[ 'directVCard'=>'Staff' ],
		), $operations = array('display'=>array(),'update'=>array(),'delete'=>array(),'create'=>array(),'list'=>array()
			,'addresses'=>array('action'=>'a_ProduceAddressesPDF', 'icon'=>'envelope', 'label'=>"Print mailing addresses")
			,'vcard'=>array('target'=>'dataaccess.php', 'icon'=>'download', 'label'=>"Download vCard", 'args'=>array('method'=>'directVCard'))
		), $directMethods = array(
			 'directVCard'=>'Staff'
		);

	public function formatted( )
	{
		return "$this->°nickname $this->°surname";
	}

	public function getVCard()
	{
		$sysuidbase = "synthesis-$_ENV[ACCOUNT]-$_ENV[PHASE]-person";
		$sysuidbase4 = "urn:x-synthesis:$sysuidbase";

		// Begin by establishing the format-required entries
        $result = array("BEGIN:VCARD"
			,"VERSION:4.0"
		// Next we add the properties which are required by our class
			,"KIND:individual"
			,"FN:$this->fullname"
			,"N:$this->surname;;$this->nickname;;$this->suffix"
//			,"NICKNAME:$this->nickname" // renders in quotes in Apple Contacts - too marmy for our usage
			);
		// Optional personal fields
		if (isset($this->gender))
			$result[] = "GENDER:". ($this->gender ? 'F' : 'M');
		if ($this->birthday)
			$result[] = "BDAY:". str_replace('-', '', $this->°birthday);
		// Contact information
		if ($this->mailaddr)
			$result[] = $this->mailaddr->getVCardProperty(++$ADR);
		// Telecom numbers
		if ($this->phone || $this->msgsvc) {
			if ($this->phone == $this->msgsvc)
				$result[] = t_telecom::getVCardProperty($this->phone, array('voice','text'), ++$TEL, 1);
			else {
				if ($this->phone)
					$result[] = t_telecom::getVCardProperty($this->phone, 'voice', ++$TEL, 10);
				if ($this->msgsvc)
					$result[] = t_telecom::getVCardProperty($this->msgsvc, 'text', ++$TEL, 10);
			}
		}
		// Primary email address
		if ($this->email)
			$result[] = "EMAIL;PID=". ++$EMAIL .".1;PREF=1;TYPE=pref:$this->°email";
		// Contact notes
		if ($this->comnotes)
			$result[] = t_richtext::getVCardProperty($this->comnotes, ++$NOTE);
		if ($this->comments)
			$result[] = t_richtext::getVCardProperty($this->comments, ++$NOTE);

		// Organizational relationships
		if ($this->company) {
			$result[] = 'ORG;PID='. ++$ORG .".1:$this->°company"; // Also TITLE and ROLE...
			if ($this->company->phone)
				$result[] = t_telecom::getVCardProperty($this->company->phone, array('voice','work'), ++$TEL);
			if ($this->company->email)
				$result[] = 'EMAIL;PID='. ++$EMAIL .'.1;TYPE=work:'. $this->company->°email;
			if ($this->company->website)
				$result[] = 'URL;PID='. ++$WEB .'.1;TYPE=work:'. $this->company->website;
		}
		// foreach (organization relation) do telephone and text numbers, email address, org info (ORG, TITLE, ROLE)

		// Envisioned but not as implemented here:
		// foreach (location relation) do telephone numbers, location addresses (ADR, TEL)
		// Calendar information as FBURL (free/busy query URL), CALADRURI (target of invitations), CALURI (location of calendar)
		// PGP Public key, X.509 cert, etc.

		// Control info
		$result[] = "PRODID:Synthesis by Lifetoward LLC (SyntheticWebApps.com)";
		$result[] = "REV:". date('Ymd\THis'); // for now we're just saying "as of"
		$result[] = "UID:$sysuidbase-$this->_id";
//		$result[] = "UID:$sysuidbase4-$this->_id"; // this is a vCard 4.0 way to do it, but since most out there are 3.0 the preceding seems preferred for now.
		$result[] = "CLIENTPIDMAP:1;$sysuidbase";
		$result[] = "END:VCARD\n";

		// Assemble and return
		return implode("\n", $result);
	}

	/**
	* This method renders a person object according to RFC6350 (http://tools.ietf.org/html/rfc6350#section-10.3.1)
	* @return string Returns a vCard object corresponding to this one instance of e_person.
	*/
    public function directVCard( )
    {
		header("MIME-Version: 1.0");
		header("Content-type: text/vcard; charset=utf8");
		header("Content-disposition: inline; filename=\"$this.vcf\"");
		print $this->getVCard();
		exit;
	}

	public function acceptFieldValue( $fn, $value )
	{
		parent::acceptFieldValue($fn, $value);
		if (in_array($fn, array('givennames','nickname','surname','suffix','birthday')))
			$this->name = ($this->givennames ? $this->givennames : $this->nickname) ." $this->surname". ($this->suffix ? ", $this->suffix" : null) . ($this->birthday ? " [$this->birthday]" : null);
	}

}
