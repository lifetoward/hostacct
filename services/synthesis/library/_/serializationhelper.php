<?php
/**
* Having found myself rewriting the same code over and over for each object with persistent properties, I decided to write the serialization routines as a trait.
* Recall that implementing Serializable means that a class and all its subclasses must provide serialize() and unserialize() methods or their properties will not be serialized.
* If you're working within a subclass of a class that implements Serializable, we highly recommend using this trait to simplify your job of keeping (some of) your properties persistent across requests.
* ALL core library classes implement Serializable, specifically Action, Context, and Instance; All their children must also.
* If you're not extending one of those classes, you may need to expressly implement Serializable yourself to use this trait.
*	BEWARE!! : If you use this trait without implementing Serializable, you'll get very bad results which can appear like corrupted sessions!
*
* To use this trait, a class must define a private static property called $savedProperties which should contain a simple list of the names of the class properties you want saved.
*	Notably any properties are savable, including private ones, but because we automatically detect whether any parent class is serializable,
*		you should only handle your own local properties, not those in sub or parent classes, UNLESS you have complete knowledge that the parent does NOT implement Serializable, etc.
*
* If you would like to have some methods called after unserialization (wakeup) or before serialization (sleep), you can optionally proved an array as the first element of the $savedProperties array:
*	- This list array should include the names of the wakeup and sleep functions respectively. You can leave either null if you only need the other.
*
* When you use this helper as with any Serializable object, the magic __sleep() and __wakeup() methods will NOT be called automatically.
*	You could specify those names as your callbacks if you want, but you don't need to use those names under this scheme.
* Example:
* 	private static $savedProperties = array(array('when_i_wake'), 'instance', 'hints'); // saves self-defined class properties $instance and $hints, and $this->when_i_wake() is called after unserialization.
* 	private function when_i_wake() {
*		// this code executes after unserialization
*	}
* Note: You should ALWAYS make your wakeup and sleep methods private!
*
* Note that this approach is better than the system's __sleep() and __wakeup() approach because it can handle private properties (thanks, Traits!!)
*
* Because of https://bugs.php.net/bug.php?id=65591 we explicitly handle core object references ourselves rather than let the buggy serializer mess up object references.
*
* All original code.
* @package Synthesis/Base
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2004-2014 Lifetoward LLC
* @license proprietary
*/
trait SerializationHelper
{
	public function serialize( )
	{
		$propList = self::$savedProperties; // we must do this to have our own private iteration pointer
		if (is_array(reset($propList)))
			list($wakeup, $sleep) = array_shift($propList);
		if (is_string($sleep) && is_callable(array($this, $sleep)))
			$this->$sleep();
		if (get_parent_class(__CLASS__))
			$parent = parent::serialize();
		foreach ($propList as $prop)
			$props[$prop] = $this->$prop;
		array_walk_recursive($props, 'preserializeObjectRefs'); // This conveniently operates on our own properties first and all their array descendents too
		$z = mb_strlen($s = $parent . serialize($props));
		return __CLASS__.":$z:$s";
	}

	public function unserialize( $rep )
	{
		$propList = self::$savedProperties; // we must do this to retain our iteration pointer
		if (is_array(reset($propList)))
			list($wakeup, $sleep) = array_shift($propList);
		$s = pullSerialString($rep, __CLASS__); // s now contains the concatenation of my parent and my properties.
		if (get_parent_class(__CLASS__))
			parent::unserialize($s); // unwrapping from string allows us to parse it off
		pullSerialString($s); // knocks the parent class off the front
		$props = unserialize($s);
		array_walk_recursive($props, 'renewObjects'); // This conveniently operates on our own properties first and all their array descendents too
		foreach ($propList as $prop)
			$this->$prop = $props[$prop];
		is_string($wakeup) && is_callable(array($this, $wakeup)) && $this->$wakeup();
	}
}
