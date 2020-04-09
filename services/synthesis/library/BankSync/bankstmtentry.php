<?php
/**
*
* All original code.
* @package Synthesis/Finance
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
class BankStmtEntry extends Element
{
	public static $table = 'bank_stmtentry', $singular = "Bank statement entry", $plural = "Bank statement entries", 'descriptive' = "Statement entry details";

	public static $fielddefs = array(
		,'statement'=>array('name'=>"stmt", 'type'=>"belong", , 'unique'=>"1", 'sort'=>"ASC", 'readonly'=>true, 'notnull'=>true)
		,'fitid'=>array('name'=>"fitid", 'class'=>"t_string", 'unique'=>"1", 'readonly'=>true)
		,'trntype'=>array('name'=>"trntype", 'class'=>"t_string", 'readonly'=>true)
		,'dtposted'=>array('name'=>"dtposted", 'class'=>"t_date", 'sort'=>"ASC", 'format'=>"D m 'y", 'readonly'=>true, 'notnull'=>true)
		,'trnamt'=>array('name'=>"trnamt", 'class'=>"t_cents", 'readonly'=>true, 'notnull'=>true)
		,'memo'=>array('name'=>"memo", 'class'=>"t_string", 'readonly'=>true)
		,'localtype'=>array('name'=>"localtype", 'class'=>"t_boolean", 'readonly'=>true, 'format'=>"Credit|Debit")
		,'entry'=>array('name'=>"entry", 'type'=>"belong", 'reference'=>"accounting:element:acctentry", 'readonly'=>true)
		);
}
