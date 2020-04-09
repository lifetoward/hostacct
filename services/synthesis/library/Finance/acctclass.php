<?php
/**
* AcctClass
* An Account Class establishes a particular journal account for certain purposes.
*
* Created: 3/21/15 for Lifetoward LLC
*
* All original code.
* @package Synthesis/Finance
* @author Biz Wiz <bizwiz@SyntheticWebApps.com>
* @copyright (c) 2015 Lifetoward LLC; All rights reserved.
* @license proprietary
*/
class AcctClass extends Element
{
	public static $table = "actg_class", $singular = "Account class", $plural = "Account classes", $descriptive = "Account class specification",
		$help = "An Account class establishes a particular journal account for certain purposes.",
		$fielddefs = [
			 'id'=>[ 'name'=>'id', 'class'=>'t_integer', 'sort'=>true, 'derived'=>'{}._id', 'label'=>"Identifier", 'range'=>'100:999999' ]
			,'name'=>[ 'name'=>'name', 'class'=>'t_string', 'label'=>"Class name", 'identifying'=>true, 'sort'=>true, 'width'=>6 ]
			,'class'=>[ 'name'=>'class','class'=>'t_select','label'=>"Major class", 'help'=>"Which of the six conventional accounting classes this class represents.",
				'options'=>[ 1=>'Asset','Liability','Equity','Income','Expense','Contra' ], 'width'=>4 ]
			,'positive'=>[ 'name'=>'positive','class'=>'t_boolean','label'=>"Positive type", 'format'=>"Credit|Debit", 'required'=>true, 'width'=>2,
				'help'=>"Which entry type counts as positive in balance calculations." ]
			,'credittag'=>[ 'name'=>'credittag','class'=>'t_class', 'label'=>"Credit entry tag class", 'base'=>'Element', 'width'=>6,
				'help'=>"Each credit journal entry can be associated with an Instance of this class." ]
			,'debittag'=>[ 'name'=>'debittag','class'=>'t_class', 'label'=>"Debit entry tag class", 'base'=>'Element', 'width'=>6,
				'help'=>"Each debit journal entry can be associated with an Instance of this class." ]
			,'flags'=>[ 'class'=>'t_boolset', 'name'=>'flags', 'label'=>"Relevant capabilities", 'initial'=>'inactive', 'width'=>12,
				'options'=>[ 'inactive'=>"Inactive", 'funding'=>"Funding", 'interest'=>"Interest bearing" ],
				'help'=>"Inactive means not available for creating new transactions. Funding means able disburse funds. Interest bearing means we earn or pay interest on balance." ]
			,'description'=>[ 'class'=>'t_richtext', 'name'=>'description', 'label'=>"Description", 'width'=>12 ]
		 ], $hints = [  // hints are provided for general purpose actions to be able to customize rendering of this particular element class
			 'a_Browse'=>[ 'role'=>'Finance', 'triggers'=>[ 'banner'=>"create", 'row'=>[ 'update' ] ], 'include'=>[ 'id','name','class','positive' ] ]
			,'a_Edit'=>[ 'role'=>'*super','triggers'=>[ 'banner'=>'delete' ] ]
		 ], $operations = [
			 'update'=>[ 'role'=>'*super' ],'delete'=>[ 'role'=>'*super' ],'create'=>[ 'role'=>'*super' ],'list'=>[ 'role'=>'Finance' ]
		 ];

	public function formatted()
	{
		return "$this->°name";
	}

	const StaticDataSQL = <<<'SQL'
-- These classes support the Finance module
REPLACE INTO actg_class (_id, name, class, positive, credittag, debittag, flags, description) VALUES
 (100, 'Other asset', 1, 1, NULL, NULL, '', NULL)
,(120, 'Receipts pending', 1, 1, 'Employee', 'DepositTrx', '', NULL)
,(130, 'Earned receivables', 1, 1, 'Client', 'Client', '', NULL)
,(180, 'Cash in hand', 1, 1, 'Employee', 'Employee', 'funding', NULL)
,(190, 'Bank account', 1, 1, 'BankStmtEntry', 'BankStmtEntry', 'funding,interest', NULL)
,(200, 'Liability', 2, 0, NULL, NULL, 'interest', NULL)
,(210, 'Credit line', 2, 0, 'BankStmtEntry', 'BankStmtEntry', 'interest,funding', NULL)
,(230, 'Fixed loan', 2, 0, NULL, NULL, 'interest', NULL)
,(290, 'Taxes payable', 2, 0, 'Tax', 'Tax', '', NULL)
,(300, 'Equity', 3, 0, NULL, NULL, '', NULL)
,(400, 'Income', 4, 0, 'Project', 'Project', '', NULL)
,(410, 'Earned revenue', 4, 0, 'Project', 'Project', '', NULL)
,(420, 'Passive income', 4, 0, 'Project', 'Project', 'interest', NULL)
,(500, 'Expense', 5, 1, 'Project', 'Project', 'interest', NULL)
,(600, 'Contra-account', 6, 0, NULL, NULL, '', NULL)
;

-- These classes support the Business module
REPLACE INTO actg_class (_id, name, `class`, positive, credittag, debittag, flags, description) VALUES
 (270, 'Vendors payable', 2, 0, 'Vendor', 'Vendor', 'interest', NULL)
,(280, 'Employees payable', 2, 0, 'Employee', 'Employee', '', NULL)
,(310, 'Owners equity', 3, 0, NULL, NULL, '', NULL)
;

-- These classes support the Securities module
--  REPLACE INTO actg_class (_id, name, `class`, positive, credittag, debittag, flags, description) VALUES
-- (170, 'Security', 1, 1, 'Security', 'Security', 'interest', NULL)
-- ;
SQL;

}
