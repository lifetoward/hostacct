<?php
/**
* e_paytax
* Payroll tax is a particular tax calculation and purpose which is applied to employee pay.
* It contains a tax table (set of e_taxtable records) which defines the rates taxed for various bands of the amount.
* For income taxes, the bands are prorated and the single and married filing classes indicated different paytax instances.
* For most other taxes, the bands are annual and there aren't separate filing classes, so there's just one of these instances per tax.
* Examples of payroll taxes:
*	2014 TX Unemployment tax
*	2014 US Federal Income tax (married)
*	2014 US Social Security tax
*	2014 US Medicare tax
*	2014 US Unemployment tax
*
* Created: 11/30/14 for Lifetoward LLC
*
* All original code.
* @package Synthesis/Payroll
* @author Biz Wiz <bizwiz@SyntheticWebApps.com>
* @copyright (c) 2014 Lifetoward LLC; All rights reserved.
* @license proprietary
*/
class e_paytax extends Container
{
	// SYSTEM-DEFINED PARAMETERS INCLUDE:
	// PAYPERIOD (an index into the $PayPeriods array)
	// First Pay Date (initializes the pay periods)
	// Payroll Funding Account (from which paychecks are funded and the tax liability escrow is sourced)

	protected static $containDef = ['fname'=>'bands', 'class'=>'e_taxtable', 'refBy'=>'paytax'];

	public static $table = "pay_tax", $singular = "Payroll tax", $plural = "Payroll taxes", $descriptive = "Payroll tax",
		$help = "Each of these records defines a particular tax calculation and purpose which is applied to employee pay.",
		$fielddefs = array(
			 'taxyear'=>array('name'=>'taxyear','class'=>'t_string','pattern'=>'20[1-9][0-9]','label'=>"Tax year",'identifying'=>true,'sort'=>'DESC')
			,'label'=>array('name'=>'label','class'=>'t_string','label'=>"Payroll tax",'identifying'=>true,'sort'=>true
				,'help'=>"Choose a name for this payroll tax which is consistent across many years and all its filing statuses but otherwise unique.")
			,'taxadmin'=>array('name'=>'taxadmin','class'=>'e_taxadmin','label'=>"Administrative scheme",'sort'=>true,'type'=>'belong','identifying'=>true,
				'help'=>"The administrative scheme for a payroll tax specifies the reporting and payment schedules, identifies the taxing authority, forms, and methods for complying.")
			,'method'=>array('name'=>'method','class'=>'t_select','label'=>"Tax calculation method",'required'=>true,
				'options'=>array('StandardYTD'=>"Standard year-to-date",'ProratedIncomeTax'=>"Prorated income tax"),
				'help'=>"Designates the method used for interpreting the bands and calculating the tax. Generally state and federal income taxes are prorated while others are annual YTD.")
			,'w4allow'=>array('name'=>'w4allow','class'=>'t_dollars','label'=>"Annualized W-4 Allowance",'required'=>true,'initial'=>0,
				'help'=>"If an allowance amount is specified here, then a proration of this annualized amount, multiplied by the number of W-4 allowances, will be deducted from the taxable amount before calculating the tax.")
			,'expacct'=>array('name'=>'expacct','class'=>'Account','label'=>"Expense account",'type'=>'belong',
				'help'=>"Choose the expense account which will be debited as this tax is charged to the business.")
			,'liability'=>array('name'=>'liability','class'=>'Account','label'=>"Tax liability account",'type'=>'belong',
				'help'=>"Choose the liability account which accumulates taxes due to the appropriate tax authority.")
			,'escrow'=>array('name'=>'escrow','class'=>'Account','label'=>"Internal escrow account",'type'=>'refer',
				'help'=>"The escrow account is where funds for unpaid taxes can be accumulated before payment to ensure the liability can be paid. Ideally funding can occur from this account.")
		), $hints = array( // hints are provided for general purpose actions to be able to customize rendering of this particular element class
			 '*all'=>array()
			,'a_Browse'=>array(
				 'include'=>array('taxyear','taxauth','label')
				,'triggers'=>array('banner'=>"create", 'row'=>array('update','display'), 'multi'=>"delete") )
			,'a_Edit'=>array('triggers'=>array('banner'=>'delete'))
			,'a_Display'=>array('title'=>'', 'subtitle'=>'', 'headdesc'=>'', 'tiles'=>array(
//				 array('method'=>'fields','title'=>"",'fields'=>array('example'))
//				,array('method'=>'relations','title'=>"",'class'=>'')
//				,array('method'=>'relatives','title'=>"",'class'=>'')
				))
		), $operations = array( // actions allow general purpose actions to know how to interact with this element class in various situations
			 'list'=>array('action'=>'a_Browse', 'role'=>'Staff')
			,'display'=>array('action'=>'a_Display', 'role'=>'Staff')
			,'update'=>array('action'=>'a_Edit') // only a *super can change a tax definition
			,'delete'=>array('action'=>'a_Delete') // only a *super can change a tax definition
			,'create'=>array('action'=>'a_Edit', 'role'=>'*system')
		), $PayPeriods = array(
			// These data are for calculating prorated tax bands for a given pay period.
			// The tax bands contain annualized amounts, so to determine the appropriate band floors for prorated pay periods we must:
			//   Divide the annualized number by the divisor for the pay period, rounding off the result to the number of decimal places in capdecplc.
			//   These prorated bands then are compared against the taxable income after withholding allowances are deducted.
			 'annual'=>array('label'=>"Annual",'divisor'=>1,'capdecplc'=>0)
			,'semiann'=>array('label'=>"Semi-annual",'divisor'=>2,'capdecplc'=>0)
			,'quarter'=>array('label'=>"Quarterly",'divisor'=>4,'capdecplc'=>0)
			,'monthly'=>array('label'=>"Monthly",'divisor'=>12,'capdecplc'=>0)
			,'semimon'=>array('label'=>"Semi-monthly",'divisor'=>24,'capdecplc'=>0)
			,'biweekly'=>array('label'=>"Bi-weekly",'divisor'=>26,'capdecplc'=>0)
			,'weekly'=>array('label'=>"Weekly",'divisor'=>52,'capdecplc'=>0)
			,'daily'=>array('label'=>"Daily or other",'divisor'=>260,'capdecplc'=>1)
		);

	protected function calculateStandardYTD( e_employee $employee, $paydate, $taxable )
	{
		$period = static::$PayPeriods[$employee->payperiod];
		// Prep: load the band data in lowest-highest sequence. We will calculate the tax for each band in sequence until the taxable amount is depleted
		$bands = e_taxtable::collection($this);
		list($index, $band) = each($bands);
		// Prep: reduce the taxable amount by the W-4 allowances
		$taxable -= $employee->w4allows * $this->w4allow / round($period['divisor'], $period['capdecplc']);
		// Execution: accumulate the tax as a series of sub taxes drawn from each band.
		$tax = 0;
		while ($taxable > 0) {
			$eRate = $band['employee']
			$tax +=
			$band = each($bands);
			$taxable -=
			$rMsg .= "$portion @ $rRate = $subtax; ";
			$eMsg .= "$portion @ $eRate = $subtax; ";
		}
		return compact('rTax','eTax','tax','rMsg','eMsg');
	}

	protected function calculateProratedIncomeTax( e_employee $employee, $paydate, $taxable )
	{
	}

	/**
	* @return array Returns the calculated tax in a data structure including these elements:
	*	rTax = The employer's tax in dollars.
	*	eTax = The employee's tax in dollars (ie. deducted from paycheck).
	*	tax = The total tax due from employer to authority
	*	rMsg = A textual description of the calculation of the employer's tax for confirmation purposes.
	*	eMsg = A textual description of the calculation of the employee's tax for confirmation purposes.
	*/
	public function calculatePayrollTax( $taxable, $paydate, e_employee $employee )
	{
		return call_user_func([$this, "calculate$this->method"], $employee, $paydate, $taxable);
	}

	public function formatted()
	{
		return "$this->taxyear $this->°taxauth $this->°label";
	}
}
