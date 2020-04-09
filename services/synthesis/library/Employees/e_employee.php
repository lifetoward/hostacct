<?php

class e_employee extends Element
	implements Messagee
{
	public static $table = 'employee'	,$singular = 'Employee' ,$plural = 'Employees' ,$descriptive = 'Employee info' ,
		$formatSQL = "(SELECT CONCAT(nickname,' ',surname) FROM person WHERE {}.person=person._id)",
		$fielddefs = array(
			 'person'=>array('name'=>'person', 'label'=>'Personal data', 'type'=>'include', 'reference'=>'contact:element:person', 'class'=>'e_person', 'identifying'=>true, 'sort'=>true, 'help'=>'The employee as a person')
			,'status'=>array('name'=>'status', 'label'=>'Status', 'class'=>'t_select', 'notnull'=>true, 'sort'=>'ASC', 'help'=>"Only active employees can earn compensation",
				'options'=>array('active'=>'Active', 'inactive'=>'Inactive', 'separated'=>'Separated'))
			,'phone'=>array('name'=>'phone', 'label'=>'Company phone', 'class'=>'t_telecom',
				'help'=>"If the employee has a business phone line, use that number here. Otherwise use the most reliable personal phone number available, mobile preferred. This number will be available to all employees and possibly external contacts.")
			,'phone2'=>array('name'=>'phone2', 'label'=>'Personal phone', 'class'=>'t_telecom', 'help'=>"Use this number when the employee cannot be reached at the Primary phone number. This information is only visible to the employee, managers, and coordinators.")
			,'email'=>array('name'=>'email', 'label'=>'Company email', 'class'=>'t_email', 'help'=>"Use the employee's business email address here. All employees can view this email address and it could be used in external correspondence initiated by the system.")
			,'pemail'=>array('name'=>'pemail', 'label'=>'Personal email', 'class'=>'t_email', 'required'=>true, 'help'=>"Employee's preferred personal email address. This information is only visible to the employee, managers, and coordinators.")
			,'locaddr'=>array('name'=>'locaddr', 'label'=>'Home address', 'class'=>'t_address', 'type'=>'contact:address', 'required'=>true,
				'help'=>"A current and correct geographic home address is required for tax purposes and for determining distance to travel. This information is only visible to the employee, managers, and coordinators.")
			,'mailaddr'=>array('name'=>'mailaddr', 'label'=>'Postal address', 'class'=>'t_address', 'type'=>'contact:address', 'required'=>true,
				'help'=>"Unlike the Home address, the mailing address may be a PO box, temporary, or other postal address where the employee prefers to receive official mail. This information is only visible to the employee, managers, and coordinators.")
			,'hourly'=>array('name'=>'hourly', 'label'=>'Hourly wage', 'class'=>'t_cents', 'help'=>"Gross hourly wage to be used in all except &quot;alternative minimum&quot; situations. If the employee does not do hourly work, leave this field blank or 0.00.")
			,'salary'=>array('name'=>'salary', 'label'=>'Salary', 'class'=>'t_cents', 'help'=>"Amount to be paid PER PAY PERIOD. If the employee does not earn by salary, leave this field blank or 0.00.")
			,'newcomm'=>array('name'=>'newcomm', 'label'=>'New sales commission rate', 'class'=>'t_float', 'range'=>'0-100',
				'help'=>"Percentage or cents per dollar to be paid as commissions for NEW sales receipts.")
			,'recomm'=>array('name'=>'recomm', 'label'=>'Renewals commission rate', 'class'=>'t_float', 'range'=>'0-100',
				'help'=>"Percentage or cents per dollar to be paid as commissions for renewal sales receipts.")
			,'ownshare'=>array('name'=>'ownshare', 'label'=>'% Ownership', 'class'=>'t_float', 'range'=>'0-100',
				'help'=>"Shareholders of the corporation should have this field set to the percentage of all shares they own. Up to 4 decimal places on the percentage will be honored. This number should range from 0 - 100.")
			,'roles'=>array('name'=>'roles', 'label'=>'Org roles', 'class'=>'t_boolset', 'notnull'=>true,
				'options'=>array('owner'=>'Owner','manager'=>'Manager','technician'=>'Screening technician','coach'=>'Coach','screenlead'=>"Screening leader",'sales'=>"Sales",'coordinator'=>"Operations coordinator"))
			,'notes'=>array('name'=>'notes', 'label'=>'Employment notes', 'class'=>'t_richtext',
				'help'=>"This field is private to the employee and management, is only editable by management, and may include terms of employment, documentation of problems, etc.")
			);

	private $roleset;

	public function __get( $name )
	{
		if ($name == 'roleset') {
			if (!isset($this->roleset))
				$this->roleset = array_fill_keys(explode(',', $this->roles), true);
			return $this->roleset;
		}
		return parent::__get($name);
	}

	public function formatted()
	{
		return ($named = "$this->°nickname $this->°surname") == ' ' ? 'Employee' : $named;
	}

	public static function render_type( $plural = false )
	{
		return 'Employee'. ($plural ? 's' : null);
	}

	private static // field authorization exclusion lists... see comments just below
		 $adminOnly = array('ownshare','entity','name','login')
		,$noSelfMod = array('hourly','salary','newcomm','recomm','notes','roles','status','email')
		,$anyoneSee = array('nickname','surname','commonname','comments','phone','email')
		,$coordView = array('pemail','locaddr','phone2','status','roles')
		,$nonInput = array('person','commonname','fullname','entity') // ie. derived or system-set
		,$tooBig = array('passhint','comments','keywords','notes') // won't fit in inline or columnar contexts
		,$fullBrowse = array('surname','nickname','phone','phone2','email','locaddr','status','roles')
		,$relativeBrowse = array('commonname','phone','phone2','email')
		;
		/*
		An administrator can see and update anything. If you're not an administrator you cannot see or update the fields in adminOnly.
		A manager can both see and update everything remaining.
		If you're the owner of the record, you can see everything remaining, however you cannot update the fields in noSelfMod.
		No one else has any update authority, and (with exception below) can only see fields in anyoneSee.
		If you're a coordinator, you can additionally see fields in coordView, but no update privileges are added.
		*/

	// returns 0 for no access, 1 for readable only, 2 for read and updatable
	public function authFieldAccess( $fname, Context $c )
	{
		if (!is_array($this->{¶.$fname}))
			return 0;
		if ($c->_roles['admin'])
			return 2;
		if (in_array($fname, self::$adminOnly))
			return 0;
		if ($c->_employee->roleset['manager'])
			return 2;
		if ($c->_employee->_handle == $this->_handle)
			return in_array($fname, self::$noSelfMod) ? 1 : 2;
		return in_array($fname, array_merge(self::$anyoneSee, ($c->_employee->roleset['coordinator'] ? self::$coordView : array()))) ? 1 : 0;
	}

	public function fielddefs( Context $c = null, array $exclude = array(), array $include = array() )
	{
		if (!$c && !$c->mode)
			return parent::fielddefs();

		if ($c->mode == $c::INPUT) // various fields are simply not editable
			$exclude = array_merge((array)$exclude, self::$nonInput);

		// Exclude fields which are not well-rendered in small areas
		if ($c->mode == $c::INLINE || $c->mode == $c::COLUMNAR)
			$exclude = array_merge((array)$exclude, self::$tooBig);

		// Exclusions based on authorization
		if (!$c->_roles['admin']) {
			$exclude = array_merge((array)$exclude, self::$adminOnly);
			if (!$c->_employee->roleset['manager']) {
				if (($c::INPUT == $c->mode) && $this && $c->_employee->_handle == $this->_handle)
					$exclude = array_merge($exclude, self::$noSelfMod);
				else { // for all but input situations for a non-manager
					$myincludes = array_merge(self::$anyoneSee, $c->_employee->roleset['coordinator'] ? self::$coordView : array());
					$include = $include ? array_intersect($include, $myincludes) : $myincludes;
				}
			}
		}

		// Here we further constrain the max include list depending on the action we're in
		if ($c->_action == 'a_Browse')
			$include = $include ? array_intersect($include, self::$fullBrowse) : self::$fullBrowse;
		else if ($c->_action == 'base:action:display')
			if ($c->_subaction == 'relative_records_table')
				$include = $include ? array_intersect($include, self::$relativeBrowse) : self::$relativeBrowse;

		return parent::fielddefs($c, $exclude, $include);
	}

	public function renderField( $fname, Context $c, $format = null )
	{
		// You must be a manager or system admin OR you must be logged in as the employee we're looking at to see the private fields.
		$auth = $this->authFieldAccess($fname, $c);
		if (!$auth)
			return "[ not authorized ]";
		return parent::renderField($fname, $c, ($auth < 2 && $c->mode == $c::INPUT) ? '*readonly' : $format);
	}

	public static function get_triggers( Context $c, $handle = null )
	{
		if ($c->_action == 'a_Browse' && $handle == 'banner')
			return $c->_employee->roleset['manager'] || $c->_roles['admin'] ? array('a_Edit'=>array('icon'=>'new.png', 'label'=>'Add employee', 'args'=>array('focus'=>'e_employee','_action'=>'a_Edit'))) :  array();
		else if ($c->_action == 'a_Browse' && $handle == 'record')
			return array('a_Edit'=>array('icon'=>'edit.png', 'label'=>'Update info', 'args'=>array('focus'=>'e_employee','_action'=>'a_Edit'), 'dynarg'=>'id'));
		return array();
	}

	public function message_notifier( $message = null )
	{
		if ($this->email) {
			if (($message instanceof Element && $message->_type = 'messages:element:message') || $message instanceof e_message)
				$from = " from $message->author";
			if (sendEmail($this->email, "You have a new message{$from} awaiting your attention!", <<<html
<html><body>
<p>Hi $this->»nickname,</p>
<p>You've just received a new message $from.</p><p>To see a list of all new messages waiting for you, log in to the
<a href="$GLOBALS[urlbase]/coach.php" target="_blank">Coaching Portal</a> or <a href="$GLOBALS[urlbase]/manage.php" target="_blank">Manager's Portal</a>
and then view the coaching Dashboard to see a list of messages waiting for you.</p>
<p><br/>Sent by the InnerActive Wellness online system</p>
</body></html>
html
					))
				return 1;
		}
		return 0;
	}

}
