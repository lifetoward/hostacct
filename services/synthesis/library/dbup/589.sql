
-- We're completely changing the way e_account stores account classes.
-- The type of the t_select column becomes numeric, and we must map old names for account classes to the new numerics.

UPDATE actg_account SET class = 10 WHERE class = 'asset';
UPDATE actg_account SET class = 11 WHERE class = 'fixed';
UPDATE actg_account SET class = 17 WHERE class = 'security';
UPDATE actg_account SET class = 19 WHERE class = 'current';
UPDATE actg_account SET class = 20 WHERE class = 'liability';
UPDATE actg_account SET class = 30 WHERE class = 'equity';
UPDATE actg_account SET class = 40 WHERE class = 'income';
UPDATE actg_account SET class = 50 WHERE class = 'expense';

ALTER TABLE actg_account
     DROP sign
    ,CHANGE  `class`  `class` SMALLINT( 4 ) UNSIGNED NULL DEFAULT NULL COMMENT  'select*: Account type'
    ,CHANGE  `flags`  `flags` SET(  'inactive',  'funding',  'interest' ) CHARACTER SET BINARY NOT NULL DEFAULT  '' COMMENT  'boolset: Capabilities'
    ,CHANGE  `cleared`  `cleared` DECIMAL( 12, 2 ) NULL DEFAULT NULL COMMENT  't_balance(10.2): Reconciled balance'
;

ALTER TABLE actg_vendor
     DROP category
    ,ADD `org`  INT(10) UNSIGNED DEFAULT NULL COMMENT 'include: Company (e_organization)' AFTER _capcel
;

insert into com_organization (entity) select entity FROM  `actg_vendor` WHERE entity NOT IN (SELECT entity FROM com_organization);

update actg_vendor, com_organization set actg_vendor.org = com_organization._id, com_organization._capcel = actg_vendor._capcel where com_organization.entity = actg_vendor.entity;

ALTER TABLE actg_vendor
     DROP foreign key actg_vendor_entity_com_entity
    ,DROP entity
    ,ADD CONSTRAINT `actg_vendor_org_com_organization` FOREIGN KEY (`org`) REFERENCES `com_organization` (`_id`) ON DELETE SET NULL
;

alter table actg_trx
    ADD `class` varchar(31) not null default 'Transaction' comment 't_select: Transaction class' after _capcel
   ,CHANGE `attachpoint`  `attachpoint` INT( 10 ) UNSIGNED NULL DEFAULT NULL COMMENT  'fieldset: Attachments (f_attachpoint)'
   ,DROP FOREIGN KEY  `actg_trx_attachpoint_attachpoint`
;
ALTER TABLE  `actg_trx`
     ADD CONSTRAINT  `actg_trx_attachpoint_attachpoint` FOREIGN KEY (attachpoint) REFERENCES attachpoint (_id) ON DELETE SET NULL ON UPDATE RESTRICT
;

alter table actg_entry add `tag` int(10) unsigned default null comment 't_id: Tagged element' after memo;

-- Because we've renamed classes, we must update all the _capcel references to the classes that have changed.
update actg_trx set _capcel = CONCAT('Transaction',SUBSTR(_capcel,6)) WHERE _capcel LIKE 'e_trx=%';
update actg_account set _capcel = CONCAT('A',SUBSTR(_capcel,4)) WHERE _capcel LIKE 'e_account=%';
update actg_entry set _capcel = CONCAT('AcctE',SUBSTR(_capcel,8)) WHERE _capcel LIKE 'e_acctentry=%';

RENAME TABLE  `actg_bank` TO  `bank_bank` ;
RENAME TABLE  `actg_bankacct` TO  `bank_account` ;

-- For each renamed class, we must update its table and all tables it includes, swapping out any _capcel reference classnames with the new.
update bank_bank set _capcel = CONCAT('B',SUBSTR(_capcel,4)) WHERE _capcel LIKE 'e_bank=%';
update actg_vendor set _capcel = CONCAT('B',SUBSTR(_capcel,4)) WHERE _capcel LIKE 'e_bank=%';
update com_organization set _capcel = CONCAT('B',SUBSTR(_capcel,4)) WHERE _capcel LIKE 'e_bank=%';
update com_entity set _capcel = CONCAT('B',SUBSTR(_capcel,4)) WHERE _capcel LIKE 'e_bank=%';
update bank_account set _capcel = CONCAT('BankAccount',SUBSTR(_capcel,LOCATE('=',_capcel))) WHERE _capcel LIKE 'e_bankacct=%';
update actg_account set _capcel = CONCAT('BankAccount',SUBSTR(_capcel,LOCATE('=',_capcel))) WHERE _capcel LIKE 'e_bankacct=%';
update actg_vendor set _capcel = CONCAT('Vendor',SUBSTR(_capcel,LOCATE('=',_capcel))) WHERE _capcel LIKE 'e_vendor=%';
update com_organization set _capcel = CONCAT('Vendor',SUBSTR(_capcel,LOCATE('=',_capcel))) WHERE _capcel LIKE 'e_vendor=%';
update com_entity set _capcel = CONCAT('Vendor',SUBSTR(_capcel,LOCATE('=',_capcel))) WHERE _capcel LIKE 'e_vendor=%';

