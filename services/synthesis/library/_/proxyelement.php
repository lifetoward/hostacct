<?php
namespace Lifetoward\Synthesis\_ ;
/**
* It looks and acts a lot like an Element, but:
*	- there is no storage within our system, so methods like store() and delete() must be implemented against the remote system
*	- there is no implied key field, at least not as applies within our system.
*	- fields marked as identifying determine the uniqueness of records
*	- persistence is in a session only and until freed
*/
class ProxyElement extends Instance
{
	use FieldOps;
	public function getOperation( $operation, Context $c = null, $actionArg = '_action', $args = array(), $accept = false )
	public function free()
	public function render( HTMLRendering $R )
	public function serialize()
	public function unserialize($rep)
	public function store( Database $db = null )
	public function duplicate( )
	public function delete( )
	public function load( )
	public function acceptValues( )
	public function latent( $args = null )
	public function loaded( )
	public function asJSON( array $fields = null )
	public function getMetaProperty( $prop )
	{
	}
	public function getMetaProperty( $name )
	{
		switch ($name) {
			case 'cacheHash':
			case 'handle':   		return get_class($this) ."=$this->key";
			case 'formatted':	return $this->formatted();
			case 'rendered':		return htmlentities($this->formatted());
			case 'stored':     	return $this->key && true;
			case 'capcel':      	return $this->capcel;
		}
		return parent::getMetaProperty($name);
	}
	public function formatted()
	public function __toString()

	public function __get( $name )
	{
		$main = mb_substr($name, 1);
		switch (mb_substr($name, 0, 1)) {
			case '_':	return $this->getMetaProperty($main);
			case '¶': 	return $this->getFieldDef($main); // To get this ¶ character (00B6 "pilcrow"): Mac = Alt-7; Linux = Compose ! p;
			case '¬': 	return $this->getFieldLabel($main); // To get this ¬ character (00AC "not"): Mac = Alt-L; Linux = Compose - , ;
			case '¿':	return $this->getFieldHelp($main); // To get this ¿ character (00BF "invert question"): Mac = Shift-Alt-?; Linux = Compose ? ?
			case '°': 	return $this->formatField($main); // To get this ° character (00B0 "degree"): Mac = Shift-Alt-8; Linux = Compose 0 *
			case '»': 	return $this->renderField($main, $this->_rendering); // To get this » character (00BB "double rt angle quote"): Mac = Shift-Alt-\; Linux = Compose > >
			case '•': 	return $this->numericizeField($main); // To get this • character (2022 "bullet"): Mac = Alt-8; Linux = Compose . =
			case '√':	return $this->getFieldValue($main, true); // If it's a reference, get the handle rather than the object. To get this On Mac this is Alt-V; Linux = Compose v /
			// For help with special characters on Linux see http://fsymbols.com/keyboard/linux/compose/ or the more complete http://www.x.org/releases/X11R7.7/doc/libX11/i18n/compose/en_US.UTF-8.html
		}
		try {
			return $this->getFieldValue($name);
		} catch (NotMyFieldX $ex) {
			return $this->aux[$name];
		}
	}

}

