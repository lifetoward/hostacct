<?php
/**
* Provides a form-based authentication action.
*
* All original code.
* @package Synthesis/Authentication
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2007-2014 Lifetoward LLC
* @license proprietary
*/
class a_Login extends Action
{
	private $login; // the login element we're hoping to find/authenticate

	/**
	* This method is to be called by a login handler and produces a completed login frame of the simplest variety.
	* It does not return. Static start methods in actions are intended to bootstrap an action from a handler.
	* They create their own root context and then set themselves us to run with it.
	*/
	public static function start( )
	{
		$root = SecureRequest::start();
		$a = new Frame($root, new a_Login($root)); // Both these actions content themselves with the root context as their own
		$R = $a->render($root);
		$root->setHTTPHeaders();
		$R->outputDocument();
	}

	public function __construct( Context $root )
	{
		$this->context = $root;
		$this->login = e_login::create(); // strawman, transient
	}

	public function render( Context $c )
	{
		$this->context = $c;
		extract($c->request);
		$R = new HTMLRendering($c);

		if (is_array($post))
			$this->login->acceptValues($post, $c);

		if (!($class = $args['class']))
			$class = 'AuthSession';
		if ($args['_'] == 'auth')
			new $class($c, $this->login); // Never returns... redirects either here (on failure) or to the target (on success)

		$formTarget = htmlentities($c->target(['_'=>'auth', 'target'=>$args['target'], 'class'=>$args['class']], true));

		$R->mode = $R::INPUT;
		foreach ($this->login->renderFields($R, array(), is_array($R->fieldlist) ? $R->fieldlist : ['username','password'], '*login') as $fn=>$rendered)
			$fieldsout .= <<<html
<div class="form-group">
	<label for="$R->idprefix$fn" style="font-size:larger">{$this->login->{¬.$fn}}</label>
$rendered
</div>
html;

		$R->addReadyScript("$('#pageform').on('submit',function(e){if(e.isDefaultPrevented())alert('Please correct or complete the marked fields, then try again.')})", "login form validation");
		$R->addReadyScript(<<<js
d=-1*new Date().getTimezoneOffset();h=(d/60).toFixed();m=d%60;$('#timezoneField').val((h>=0?'+':'')+h+':'+(m<10?'0':'')+m);
$('#languageField').val(navigator.language.replace('-','_'));
js
			);
		$R->linkStylesheet("$c->weblib/bootstrap/css/bootstrap.min.css");

		$R->content = ($args['msg'] ? "<p class=\"alert alert-info\">$args[msg]</p>\n" : null) . <<<html
<p class="static">To log in, enter a valid login name and password below, then click the "Log in" button.</p>
<div style="padding:0 2em">
<form action="$formTarget" method="post" id="loginForm" role="form">
<input type="hidden" name="language" id="languageField" value=""/>
<input type="hidden" name="timezone" id="timezoneField" value=""/>
<fieldset>
$fieldsout
</fieldset>
<button type="submit" name="submit" value="1" class="btn btn-primary">Log in</button>
</form></div>
<hr/>
<p><b>Remember:</b> You may consider information provided through this portal to be confidential. Keep these things in mind:</p>
<ul>
<li>Keep your password strong and private.</li>
<li>Log in only when you are in a position to keep your screen private.</li>
<li>Always be sure to log out when you are finished using the portal, especially on a shared machine.</li>
<li>Never allow the browser on a shared machine to remember your password.</li>
</ul>
html;
		return $R;
	}

}
