<?php
/**
* To make the envelope-PDF rendering functionality more useful in various settings, we've here implemented it as an extension of the FPDF class with some handy static methods.
* YOU MUST SET $GLOBALS['sysroot'] TO MAKE LOADING THE FPDF LIBRARY POSSIBLE. This class only needs that one other file to work, so as long as you provide
* sysroot, you're all set. That's our only dependency, so you can use this class from the command line quite easily.
*
* Makes essential use of the FPDF package by license. See
* All original code.
* @copyright © Lifetoward LLC 2015
*/
require_once("$sysroot/lib/contrib/FPDF/fpdf.php");
define('FPDF_FONTPATH',"$sysroot/lib/contrib/FPDF/font");

class EnvelopePDF extends FPDF
{
	protected static $envelopeSpecs = array(
		 '#10'=>array('width'=>684, 'height'=>297, 'indent'=>216, 'scroll'=>140)
		,'holiday'=>array('width'=>586, 'height'=>436, 'indent'=>216, 'scroll'=>234)
	), $envelopeStandards = array('fontsize' => 12, 'marginLeft' => 48, 'marginTop' => 20, 'lineheight' => 16, 'font' => 'Helvetica');
	
	/**
	* If for some reason you want to know the specs for a particular envelope, you can obtain it here. 
	* Perhaps one reason to do this is to extend one to create your own.
	* @param string $envType The name of a type of envelope for which you'd like the specs.
	* @param mixed[] Returns an array with all the data which specifies the envelope you selected.
	*/
	public static function getEnvelopeSpec( $envType = '#10' )
	{
		if (!is_array(static::$envelopeSpecs[$envType]))
			$envType = '#10';
		return array_merge(static::$envelopeSpecs[$envType], static::$envelopeStandards);
	}

	/**
	* This class carries specifications for a few envelope types out of the box. Call this method to learn the handles of them.
	* @return string[] Returns an array of strings, each of which is the name or handle of an envelope specification. The names should be self-describing.
	*/
	public static function listEnvelopeOptions( )
	{
		return array_keys(static::$envelopeSpecs);
	}

	/**
	* Call this method to add (or update) a specific envelope dimensional specification to the static specs so you can use it to render a kind of envelope we don't already have defined.
	* As static specs, such updates are only good for the runtime of the script.
	* The specs are applied when you create the PDF object, ie. in render() or 
	* @param string $name This string uniquely identifies the envelope specification among any defined for this class. Any valid array index string will do.
	*	If you supply the name of a specification we already had defined, your new specification will overwrite the original. 
	* An envelope specification requires 4 values specified in points (1/72nd inch):
	* @param integer $width Width of the envelope in points from left to right when held in readable upright orientation.
	* @param integer $height Height of the envelope in points from top to bottom when held in readable upright orientation.
	* @param integer $indent Left-to-right displacement of the TO address from the left edge of the envelope.
	* @param integer $scroll Top-down displacement of the TO address from the top edge of the envelope to the BASELINE of the first line of the address (addressee's name).
	*/
	public static function specifyEnvelope( $name, $width, $height, $indent, $scroll )
	{
		static::$envelopeSpecs[$name] = compact('width','height','indent','scroll');
	}
	
	/**
	* Call this to obtain the content of a PDF file with one page per envelope addressed to the addresses provided.
	* @param string|string[] $addresses A list of plaintext string-formatted addresses; they should include the addressee and be newline-separated. 
	*	We do accept a single string for the one-banger case.
	* @param string $fromAddress A string representing a completely assembled return address formatted for simple text output (newline-separated, with addressee)
	* @param string $envelope An envelope selector. Recognized envelope specifications are in f_postaddr::$envelopeSpecs
	* @param boolean $asText Specify how you'd like the resulting PDF returned. If you pass true, you'll get the PDF in its canonical text format and no object will be retained.
	*		Otherwise, you'll get an EnvelopePDF object which can be retained and exported using the methods provided below.
	* @return FPDF An object with methods to produce the PDF output. Because those method parms are obscure, we provide static methods in EnvelopePDF which are easier to understand (see below).
	*/
	public static function render( $addresses, $fromAddress = null, $envelope = '#10', $asText = false )
	{
		$pdf = new EnvelopePDF($envelope);
		
		foreach ((array)$addresses as $address)
			$pdf->addEnvelopePage($address, $fromAddress);

		$pdf->Close();
		
		return $asText ? $pdf->Output(null, 'S') : $pdf;
	}

	protected $spec = '#10';
	
	/**
	* Pass the envelope specification you want to use to the constructor.
	* @param string $env The name of a defined specification for an envelope's dimensions. If you don't pass one, the #10 spec will be used.
	* @return EnvelopePDF An "open" PDF object ready to have pages added to it.
	*/
	public function __construct( $env = '#10' )
	{
		if ($env && array_key_exists($env, static::$envelopeSpecs))
			$this->spec = $env;
		extract(static::getEnvelopeSpec($this->spec));
		parent::__construct($width > $height ? 'L' : 'P', "pt", array($width, $height));
		$this->SetFont($font,'',$fontsize);
	}
	
	/**
	* Call this to render one envelope as one page of a PDF.
	* The page may be added to an open PDF object. 
	* @param string $recipient Simple text with newlines including the addressee (target or label) and address where the envelope is headed
	* @param string $sender (optional) An address, with addressee line, in simple text, newline separated, for the "return" address. If null, not return address will be included.
	* @return void
	*/
	public function addEnvelopePage( $recipient, $sender = null )
	{
		extract(static::getEnvelopeSpec($this->spec));
		$this->AddPage();

		// Return address rendered at the top left margin
		if ($sender)
			foreach (explode("\n", $sender) as $x => $line)
				$this->Text($marginLeft, $marginTop+$fontsize+$lineheight*$x, $line);
		
		// Recipient address rendered at the indent and scroll
		foreach (explode("\n", $recipient) as $x => $line)
			$this->Text($indent, $scroll+$fontsize+$lineheight*$x, $line);
	}

	/**
	* Export PDF to file
	* @param string $filename (optional) Provide the name of the file as it should be saved to the script's host's filespace. If omitted, use "envelopes.pdf"
	* @return void
	*/
	public function toFile( $filename = "envelopes.pdf" )
	{
		$this->Output($filename, 'F');
	}

	/**
	* Export PDF as attachment with output to browser
	* @param string $saveas (optional) Filename to propose for saving the PDF to the client's filespace. envelopes.pdf if omitted.
	* @param boolean $forceDownload (optional) Normally false, but if true, don't allow direct opening of the attachment by the browser.
	* @return void
	*/
	public function asAttachment( $saveas = "envelopes.pdf", $forceDownload = false )
	{
		$this->Output($saveas, $forceDownload ? 'D' : 'I');
	}

	/**
	* Export PDF as a text string. A PDF document is a text document. This gives you that text in its entirety.
	* @return void
	*/
	public static function asText( )
	{
		return $this->Output(null, 'S');
	}
}
