<?php

class a_typetester extends Frame
{
	private $obj;

	public static function start( $class = null )
	{
		$c = OpenSession::start();
		$a = $c->_main ? $c->_main : new a_typetester($c, $class);
		$a->render($c);
		$c->setHTTPHeaders();
		$a->outputDocument();
	}

	public function __construct( Context $c = null, $class = 'e_basetypes' )
	{
		$this->obj = $class::create();
	}

	public function render( Context $c )
	{
		list($args, $post) = $c->request;
		logDebug(array("ARGS"=>$args));
		logDebug(array("POSTED"=>$post));
		if ($post['submit']=='reset') {
			$class = get_class($this->obj);
			$this->obj = $class::create();
			logDebug(array("Re-Initialized Object"=>$this->obj->_));
		} else
			$this->obj->acceptValues((array)$post);

		$R = new HTMLRendering($c);
		foreach ($this->obj->_fields as $fn) {
			$label = $this->obj->getFieldLabel($fn);
			$inline = $this->obj->renderField($fn, $R, static::INLINE);
			$verbose = $this->obj->renderField($fn, $R, static::VERBOSE);
			$columnar = $this->obj->renderField($fn, $R, static::COLUMNAR);
			$input = $this->obj->renderField($fn, $R, static::INPUT);
			$R->idprefix = 'ro_';
			$readonly = $this->obj->renderField($fn, $R, '*readonly');
			unset($R->idprefix);
			$content .= <<<html
<div id="$fd[name]" class="row">
	<div class="col-lg-12"><h3 style="padding-left:25%">$label</h3></div>
	<div class="col-lg-6"><h4>Output formats</h4><table>
		<tr><th>inline</th><td>$inline</td></tr>
		<tr><th>verbose</th><td>$verbose</td></tr>
		<tr><th>columnar</th><td>$columnar</td></tr>
		<tr><th>numeric</th><td>{$this->obj->{•.$fn}}</td></tr>
	</table></div>
	<div class="col-lg-6">
		<fieldset><legend>input</legend><table>
			<tr><td align="right"><label for="$fn">input:</label></td><td>$input</td></tr>
			<tr><td align="right"><label for="$fn">readonly:</label></td><td>$readonly</td></tr>
		</table></fieldset>
	</div>
</div>
html;
		}
		$formTarget = $R->target(array(), true);
		$R->addReadyScript("$('form').on('submit',function(e){alert('Submitting and value of date1 control is '+\$('#date1').val());if(e.isDefaultPrevented())alert('Please supply appropriate values for all input items.');});", "standard form validation");
		$R->context->http_headers();
		return $R->prep($R::PROCEED, <<<html
<body>
<div class="container">
<div id="_header" class="row"><h1>Base types</h1></div>
<form action="$formTarget" method="post">
$content
<p style="padding-left:25%"><input type="submit" name="submit" value="Update"/>&nbsp;<button type="submit" value="reset" name="submit">Reset object</button></p>
</form>
</div>
</body></html>
html
			);
	}
}
