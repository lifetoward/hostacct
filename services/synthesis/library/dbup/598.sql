
UPDATE actg_account SET class = class * 10 where class < 100;

CREATE TABLE actg_class (
  _id int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'AcctClass Primary Key',
  _capcel varchar(255) DEFAULT NULL COMMENT 'capcel, System Value',
  `name` varchar(255) NOT NULL COMMENT 'string: Class name',
  class varchar(31) DEFAULT NULL COMMENT 'select: Major class',
  positive tinyint(1) NOT NULL DEFAULT '0' COMMENT 'boolean: Positive type',
  credittag varchar(255) DEFAULT NULL COMMENT 'class: Credit entry tag class',
  debittag varchar(255) DEFAULT NULL COMMENT 'class: Debit entry tag class',
  flags set('inactive','funding','interest') CHARACTER SET binary NOT NULL DEFAULT 'inactive' COMMENT 'boolset: Relevant capabilities',
  description text COMMENT 'richtext: Description',
  PRIMARY KEY (_id),
  UNIQUE KEY _ident (`name`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Account class specification (AcctClass)';

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
,(420, 'Passive income', 4, 0, 'Project', 'Project', '', NULL)
,(500, 'Expense', 5, 1, 'Project', 'Project', '', NULL)
,(600, 'Contra-account', 6, 0, NULL, NULL, '', NULL)
;

-- These classes support the Business module
REPLACE INTO actg_class (_id, name, `class`, positive, credittag, debittag, flags, description) VALUES
 (270, 'Vendors payable', 2, 0, 'Vendor', 'Vendor', 'interest', NULL)
,(280, 'Employees payable', 2, 0, 'Employee', 'Employee', '', NULL)
,(310, 'Owners equity', 3, 0, NULL, NULL, '', NULL)
;

ALTER TABLE actg_account
     CHANGE class class INT(10) UNSIGNED DEFAULT NULL COMMENT 'require: Account class'
    ,ADD CONSTRAINT actg_account_class_actg_class FOREIGN KEY (class) REFERENCES actg_class(_id)
;