<?php
/**
* This is the postal and geographic address type for the United States.
*
* All original code.
* @package Synthesis/Contacts
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2004-2014 Lifetoward LLC
* @license proprietary
*/
class f_postaddr_USA extends f_postaddr
{
	static $fielddefs = array(
		 'country'=>array('name'=>'country', 'class'=>'t_string', 'label'=>"Country", 'readonly'=>true, 'initial'=>'USA')
		,'scheme'=>array('name'=>'scheme', 'class'=>'t_select', 'initial'=>'standard', 'label'=>"Addressing scheme", 'options'=>array(
			 'standard'=>"Standard addressing" // number, street, unit, etc.
			,'proxied'=>"PO Boxes, proxies" // delivery via the post office or mail handling service rather than out in the world. This setting typically indicates a mail-only address.
			,'rural'=>"Rural delivery" // typically means something like "route or road rather than street" and often "box delivery rather than house delivery" - For these, unit is something like "BOX 210", premise may be null
			,'military'=>"Military postal system" // When chosen, the locality
			))
		,'district'=>array('name'=>'district', 'class'=>'t_select', 'sort'=>true, 'label'=>"State, etc.",
			'help'=>"You can specify a State, the District of Columbia, a US territory, or a Military theater (APO/FPO)", 'options'=>array(
			 'AL'=>"Alabama" ,'AK'=>"Alaska" ,'AS'=>"American Samoa" ,'AZ'=>"Arizona" ,'AR'=>"Arkansas"
			,'CA'=>"California" ,'CO'=>"Colorado" ,'CT'=>"Connecticut"
			,'DE'=>"Delaware" ,'DC'=>"District of Columbia"
			,'FM'=>"Federated States of Micronesia" ,'FL'=>"Florida"
			,'GA'=>"Georgia" ,'GU'=>"Guam"
			,'HI'=>"Hawaii"
			,'ID'=>"Idaho" ,'IL'=>"Illinois" ,'IN'=>"Indiana" ,'IA'=>"Iowa"
			,'KS'=>"Kansas" ,'KY'=>"Kentucky"
			,'LA'=>"Louisiana"
			,'ME'=>"Maine" ,'MH'=>"Marshall Islands" ,'MD'=>"Maryland" ,'MA'=>"Massachusetts" ,'MI'=>"Michigan" ,'MN'=>"Minnesota" ,'MS'=>"Mississippi" ,'MO'=>"Missouri" ,'MT'=>"Montana"
			,'NE'=>"Nebraska" ,'NV'=>"Nevada" ,'NH'=>"New Hampshire" ,'NJ'=>"New Jersey" ,'NM'=>"New Mexico" ,'NY'=>"New York" ,'NC'=>"North Carolina" ,'ND'=>"North Dakota" ,'MP'=>"Northern Mariana Islands"
			,'OH'=>"Ohio" ,'OK'=>"Oklahoma" ,'OR'=>"Oregon"
			,'PW'=>"Palau" ,'PA'=>"Pennsylvania" ,'PR'=>"Puerto Rico"
			,'RI'=>"Rhode Island"
			,'SC'=>"South Carolina" ,'SD'=>"South Dakota"
			,'TN'=>"Tennessee" ,'TX'=>"Texas"
			,'UT'=>"Utah"
			,'VI'=>"Virgin Islands" ,'VT'=>"Vermont" ,'VA'=>"Virginia"
			,'WA'=>"Washington" ,'WV'=>"West Virginia" ,'WI'=>"Wisconsin" ,'WY'=>"Wyoming"
			,'AA'=>"Armed forces: Americas (except Canada)", 'AE'=>"Armed forces: Europe, Middle East, Africa, & Canada", 'AP'=>"Armed forces: Asia & Pacific"
			))
		,'locality'=>array('name'=>'locality', 'class'=>'t_string', 'sort'=>true, 'label'=>"City, town, or village", 'pattern'=>'^\w[\w\s]{0,22}\w$')
		,'sublocal'=>array('name'=>'sublocal', 'class'=>'t_string', 'sort'=>false, 'label'=>"Not used")
		,'postcode'=>array('name'=>'postcode', 'class'=>'t_string', 'sort'=>true, 'label'=>"ZIP code (5-digit)", 'pattern'=>'^\d{5}$')
		,'street'=>array('name'=>'street', 'class'=>'t_string', 'sort'=>true, 'label'=>"Street or route", 'pattern'=>'^[[:alpha:] ]{0,38}$')
		,'premise'=>array('name'=>'premise', 'class'=>'t_string', 'sort'=>true, 'label'=>"Number", 'pattern'=>'^\d{0,6}$')
		,'unit'=>array('name'=>'unit', 'class'=>'t_string', 'sort'=>true, 'label'=>"Secondary unit", 'pattern'=>'^[[:alnum:] ]{0,39}$',
			'help'=>"See <a href=\"https://www.usps.com/ship/official-abbreviations.htm\">USPS Official abbreviations</a>")
		,'dropcode'=>array('name'=>'dropcode', 'class'=>'t_string', 'sort'=>true, 'label'=>"ZIP+4", 'pattern'=>'^\d{4}$')
		,'cluster'=>array('name'=>'cluster', 'class'=>'t_string', 'sort'=>false, 'label'=>"Not used")
	);

	public static function normalizeAddressString( $value )
	{
		return	trim(
				str_replace(',', "\n",
				preg_replace('/\s+/', ' ', // replace groups of whitespace with a single space
				preg_replace('|[^\w, -]+|', '', // replace all non-allowable characters with underscores (we only allow alphanumeric, newline, space, and dash/hyphen)
				preg_replace("/\s*[,\n]\s*/", ',', // replace all padded commas and newlines with unpadded commas
				preg_replace('|</?[A-Z]+(\s[^>]*)?>|', ' ', // replace all ml tags with space
				strtoupper(
			$value
			)))))));
		/* After normalization you can count on using these conventions:
		* All letters in uppercase.
		* \w is equivalent to printable non-space
		* \s is equivalent to any word separator, including line breaks and former commas
		* ' ' (space) is equivalent to all spaces except line break
		*/
	}
	
	public static $subPatterns = array(
		 'unit'=>"((PO BOX|[A-Z]{2,20})( [\w-]{0,12})?)"			// 'unit','u1','u2'
		,'premise'=>"(\d{1,6})"											// 'premise'
		,'country'=>"(USA?|UNITED STATES( OF AMERICA)|AMERICA)"         // 'country','c1'
		,'street'=>"(((E|EAST|N|NORTH|S|SOUTH|W|WEST) )?\d*[A-Z ]{2,40})"	// 'street','s1','s2'
		,'locality'=>"([A-Z][A-Z ]{0,20}?[A-Z])"							// 'locality'
		,'district'=>"([A-Z][A-Z]|[A-Z]+( [A-Z])?)"                       // 'district','d1'
		,'postcode'=>"(\d{5})"											// 'postcode'
		,'dropcode'=>"([-_ ](\d{4}))"									// 'v1','dropcode'
		,'milspec'=>"([AF]PO)\s(A[EPA])"								// 'locality','district'
		,'milAE'=>"(APO)[, ](AE)[, ]((09001)(-(5275))?)?"				// 'locality','district','m1','postcode','m2','dropcode'
		,'milAP'=>"(FPO)[, ](AP)[, ]((96606)(-(2783))?)?"				// 'locality','district','m1','postcode','m2','dropcode'
		,'milAA'=>"(APO)[, ](AA)[, ]((34035)(-(4198))?)?"				// 'locality','district','m1','postcode','m2','dropcode'
	);
	public static $militaryDefs = array(
		 'AE'=>array('postcode'=>"09001",'dropcode'=>"5275",'locality'=>"APO",'street'=>null)
		,'AP'=>array('postcode'=>"96606",'dropcode'=>"2783",'locality'=>"FPO",'street'=>null)
		,'AA'=>array('postcode'=>"34035",'dropcode'=>"4198",'locality'=>"APO",'street'=>null)
	);

	/**
	* Produces a single property (line) in a vCard 4.0 format. (See http://tools.ietf.org/html/rfc6350#section-6.3.1)
	* @param string $PID Use this property ID; if none is provided on the call, we don't set one in the property. We always set the second value to 1, ie "PID=$PID.1;".
	* @return string A vCard ADR line, without a newline at the end.
	*/
	public function getVCardProperty( $PID = null )
	{
		switch ($this->scheme) {
			case 'standard': $street = trim("$this->°premise $this->°street $this->°unit"); break;
			case 'proxied': $street = "$this->°unit"; break;
			case 'rural': $street = trim("$this->°street $this->°premise $this->°unit"); break;
			case 'military': $street = trim("UNIT $this->°premise $this->°unit"); break;
		}
		$code = $this->postcode . ($this->dropcode ? "-$this->dropcode" : null);
		return 'ADR;'. ($PID ? "PID=$PID.1;" : null) .'LABEL="'. str_replace("\n", '\n', $this->format()) ."\":;;$street;$this->°locality;$this->district;$code;";
	}

	/**
	* Call this to parse out the various components of a USPS address from a string which has already been normalized with normalizeAddressString()
	* @param string $normal The normalized address string.
	* @param string[] $fd Field definition for the entire address in aggregate... allows us to throw BadFieldValueX exceptions directly.
	* @return false|string[] The values of the various international address components.
	*/
	public static function getFieldValuesFromNormalString( $normal, array $fd )
	{
		$sps = self::$subPatterns;
		$schemeInfo = array(
			 'military'=>array('label'=>"Military", 'extractor'=>"^UNIT $sps[premise]( $sps[unit])?\s$sps[milspec]($sps[postcode]-$sps[dropcode])?(\s$sps[country])?$"
				,'telltale'=>"$sps[milspec]", 'fields'=>array('whole','premise','i1','unit','u1','u2','locality','district','postcode','v1','dropcode','i2','country','c1')
				,'finalize'=>function($x) {	return array_merge($x, self::$militaryDefs[$x['district']], array('unit'=>preg_replace("/[A-Z ]+ /", "BOX ", $x['unit']), 'street'=>null)); })
			,'proxied'=>array('label'=>"Post office box", 'extractor'=>"^$sps[unit]\s$sps[locality]\s$sps[district]\s$sps[postcode]$sps[dropcode]?(\s$sps[country])?$"
				,'telltale'=>"^(PO )?BOX", 'fields'=>array('whole','unit','u1','u2','locality','district','d1','postcode','v1','dropcode','i1','country','c1')
				,'finalize'=>function($x) { return array_merge($x, array('unit'=>preg_replace("/^BOX /", "PO BOX ", $x['unit']),'street'=>null,'premise'=>null)); })
			,'standard'=>array('label'=>"Standard street address", 'extractor'=>"^$sps[premise] $sps[street](\s$sps[unit])?\n$sps[locality]\s$sps[district]\s$sps[postcode]$sps[dropcode]?(\s$sps[country])?$"
				,'telltale'=>"^\d+ \w.+\n([\w ]*\n)?\w.+ \d{5}", 'fields'=>array('whole','premise','street','s1','s2','i1','unit','u1','u2','locality','district','d1','postcode','v1','dropcode','i2','country','c1')
				,'finalize'=>function($x) { if (($p=$x['premise']>0) && ($d=strlen($x['district'])>1) && ($l=strlen($x['locality'])>1) && ($s=strlen($x['street'])>1)) return $x;
					throw new BadFieldValueX($fd, "Invalid standard address format". ($p?'':", street number").($d?'':", state").($l?'':", city").($s?'':", street name")."."); })
			,'rural'=>array('label'=>"Rural route (or misc.)", 'extractor'=>"^$sps[street]( $sps[premise])?(\s$sps[unit])?\n$sps[locality]\s$sps[district]\s$sps[postcode]$sps[dropcode]?(\s$sps[country])?$"
				,'telltale'=>"^(\w.+\n)+\w.+ \d{5}", 'fields'=>array('whole','street','s1','s2','i0','premise','i1','unit','u1','u2','locality','district','d1','postcode','v1','dropcode','i2','country','c1')
				,'finalize'=>function(&$x) { return $x; })
			);
		foreach ($schemeInfo as $scheme=>$info) {
			extract($info);
			if (preg_match("/$telltale/", $normal)) {
				if (preg_match("/$extractor/", $normal, $m))
					return array_merge($finalize(array_combine($fields, array_pad($m,count($fields),''))), array('country'=>'USA','scheme'=>$scheme)); // may throw BadFieldValueX
				throw new BadFieldValueX($fd, "Address appears to be of the '$label' format, but we could not recognize all its parts.");
			}
		}
		throw new BadFieldValueX($fd, "Could not recognize the address format as one of these: '". implode("','", array_keys($schemeInfo)) ."'.");
	}
	
	/**
	* We can accept an array of values as rendered for input. The array may be sparse.
	* We can also accept a string which we'll do our best to parse out.
	* Regardless of how the value is received or what values we had previously, the resulting address is defined to be null unless it includes at least a district (state) or street.
	*/
	public function accept( $value, array $fd )
	{
		if (!$value && !is_array($value))
			return null; // we take no action here because we simply allow the scalar to become null while the (loaded or set) object sits untouched.
		if (is_string($value))
			$value = self::getFieldValuesFromNormalString(self::normalizeAddressString($value), $fd);
		if (is_array($value)) {
			try { $updated = $this->acceptValues($value); }
			catch (BadFieldValuesX $ex) { throw new BadFieldValueX($fd, $ex->getMessage()); }
			if ($updated < 1)
				logWarn(array("Values to be accepted by ". get_class($this) ." resulted in no updates.", "Submitted array"=>$value));
		} else
			throw new BadFieldValueX($fd, "Invalid input type");
		return ($this->street || $this->district) ? $this->_id : null;
	}
	
	/**
	* Format an address. We format for multi-line... you can flatten it by replacing our newlines if you want to.
	* See: http://www.upu.int/fileadmin/documentsFiles/activities/addressingUnit/usaEn.pdf
	* Such that any line with no content is not rendered at all.
	* @param string $fromCountry (optional) Pass the three-letter uppercase identifier of the country from which the mail will be posted.
	*		This is used to determine whether we should specify the country in the address produced. (Same country means not required.)
	* @param string $addressee (optional) Pass an addressee, ie. the entity's name, if you would like to produce a mailing address ready to send to that addressee at this address.
	* @return string The formatted address using newlines to separate the lines.
	*/
	public function format( $addressee = null, $fromCountry = 'USA'  )
	{
		$lines = array();
		if ($addressee)
			$lines[] = $addressee;
		if ($this->cluster)
			$lines[] = $this->cluster;
		if ($this->scheme == 'military') {
			// In the military scheme, the military unit is the premise, there's no street, and the unit should be a box number like "BOX 2010"
			if ($this->premise)
				$lines[] = "UNIT $this->premise $this->unit";
			$lines[] = $this->district == 'AE' ? "APO AE 09001-5275" : ($this->district == 'AP' ? "FPO AP 96606-2783" : "APO AA 34035-4198");
			$fromCountry = null; // force the inclusion of the country name at the end.
		} else {
			if ($this->scheme == 'standard') {
				if ($this->street)
					$lines[] = (strlen($this->premise) ? "$this->premise " : null) ."$this->street". (strlen($this->unit) ? " $this->unit" : null);
			} else if ($this->scheme == 'proxied') {
				$lines[] = "$this->unit"; // unit should contain something like "PO BOX 20102"
			} else if ($this->scheme == 'rural' && ($this->unit || $this->street)) {
				// In the rural scheme, there's no premise, and the street might be a route.
				$lines[] = ($this->street ? "$this->street " : "Rural Delivery ") . ($this->premise ? "$this->premise " : '') . $this->unit;
			}
			if ($this->locality || $this->district || $this->postcode)
				$lines[] = ($this->locality ? "$this->locality " : null) . ($this->district ? "$this->district " : null) .
					($this->postcode ? ($this->postcode .($this->dropcode ? "-$this->dropcode" : null)) : null);
		}
		if ($fromCountry != $this->country) // We don't keep track of other countries, but if we find one, we show it.
			$lines[] = static::$Countries[$this->country];
		return mb_strtoupper(implode("\n", $lines));
	}

	/**
	* Rendering an address is a variation on the format, although the INPUT mode could be complex. 
	* Both render() and accept() bring in other type classes as defined in the subfields.
	* @param mixed[] $fd The field definition referencing this Fieldset subclass
	* @param HTMLRendering $R The rendering object
	* @param string $addressee (optional) If you want the address rendered with heading lines for the entity to receive something at the address, 
	*		then supply them formatted (not rendered) with newlines separating lines.
	* @param string $fromCountry (optional) If you want the address to work from a foreign country, supply any value here which is NOT "USA".
	* @return string HTML snippet rendered under the context provided.
	*/
	public function renderFieldset( array $fd = null, HTMLRendering $R, $addressee = null, $fromCountry = 'USA' )
	{
		$this->_rendering = $R;
		$class = __CLASS__;
		
		if ($R->mode != $R::INPUT) {
			$R->addStyles("div.$class { display:inline-block;white-space:nowrap;font-family:monospace;vertical-align:text-top }", __CLASS__);
			return "<div class=\"$class\">". str_replace("\n", '<br/>', htmlentities($this->format($addressee, $fromCountry))) .'</div>';
		}
		
		// Because we've worked so hard on our parsing capability, we just accept cut-n-paste or typed-in input in a simple text box.
		// We'll process it on the server and throw it back if we can't make sense of it.
		$R->addStyles("textarea.$class { display:inline-block;white-space:pre;font-family:monospace;vertical-align:text-top }", "$class-input");
		return <<<html
	<textarea rows="3" cols="32" class="form-control input $class" $R->tabindex id="$R->idprefix$fd[name]" name="$fd[name]" onchange="value=(value.replace(/\s+$/, '')).replace(/^\s+/, '')">$this</textarea>
html;

// Dead code below kept in case we want to take a structured input approach in the future.

		$rendered = $this->renderFields($R, array('country'));
		return <<<html
	<div class="form-control t_address_USA">
		$this->»cluster
		<span id="topprefix">$topprefix</span>$this->»premise$this->»street$this->»unit<br/>
		$this->»locality$this->»district<span id="codes">$this->»postcode-$this->»dropcode<input name="postcode"/>-<input name="dropcode"/></span><br/>
		$this->»scheme
	</div>
html;
	/*
	standard: hide unitlabel
	rural: hide premise, unitlabel; change street placeholder; constrain unit[type]?
	mil: drop street, locality, codes; Prefix Premise with "UNIT"; ensure BOX for Unit
	proxied: hide street, premise; set unit label to "PO"


		<input placeholder="Street address" class="input" id="$R->idprefix{$fn}_street" name="{$fn}[street]" value="$street"/>
		<br/><input placeholder="Apt / Suite / Other (as needed)" class="input" id="$R->idprefix{$fn}_street2" size="37" name="{$fn}[street2]" value="$street2"/>
		<br/><input placeholder="City or town" type="text" size="22" class="input" id="$R->idprefix{$fn}_city" name="{$fn}[city]" value="$city"/><input placeholder="State (abbr)" type="text" size="12" class="input" id="$R->idprefix{$fn}_state" name="{$fn}[state]" value="$state"/>
		<br/><input placeholder="Postal code" class="input" type="text" size="15" id="$R->idprefix{$fn}_code" name="{$fn}[code]" value="$code"/>
		<input type="text" size="19" class="input" id="$R->idprefix{$fn}_country" name="{$fn}[country]" value="$country"/>
	</div>
	<div class="form-control t_address_USA template" id="military" tabindex="-1">
	</div>
	<div class="form-control t_address_USA template" id="rural" tabindex="-1">
	</div>
	<div class="form-control t_address_USA template" id="proxied" tabindex="-1">
	</div>
html;
	*/
		list($tag, $liner) = $R->mode == $R::INLINE ? array('span','; ') : array('div','<br/>');
		return "<$tag class=\"postaddr\">". str_replace(' ', '&nbsp;', str_replace("\n", $liner, htmlentities(trim("$addressee\n". static::format($d, $fn, $R->context->locale))))) ."</$tag>";
	}

	public static function get( )
	{
		return new f_postaddr_USA;
	}
	
	// The handler for accepting individual field values
	public function acceptFieldValue( $f, $value )
	{
		if ($f == 'unit' && is_array($value))
			return $this->updated[$f] = "$value[type] $value[spec]";
		return parent::acceptFieldValue( $f, $value );
	}

	// Secondary units include a boolean indicating whether the unit specifier is required for each type.
	// These are drawn from the USPS specifications.
	static $SecondaryUnits = array(
		'APT'=>array("Apartment",true), 'BSMT'=>array("Basement",false), 'BOX'=>array("P.O. Box",true), 'BLDG'=>array("Building",true), 'DEPT'=>array("Department",true),
		'FL'=>array("Floor",true), 'FRNT'=>array("Front",false), 'HNGR'=>array("Hangar",true),
		'LBBY'=>array("Lobby",false), 'LOT'=>array("Lot",true), 'LOWR'=>array("Lower",false), 'OFC'=>array("Office",false), 'PH'=>array("Penthouse",false),
		'PIER'=>array("Pier",true), 'REAR'=>array("Rear",false), 'RM'=>array("Room",true), 'SIDE'=>array("Side",false), 'SLIP'=>array("Slip",true), 'SPC'=>array("Space",true),
		'STOP'=>array("Stop",true), 'STE'=>array("Suite",true), 'TRLR'=>array("Trailer",true), 'UNIT'=>array('Unit',true), 'UPPR'=>array("Upper",false));
}
