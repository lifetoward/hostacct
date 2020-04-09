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