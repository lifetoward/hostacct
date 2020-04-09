<?php
/**
*
* All original code.
* @package Synthesis/Finance
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
class BankStatement extends Element
{
	public static $table = 'bank_statement',  'singular'=>"Bank statement", 'plural'=>"Bank statements", 'descriptive'=>"Statement info";

	public static $fielddefs = array(
		,'bankacct'=>array('name'=>"acct", 'type'=>"require", 'class'=>"BankAccount", 'identifying'=>true, 'readonly'=>true)
		,'dtstart'=>array('name'=>"dtstart", 'class'=>"t_date", 'format'=>"d M Y", 'comment'=>"This date is important. It must be immediately after the preceding statement.", 'readonly'=>true)
		,'dtend'=>array('name'=>"dtend", 'class'=>"t_date", 'format'=>"d M Y", 'comment'=>"This date is important. It represents the extent of coverage for this statement.", 'readonly'=>true)
		,'balamt'=>array('name'=>"balamt", 'class'=>"t_balance", 'comment'=>"This information is not used because it is not as of DTEND but rather DTASOF.", 'readonly'=>true)
		,'dtasof'=>array('name'=>"dtasof", 'class'=>"t_date", 'format'=>"d M Y", 'comment'=>"This information is not used.", 'readonly'=>true)
		,'status'=>array('name'=>"status", 'class'=>"t_boolean", 'readonly'=>true, 'format'=>'Open|Reconciled')
			// We mark it closed when all its entries have been associated with local journal entries and the statement is reconciled.
		);

}
