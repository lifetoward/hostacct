<?php

// LIMITATION: We do not currently have implementation of the element repeat path. Thus the repeat delimiter is ignored and any data which includes it could be confused after parsing.
// However, it should not disrupt parsing of any surrounding data, only the repeated element.

abstract class x12_element // the element is the smallest unit of data, a field, in X12
{
	public $label; // For human access to the information, including form prompting
	protected $usage = 'R'; // values are R = required; S = situational; N = not used; S fields are omitted if the conditions are right. N fields can be omitted unless they are needed as placeholders to properly identify later required elements
	protected $value; // this value is in "simple native" form

	public function report( $label = true )
	{
		return $this->assigned() ? ($label ? "$this->label=" : null). trim($this->value) : null;
	}

	public function __construct( $label, $usage = 'R' )
	{
		$this->label = $label;
		switch ($usage) { case 'R': case 'N': case 'S': $this->usage = $usage; }
	}

	public function __clone( )
	{
		unset($this->value);
	}

	public function __set( $name, $value )
	{
		if ('value'!=$name && '_'!=$name)
			throw new Exception("Attempt to set invalid property ". __CLASS__ ."->'$name'");
//			return logError("Attempt to set invalid property ". __CLASS__ ."->'$name'");
		$this->assign($value); // assign() may be overriden by subclasses!
	}

	public function __get( $name )
	{
		if ('value'==$name || '_'==$name)
			return $this->value;
		if ('assigned'==$name)
			return $this->assigned();
		else
			return logError("Attempt to get invalid property ". __CLASS__ ."->'$name'");
	}

	public function assign( $value )
	{
		$this->value = $value;
	}

	public function assigned( )
	{
		return isset($this->value);
	}

	public function render( )
	{
		// Remove any delimiters from rendered element values
		$pattern = '/['. implode(null, $delimiters = x12seg_ISA::$delimiters) .']/';
		foreach (array('_', ' ', '?', '.', '#') as $replacement) // we must have more candidate replacements than there are delimiters to avoid
			if (!in_array($replacement, $delimiters))
				break;
		return preg_replace($pattern, $replacement, "$this"); // Note that this approach relies on the subclass to provide __toString() as a rendering in x12 format
	}

	public function __toString( )
	{
		return "$this->value";
	}
}

// Comp stands for composite/complex. It contains sub-elements (components) within it separated by the component delimiter
class x12el_comp extends x12_element
{
	public static $delimiter = ':';

	public function report( $label = true )
	{
		if (!$this->assigned())
			return null;
		foreach ($this->value as $val)
			if ($val->assigned())
				$out[] = $val->report($label);
		return ($label ? "$this->label=" : null) ."(". implode('; ', $out) .")";
	}


	// When constructing a comp type element, you must pass an array of x12_element's (not of type x12el_comp) as the components
	public function __construct( $label, array $components, $usage = 'R' ) // corresponds to X12 multi-component elements
	{
		$this->value = array();
		foreach ($components as $comp) {
			if (!$comp instanceof x12_element || $comp instanceof x12el_comp)
				throw new Exception("A componentized element must be constructed of scalar elements.");
			$this->value[] = $comp;
		}
		parent::__construct($label, $usage);
	}

	public function __clone( )
	{
		foreach ($this->value as &$component)
			$component = clone $component;
	}

	public function __get( $name )
	{
		if ('component' == $name)
			return $this->value;

		if ('value'==$name || '_'==$name) {
			foreach ($this->value as $comp)
				$values[] = $comp->value;
			return $values;
		}
		return parent::__get($name);
	}

	public function assign( ) // we actually accept an array, a string that needs component-level parsing, or a series of scalar args
	{
		$values = func_get_args();
		if (is_array($values[0]))
			$values = $values[0];
		else if (count($values) == 1) // a single value we'll attempt to split out by the delimiter, but if the split is a no-op we end up with the same single value anyway
			$values = $values[0] ? explode(self::$delimiter, $values[0]) : array();
		foreach ($this->value as $component)
			$component->assign($val = array_shift($values));
	}

	public function assigned( )
	{
		if (!is_array($this->value))
			return false;
		foreach ($this->value as $c)
			if ($c->assigned)
				return true;
		return false;
	}

	public function render( )
	{
		if (!count($this->value))
			return null; // unassigned
		foreach ($this->value as $component)
			$values[] = $component->render();
		return preg_replace('/\\'. self::$delimiter .'*$/', null, implode(self::$delimiter, $values));
	}

	// Note that unlike other element types, for x12el_comp, toString() depends on render() rather than the other way around.

	public function __toString()
	{
		return $this->render();
	}
}

/* from the official spec:
An identifier data element always contains a value from a predefined list of codes that is maintained by the ASC X12 Committee
	or some other body recognized by the Committee.
Trailing spaces should be suppressed unless they are necessary to satisfy a minimum length.
An identifier is always left justified.
The representation for this data element type is "ID."
*/
class x12el_code extends x12_element // corresponds to X12 "ID"; use for enumerations
{
	// Note that the options array is responsible for using keys that conform to the element sizing requirements. We don't check sizing in any way.
	public $options = array();  // format of options array: {rendered value}=>{human readable description}, ...
	public $strict = false; // if true, enforce that assignments are found in the keys of the options array

	public function report( $label = true )
	{
		return ($label ? "$this->label=" : null). (($derefd = $this->options[$this->value]) && $derefd != '*added' ? $derefd : $this->value);
	}

	public function __construct( $label, array $options = array(), $usage = 'R', $strict = false )
	{
		$this->options = $options;
		parent::__construct($label, $usage);
	}

	public function assign( $value )
	{
		if (!isset($this->options[$value])) {
			if ($this->strict)
				throw new Exception("Attempt to assign value '$value' to element '$this->label' failed because that value is not registered as valid.");
			else
				$this->options[$value] = "*added";
		}
		$this->value = $value;
	}

	// Here are some common code mappings which are used in multiple segment types
	public static $statesAndProvinces = array(
		 'AL'=>'Alabama'
		,'AK'=>'Alaska'
		,'MA'=>'Massachusetts'
		,'NH'=>'New Hampshire'
		,'NY'=>'New York'
		,'PA'=>'Pennsylvania'
		,'VT'=>'Vermont'
		);
	public static $yesNo = array('Y'=>'Yes', 'N'=>'No');
	public static $relationshipCodes = array(
		 '01'=>'Spouse'
		,'18'=>'Self'
		,'19'=>'Child'
		,'20'=>'Employee'
		,'21'=>'Unknown'
		,'39'=>'Organ Donor'
		,'40'=>'Cadaver Donor'
		,'53'=>'Life Partner'
		,'G8'=>'Other Relationship'
		);
	static $countryCodes = array(
		 'USA'=>'United States of America'
		);

}

/* from the official spec:
A string data element is a sequence of any characters from the basic or extended character sets.
The significant characters shall be left justified.
Leading spaces, when they occur, are presumed to be significant characters.
Trailing spaces should be suppressed unless they are necessary to satisfy a minimum length.
The representation for this data element type is "AN."
*/
class x12el_text extends x12_element // corresponds to X12 "AN" element ID
{
	protected $max, $min;

	public function __construct( $label, $sizing, $usage = 'R' ) // args is complicated by an optional min at the front
	{
		if (is_array($sizing)) {
			$this->min = array_shift($sizing)*1;
			$this->max = array_shift($sizing)*1;
		} else
			$this->max = ($this->min = $sizing*1);
		parent::__construct($label, $usage);
	}

	public function __toString( )
	{
		if (!$this->value && $this->usage != 'R')
			return "";
		return strlen($this->value) > $this->max ? substr($this->value, 0, $this->max) : str_pad($this->value, $this->min);
	}

}

/* From the official spec:
 A numeric data element is represented by one or more digits with an optional leading sign representing a value in the normal base of 10.
 The value of a numeric data element includes an IMPLIED decimal point. It is used when the position of the decimal point within the data
	is permanently fixed and is not to be transmitted with the data.
 This set of guides denotes the number of implied decimal positions. The representation for this data element type is "Nn" where N
	indicates that it is numeric and n indicates the number of decimal positions to the right of the implied decimal point. If n is 0, it need
	not appear in the specification; N is equivalent to N0.
For negative values, the leading minus sign (-) is used. Absence of a sign indicates a posi-tive value. The plus sign (+) should not be transmitted.
EXAMPLE: A transmitted value of 1234, when specified as numeric type N2, represents a value of 12.34.
Leading zeros should be suppressed unless necessary to satisfy a minimum length requirement.
The length of a numeric type data element does not include the optional sign.
*/
class x12el_fixdec extends x12_element // corresponds to X12 "N?" element ID
{
	protected $max, $min, $right; // these are digit-count sizings; right means "places right of the decimal point". Decimal points are implied positionally in this type; possible - sign not included in sizing

	// A scalar sizing value indicates 0 decimal places and a fixed digit count (min = max) of the given value.
	// An array specifies minimum places, maximum places, and number of places to the right of the implied decimal.
	function __construct( $label, $sizing, $usage = 'R' )
	{
		if (is_array($sizing)) {
			$this->min = array_shift($sizing)*1;
			$this->max = array_shift($sizing)*1;
			$this->right = array_shift($sizing)*1;
		} else {
			$this->max = ($this->min = $sizing * 1);
			$this->right = 0;
		}
		parent::__construct($label, $usage);
	}

	public function assign( $value )
	{
		if (!is_numeric($value))
			return;
		$divisor = is_numeric($value) ? 1 : pow(10, $this->right);
		if (abs($value) >= ($maxabs = pow(10, $this->max - $this->right))) { // out of range
			$this->value = ($value < 0 ? -1 : 1) * (pow(10, $this->max) - 1) / $divisor;
			return logError("Attempt to assign numeric value ($value) out of range of element '$this->label'. Maximum magnitude ($value) is used.");
		}
		$this->value = $value / $divisor;
	}

	public function __toString( )
	{
		return isset($this->value) ? sprintf("%0{$this->min}d", round($this->value * pow(10, $this->right))) : '';
	}
}

/* From the official spec:
A decimal data element may contain an explicit decimal point and is used for numeric values that have a varying number of decimal positions.
This data element type is represented as "R."
The decimal point always appears in the character stream if the decimal point is at any place other than the right end.
	If the value is an integer (decimal point at the right end) the decimal point should be omitted.
For negative values, the leading minus sign (-) is used. Absence of a sign indicates a positive value. The plus sign (+) should not be transmitted.
Leading zeros should be suppressed unless necessary to satisfy a minimum length requirement.
	Trailing zeros following the decimal point should be suppressed unless necessary to indicate precision.
The use of triad separators (for example, the commas in 1,000,000) is expressly prohibited.
The length of a decimal type data element does not include the optional leading sign or decimal point.
EXAMPLE
A transmitted value of 12.34 represents a decimal value of 12.34.
*/
class x12el_numeric extends x12_element // corresponds to X12 "R" element ID
{
	protected $min, $max;

	// A scalar sizing value indicates a fixed digit count (min = max) of the given value.
	// An array specifies minimum and maximum digit counts respectively.
	public function __construct( $label, $sizing, $usage = 'R' )
	{
		if (is_array($sizing)) {
			$this->min = array_shift($sizing)*1;
			$this->max = array_shift($sizing)*1;
		} else {
			$this->min = 1;
			$this->max = 1 * $sizing;
		}
		parent::__construct($label, $usage);
	}

	public function assign( $value )
	{
		list($this->value) = sscanf($value, "%f");
	}

	public function __toString()
	{
		return "$this->value";
	}

}

/* from the official spec:
A date data element is used to express the standard date in either YYMMDD or CCYYMMDD format
	in which CC is the first two digits of the calendar year, YY is the last two digits of the calendar year,
	MM is the month (01 to 12), and DD is the day in the month (01 to 31).
The representation for this data element type is "DT."
Users of this guide should note that all dates within transactions are 8-character dates (millennium compliant)
	in the format CCYYMMDD. The only date data element that is in format YYMMDD is the Interchange Date
	data element in the ISA segment, and also used in the TA1 Interchange Acknowledgment, where the century
	can be readily interpolated because of the nature of an interchange header.
*/
class x12el_date extends x12_element // corresponds to X12 "DT" ID
{
	// We store the value according to the format specifier
	public $format;
	// We accept "6", "YYMMDD", "8", or "CCYYMMDD" as length formats which we convert ourselves; Otherwise the formats we recognize are those defined for PHP as in : http://de3.php.net/manual/en/datetime.formats.date.php

	public function report( $label = true )
	{
		$date = DateTime::createFromFormat($this->format, $this->value);
		return ($label ? "$this->label=" : null). $date->format('j M Y');
	}

	function __construct( $label, $format = 8, $usage = 'R' )
	{
		$this->format = (6==$format || 'YYMMDD'==$format ? 'ymd' : (8==$format || 'CCYYMMDD'==$format ? 'Ymd' : $format));
		parent::__construct($label, $usage);
	}

	public function assign( $value = null ) // we accept values in any recognizable format and store the value in the defined format
	{
		$this->value = date($this->format, $value ? strtotime($value) : time()); // note that an empty value means "now!"
	}

}

/* from the official spec:
A time data element is used to express the ISO standard time HHMMSSd..d format
	in which HH is the hour for a 24 hour clock (00 to 23), MM is the minute (00 to 59), SS is the second (00 to 59)
	and d..d is decimal seconds.
The representation for this data element type is "TM."
The length of the data element determines the format of the transmitted time.
EXAMPLE: Transmitted data elements of four characters denote HHMM. Transmitted data elements of six characters denote HHMMSS
*/
class x12el_time extends x12_element // corresponds to X12 "TM" ID
{
	public $format;
	// For x12 we've observed mention of a discrete set of formats as determined by their size, so the format will be determined by the size of the field passed in during construction
	// We will store our value in the format specified, which allows us to

	public function report($label = true)
	{
		$ap = array('am','pm');
		$h = substr($this->value, 0,2);
		$m = substr($this->value, 2,2);
		$H = $h%12;
		$ampm = $ap[floor($h/12)];
		return ($label ? "$this->label=" : null) ."$H:$m$ampm";
	}

	public function __construct( $label, $size = 4, $usage = 'R' )
	{
		if (6==$size)
			$this->format = 'His';
		else if (7==$size)
			$this->format = 'His0'; // note that we don't offer tighter precision than 1 sec
		else if (8==$size)
			$this->format = 'His00';  // note that we don't offer tighter precision than 1 sec
		else // presume 4
			$this->format = 'Hi';
		parent::__construct($label, $usage);
	}

	public function assign( $value = null ) // assigning to a null value implies "now"
	{
		$this->value = date($this->format, $value ? strtotime($value) : time());
	}

}

class x12el_notused extends x12_element // can be used for any element type which is marked N/U; it always renders as empty and parses without prejudice
{
	public function report()
	{
		return null;
	}

	public function __construct( $label )
	{
		parent::__construct($label, 'N');
	}

	public function __toString( )
	{
		return "";
	}
}

?>
