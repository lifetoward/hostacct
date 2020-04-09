<?php
//namespace Lifetoward\Synthesis\_
/**
* A Notice is a class of Result which is intended to notify a user about what an Action has accomplished.
* An Action which returns one of these from a call to render() is indicating that the Action is complete, as it has nothing more to render.
* It is not meant to be primarily interactive and makes minimal presumptions of layout or interface. It render's as a "smallish" block-level element.
*	Even so, it may include links (ie. to help topics, results indications, etc.) and the ability to dismiss its own display.
* In a non-interactive context, we'd expect to output such a notice to a log or some such.
* Point is we designate a severity or notice level and render, format, or log accordingly.
* We also unburden the creator of these objects by accepting a few different forms of result information
* 	and assembling them appropriately on output.
*
* All original code.
* @package Synthesis
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2007-2015 Lifetoward LLC
* @license proprietary
*/
class Notice extends Result
{
	protected $textFormat = null, $reason = 'complete';
	protected static $textFormats = [ 1=>
		  'html' // HTML snippet... don't wrap your snippet in a block or anything, just provide the "inner" content.
		, 'plain' // Plain text. We will honor line breaks and tabs when rendering if we can.
		, 'json' // When we render JSON in HTML we will use list structures like DL and UL.
		];

	protected static $bootstrapClasses = [ 'default', 'primary', 'success', 'info', 'warning', 'danger' ];
	protected static $reasons = [
		  'abort'=>[ 'bsClass'=>'default', 'logLevel'=>null, 'label'=>"Operation abandoned." ] // User-initiated termination without intent to complete.
		, 'failure'=>[ 'bsClass'=>'warning', 'logLevel'=>'WARN', 'label'=>"Operation failed." ] // Unable to complete something based on the purpose. An action typically creates these to be seen as retrying.
		, 'success'=>[ 'bsClass'=>'success', 'logLevel'=>'INFO', 'label'=>"Operation successful!" ] // Some change has been made, usu. in the database.
		, 'error'=>[ 'bsClass'=>'danger', 'logLevel'=>'ERROR', 'label'=>"A system error occurred." ] // the problem is deeper than dealing with interactive consequences
		, 'complete'=>[ 'bsClass'=>'primary', 'logLevel'=>'INFO', 'label'=>"Operation completed." ] // i.e. without side effect
		];

	public static function renderArray( array $a )
	{
		$rendered = "<dl>\n";
		foreach ($a as $x=>$val)
			$rendered .= "<dt>". htmlentities($x) .": </dt><dd>".
				(is_array($val) ? static::renderArray($val) : htmlentities("$val")) ."</dd>\n"; // nest or stringify
		return "$rendered</dl>";
	}

	public function __toString()
	{
		return static::renderArray($this->render());
	}

	public function render()
	{
		$list = [];
		// We list out what we know about depth first
		if (count($this->subResults))
			foreach ($this->subResults as $sub)
				$list = array_merge($list, (array)$sub->render());

		if (($this->reason != 'complete' || !$this->content) && $this->reason != 'abort') { // ignore the no-op results

			if ($this->content instanceof Exception)
				$rendered = "<h2>A system error occurred. The operation did not complete. </h2>\n".
						($_ENV['PHASE'] != 'prod' ? HTMLLogger::renderException($this->content) :
							"<p>Please notify your system service provider for help; Provide them with this error code when you do: $GLOBALS[requestId]</p>\n");

			else if (is_array($this->content))
				$rendered = static::renderArray($this->content);

			else if (is_string($this->content))
				$rendered = "$this->content";

			// We need to make these little notice blocks a lot more interesting, with timed automatic dismissal for the less urgent, and manually-triggered dismissal for others.
			$list[] = '<div class="notice alert alert-'. static::$reasons[$this->reason]['bsClass'] .'">'.
					strip_tags($rendered, '<A><STRONG><EM><UL><OL><LI><DL><DD><DT><P><B><I>') .'</div>';
		}

		return $list;
	}

	public function format()
	{
		if (is_string($this->content) && $this->textFormat == 'html')
			$rendered = html_entity_decode(strip_tags(preg_replace('@(<[Bb][Rr]/?>|<[pP][^>]*>)@', "\n", "$this->content")));
		else
			$rendered = "$this->content";
		return [$rendered];
	}

	public function log( $rc = null )
	{
		$info = static::$reasons[$this->reason];
		if (is_object($this->content) || is_array($this->content))
			$item = $this->content;
		else
			$item = $this->format();
		logToAll($info['logLevel'], [ $info['label'], $item ], $rc);
	}

	/**
	* @param mixed $content Pass the message you'd like to convey. If it's text, let us know what kind. We'll work through an array using UL or DL formatting
	*		presuming Text values if an array is given. If you provide an object we'll stringify it presuming it's plain text. Other
	* @param int $textFormat If you pass a string as the message and it's not an HTML snippet, inform us what type of string it is and we can improve our rendering.
	*		Allowed values are the constants defined above.
	*/
	public function __construct( $content, $reason = 'complete', Result $nested = null, Instance $focus = null, $textFormat = 'plain' )
	{
		if ($nested)
			$this->subActions[] = $nested;
		$this->content = $content;
		if ($reason && array_key_exists($reason, static::$reasons))
			$this->reason = $reason;
		if (is_string($message) && array_key_exists($textFormat, static::$textFormats))
			$this->textFormat = $textFormat;
		$this->focus = $focus;
	}

	public function __get( $name )
	{
		if ($name == 'reason')
			return $this->reason;
		return parent::__get($name);
	}
}
