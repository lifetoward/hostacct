<?php
/**
* Our goal with the base class f_postaddr is especially to define a consistent database representation for ANY postal address worldwide.
* See http://www.upu.int/en/activities/addressing/postal-addressing-systems-in-member-countries.html for details about proper formatting of addresses worldwide.
* Subclasses will detail how each of the fields is used and represented. Subclasses will be named e_postaddr_TLC, where TLC is the uppercase 3-letter country code.
*
* All original code.
* @package Synthesis/Contacts
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2004-2014 Lifetoward LLC
* @license proprietary
*/
abstract class f_postaddr extends Fieldset
{
	private static $savedProperties = array('refdef','class');

	static $sysvals = array('refdef','class'), $table = 'com_postaddr', $singular = "Postal address", $plural = "Postal addresses", $descriptive = "Postal address",
		$fielddefs = array(
			 'country'=>array('name'=>'country', 'class'=>'t_select', 'options'=>array('USA'=>"United States of America"), 'label'=>"Country", 'required'=>true,
				'help'=>"The country determines the addressing scheme and can properly indicate the subclass of e_postaddr to use, ie. e_postaddr_{three-letter country code}. See http://countrycode.org/")
			,'scheme'=>array('name'=>'scheme', 'class'=>'t_select', 'initial'=>'standard', 'label'=>"Addressing scheme", 'required'=>true, 'options'=>array(
					 'standard'=>"Standard addressing" // number, street, unit, etc.
					,'proxied'=>"PO Boxes, proxies" // delivery via the post office or mail handling service rather than out in the world. This flag differentiates package deliverable and geographic addresses from mail-only addresses, the latter being proxied.
				), 'help'=>"These don't sort, but there are some flag settings which help with the proper rendering or interpretation of the address.")
			,'district'=>array('name'=>'district', 'class'=>'t_select', 'sort'=>true, 'label'=>"State, Province, District, Region",
				'help'=>"This is the postal administrative region. In the US it's a state, in Canada, the province, in other countries it might be a region or jurisdiction. Some small countries might not use this.")
			,'locality'=>array('name'=>'locality', 'class'=>'t_string', 'sort'=>true, 'label'=>"Locality, City, Town, Village",
				'help'=>"A municipality, city, or other subregional area. This is generally an alphabetic string.")
			,'sublocal'=>array('name'=>'sublocal', 'class'=>'t_string', 'sort'=>true, 'label'=>"Sub-locality, Ward, Precinct, Neighborhood, Sector",
				'help'=>"Sublocal names are sometimes used in more densely populated systems.")
			,'postcode'=>array('name'=>'postcode', 'class'=>'t_string', 'sort'=>true, 'label'=>"Post office code",
				'help'=>"The postcode usually encodes a delivering station, ie. a post office. For example in the US the 5-digit ZIP is the main code as would appear here. This is an alphanumeric code.")
			,'street'=>array('name'=>'street', 'class'=>'t_string', 'sort'=>true, 'label'=>"Street, Road, Route, Thoroughfare, etc.",
				'help'=>"The thoroughfare, street, road, lane, or access means. Includes directional signifiers like N, North, or West")
			,'premise'=>array('name'=>'premise', 'class'=>'t_string', 'sort'=>true, 'label'=>"Premise, Building, House number, Address",
				'help'=>"The number of the location along the street, like a house number, complex number, etc. Usually coincides with a driveway or street frontage.")
			,'cluster'=>array('name'=>'cluster', 'class'=>'t_string', 'sort'=>true, 'label'=>"Complex, Cluster, Building group, Section",
				'help'=>"A building complex or section within a property.")
			,'unit'=>array('name'=>'unit', 'class'=>'t_string', 'sort'=>true, 'label'=>"Delivery point, Apartment, Suite, Unit, Floor, Office, Maildrop",
				'help'=>"A subspecifier of physical signicance within the premise.")
			,'dropcode'=>array('name'=>'dropcode', 'class'=>'t_string', 'sort'=>true, 'label'=>"Postal drop point code",
				'help'=>"The +4 in the US or other final specifier of a drop point for mail using the coded system.")
		);
	
	static $Countries = array(
		 'USA'=>"United States of America"
		);
	
	// Because we care about the class of implementation, we need to initialize that sysval.
	public static function create( $nest, $f, $initial = null )
	{
		$instance = parent::create($nest, $f, $initial);
		$instance->class = get_called_class();
		return $instance;
	}

	/**
	* Call this to obtain the content of a PDF file with a single page containing an envelope with this address.
	* @param string $addressee A string which will serve as the first line of the recipient address section. Usually comes from the instance embedding $this.
	* @param f_postaddr|string $fromAddress A string representing a completely assembled return address formatted for simple text output (newline-separated, with addressee)
	* @param string $envelope An envelope selector. Recognized envelope specifications are in f_postaddr::$envelopeSpecs
	* @return FPDF An FPDF object suitable for passing to EnvelopePDF::[getPDFContent|asAttachment|writeToFile]($result)
	*/
	public function attachEnvelopePDF( $addressee, $fromAddress = null, $envelope = '#10' )
	{
		EnvelopePDF::render("$addresee\n$this", "$fromAddress", $envelope)->asAttachment("$addressee.pdf");
	}

	// A label sheet specification requires 4 values specified in points:
	static $labelSpecs = array(
		), $labelStandards = array();

}
