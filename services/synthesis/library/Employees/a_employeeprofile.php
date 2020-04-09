<?php

class a_EmployeeProfile extends Action
{
	public $instance; // the employee record

	public function serialize( )
	{
		return serialize(array(parent::serialize(), $this->instance));
	}
	public function unserialize( $rep )
	{
		list($parent, $this->instance) = unserialize($rep);
		parent::unserialize($parent);
	}

	public function __construct( Context $c, e_employee $instance )
	{
		parent::__construct($c);
		if (!($c->_portal == 'manage' && $c->_employee->roleset['manager']) && $instance->_handle != $c->_employee->_handle)
			return logError("AUTHORIZATION FAILURE: Must be a manager in the manager portal or the employee hirself to view an employee's profile.");
		// Note that we will use the context's registered _employee attribute often to determine authorization
		$this->instance = $instance;
	}

	public function render_me( $notice = null )
	{
		// Note that we will use the context's registered _employee attribute often to determine authorization
		$this->addStyles(<<<css
div#head { width:70%; margin:auto; }
div#profile { width:98%; margin:auto; text-align:center; vertical-align:top; }
div#profile table { width:auto; margin:auto; font-size:120%; display:inline-block; text-align:left; }
div#profile table td, div#profile table th { padding:0 3em; }
div#profile table td { color:blue; }
div#profile table th { text-align:left; padding-top:1em; }
div#profile table div.value { text-align:left; padding:1em; border:1px solid gray; margin:0; font-size:80%; min-width:40em; }
div#profile table div.value p { padding:0; margin:0; }
css
			, 'EmployeeProfile');
		$c->mode = static::VERBOSE;
		$notes = $this->instance->»notes;
		$comments = $this->instance->»comments;
		$c->mode = static::COLUMNAR;
		$updateTarget = htmlentities($c->targetOperation($this->instance, 'update'));
		foreach ($ratefields = array('hourly','salary','newcomm','recomm') as $fn)
			if ($this->instance->$fn)
				$payrates[] = array($fn, "{$this->instance->{¬.$fn}}", "{$this->instance->{».$fn}}");
		if ($num = count($payrates)) { // assignment intended
			if ($num == 1) {
				list($fn, $label, $value) = array_shift($payrates);
				$payratesOut = "<tr><th>$label</th></tr><tr><td>$value</td></tr>";
			} else if ($num == 2) {
				$payratesOut = "<tr><th>{$payrates[0][1]}</th><th>{$payrates[1][1]}</th></tr><tr><td>{$payrates[0][2]}</td><td>{$payrates[1][2]}</td></tr>";
			} else if ($num == 3) {
				if (mb_strstr($payrates[1][0], 'comm')) { // we've got 2 commissions and one other rate
					$payratesOut = "<tr><th colspan=\"2\">{$payrates[0][1]}</th></tr><tr><td colspan=\"2\">{$payrates[0][2]}</td></tr>\n".
						"<tr><th>{$payrates[1][1]}</th><th>{$payrates[2][1]}</th></tr><tr><td>{$payrates[1][2]}</td><td>{$payrates[2][2]}</td></tr>";
				} else {
					$payratesOut = "<tr><th>{$payrates[0][1]}</th><th>{$payrates[1][1]}</th></tr><tr><td>{$payrates[0][2]}</td><td>{$payrates[1][2]}</td></tr>\n".
						"<tr><th colspan=\"2\">{$payrates[2][1]}</th></tr><tr><td colspan=\"2\">{$payrates[2][2]}</td></tr>";
				}
			} else if ($num == 4) {
					$payratesOut = "<tr><th>{$payrates[0][1]}</th><th>{$payrates[1][1]}</th></tr><tr><td>{$payrates[0][2]}</td><td>{$payrates[1][2]}</td></tr>\n".
						"<tr><th>{$payrates[2][1]}</th><th>{$payrates[3][1]}</th></tr><tr><td>{$payrates[2][2]}</td><td>{$payrates[3][2]}</td></tr>";
			}
		}
		$this->rendered = <<<html
<div id="head">
<button style="float:right" onclick="location.replace('$updateTarget')">Update info</button>
<h1>Employee profile</h1>
<p>It's important to keep your identifying and contact information up to date. You can review the information we have below and if you need to update it, click the <i>Update info</i> button.</p>
</div><div id="profile">
<table>
<tr><th colspan="2" style="text-align:center">Personal and contact information</th></tr>
<tr><th>First name</th><th>Surname</th></tr>
<tr><td>{$this->instance->»nickname}</td><td>{$this->instance->»surname}</td></tr>
<tr><th>Company email address</th><th>Personal email address</th></tr>
<tr><td>{$this->instance->»email}</td><td>{$this->instance->»pemail}</td></tr>
<tr><th>Primary phone number</th><th>Secondary phone number</th></tr>
<tr><td>{$this->instance->»phone}</td><td>{$this->instance->»phone2}</td></tr>
<tr><th>Home address</th><th>Mailing address</th></tr>
<tr><td>{$this->instance->»locaddr}</td><td>{$this->instance->»mailaddr}</td></tr>
<tr><th colspan="2">Personal profile notes</th></tr>
<tr><td colspan="2">$comments</td></tr>
</table><table>
<tr><th colspan="2" style="text-align:center">Company roles</th></tr>
<tr><td colspan="2" style="text-align:center">{$this->instance->»roles}</td></tr>
$payratesOut
<tr><th colspan="2">Management notes</th></tr>
<tr><td colspan="2">$notes</td></tr>
</table>
</div>
html;
		return $this->getResult(static::PROCEED);
	}

}
