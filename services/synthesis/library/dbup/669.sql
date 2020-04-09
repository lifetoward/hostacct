
ALTER TABLE  `biz_project` ADD  `discount` DECIMAL( 6, 5 ) UNSIGNED NULL DEFAULT  '0' COMMENT  'Project standard discount' AFTER  `propdate` ;

UPDATE actg_class SET debittag = 'FundsXfer' where _id IN (120,270);

ALTER TABLE `biz_client`
  ADD `entity` int(10) unsigned NOT NULL COMMENT 'include: Entity (e_entity)' AFTER company;

UPDATE biz_client,com_organization SET biz_client.entity = com_organization.entity WHERE com_organization._id = company;

ALTER TABLE `biz_client`
  ADD CONSTRAINT `biz_client_entity_com_entity` FOREIGN KEY (`entity`) REFERENCES `com_entity` (`_id`) ON DELETE CASCADE,
  DROP INDEX _inckey_company,
  DROP INDEX _ident,
  DROP company,
  ADD UNIQUE KEY `_ident` (`entity`);

CREATE TABLE `fund_xfer` (
  `_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'FundsXfer Primary Key',
  `_capcel` varchar(255) DEFAULT NULL COMMENT 'capcel, System Value',
  `type` varchar(31) DEFAULT 'cheque' COMMENT 'select: Type',
  `amount` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT 't_dollars(8.2): Total amount',
  `xid` varchar(255) NOT NULL COMMENT 'string: Specific identifier',
  `payer` int(10) unsigned NOT NULL COMMENT 'belong: Contactable entity (e_entity)',
  `acctnum` varchar(255) DEFAULT NULL COMMENT 'string: Paying account identifier',
  `receiver` int(10) unsigned NOT NULL COMMENT 'belong: Contactable entity (e_entity)',
  `broker` int(10) unsigned DEFAULT NULL COMMENT 'refer: Contactable entity (e_entity)',
  `viafee` decimal(10,2) unsigned DEFAULT NULL COMMENT 't_dollars(8.2): Transaction fee',
  `paydate` date DEFAULT NULL COMMENT 'date: Payment date',
  `rcvdate` date DEFAULT NULL COMMENT 'date: Receipt date',
  `trx` int(10) unsigned DEFAULT NULL COMMENT 'refer: General transaction (Transaction)',
  PRIMARY KEY (`_id`),
  UNIQUE KEY `_ident` (`type`,`xid`,`payer`),
  KEY `fund_xfer_payer_com_entity` (`payer`),
  KEY `fund_xfer_receiver_com_entity` (`receiver`),
  KEY `fund_xfer_broker_com_entity` (`broker`),
  KEY `fund_xfer_trx_actg_trx` (`trx`),
  CONSTRAINT `fund_xfer_broker_com_entity` FOREIGN KEY (`broker`) REFERENCES `com_entity` (`_id`) ON DELETE SET NULL,
  CONSTRAINT `fund_xfer_payer_com_entity` FOREIGN KEY (`payer`) REFERENCES `com_entity` (`_id`) ON DELETE CASCADE,
  CONSTRAINT `fund_xfer_receiver_com_entity` FOREIGN KEY (`receiver`) REFERENCES `com_entity` (`_id`) ON DELETE CASCADE,
  CONSTRAINT `fund_xfer_trx_actg_trx` FOREIGN KEY (`trx`) REFERENCES `actg_trx` (`_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8 COMMENT='Funding details (FundsXfer)';

CREATE TABLE `biz_statement` (
  `_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Statement Primary Key',
  `_capcel` varchar(255) DEFAULT NULL COMMENT 'capcel, System Value',
  `client` int(10) unsigned NOT NULL COMMENT 'belong: Client (Client)',
  `closedate` date DEFAULT NULL COMMENT 'date: Month',
  `prevbal` decimal(10,2) unsigned DEFAULT NULL COMMENT 't_balance(8.2): Previous',
  `due` decimal(10,2) unsigned DEFAULT NULL COMMENT 't_balance(8.2): Current',
  `url` varchar(255) DEFAULT NULL COMMENT 'url: URL',
  `message` text COMMENT 'text: Message',
  `prepared` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'timestamp: Date prepared',
  PRIMARY KEY (`_id`),
  UNIQUE KEY `client_month` (`client`,`closedate`),
  CONSTRAINT `biz_statement_client_biz_client` FOREIGN KEY (`client`) REFERENCES `biz_client` (`_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Statement info (Statement)';


CREATE TABLE `biz_receipt` (
  `_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ReceiptTrx Primary Key',
  `_capcel` varchar(255) DEFAULT NULL COMMENT 'capcel, System Value',
  `funding` int(10) unsigned NOT NULL COMMENT 'include: Funding transfer (FundsXfer)',
  `trx` int(10) unsigned NOT NULL COMMENT 'include: General transaction (Transaction)',
  `method` varchar(255) DEFAULT NULL COMMENT 'string: Method received',
  `statement` int(10) unsigned DEFAULT NULL COMMENT 'refer: Statement (Statement)',
  PRIMARY KEY (`_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COMMENT='Receipt details (ReceiptTrx)';


alter table biz_receipt
ADD UNIQUE KEY `_ident` (`funding`),
ADD   UNIQUE KEY `_inckey_funding` (`funding`),
  ADD UNIQUE KEY `_inckey_trx` (`trx`),
  ADD KEY `biz_receipt_statement_biz_statement` (`statement`),
  ADD CONSTRAINT `biz_receipt_funding_fund_xfer` FOREIGN KEY (`funding`) REFERENCES `fund_xfer` (`_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `biz_receipt_trx_actg_trx` FOREIGN KEY (`trx`) REFERENCES `actg_trx` (`_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `biz_receipt_statement_biz_statement` FOREIGN KEY (`statement`) REFERENCES `biz_statement` (`_id`) ON DELETE SET NULL;


CREATE TABLE `biz_clienttrx` (
  `_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ClientTrx Primary Key',
  `_capcel` varchar(255) DEFAULT NULL COMMENT 'capcel, System Value',
  `project` int(10) unsigned NOT NULL COMMENT 'belong: Project (Project)',
  `item` int(10) unsigned NOT NULL COMMENT 'require: Client exchange item (ClientItem)',
  `quantity` decimal(7,2) unsigned DEFAULT NULL COMMENT 't_decimal(5.2): Quantity',
  `extprice` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT 't_dollars(8.2): Extended price',
  `trx` int(10) unsigned NOT NULL COMMENT 'include: General transaction (Transaction)',
  `package` int(10) unsigned DEFAULT NULL COMMENT 'refer: Client Transaction (ClientTrx)',
  `overage` decimal(12,3) DEFAULT NULL COMMENT 't_decimal(9.3): Quantity over basic entitlement',
  `statement` int(10) unsigned DEFAULT NULL COMMENT '*refer: Statement',
  PRIMARY KEY (`_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8 COMMENT='Client Transaction details (ClientTrx)';

Alter table biz_clienttrx
ADD  UNIQUE KEY `_ident` (`project`,`item`,`trx`),
ADD  UNIQUE KEY `_inckey_trx` (`trx`),
ADD  KEY `biz_clienttrx_item_biz_item` (`item`),
ADD  KEY `biz_clienttrx_package_biz_clienttrx` (`package`),
ADD  KEY `statement` (`statement`),
ADD  CONSTRAINT `biz_clienttrx_item_biz_item` FOREIGN KEY (`item`) REFERENCES `biz_item` (`_id`),
ADD  CONSTRAINT `biz_clienttrx_package_biz_clienttrx` FOREIGN KEY (`package`) REFERENCES `biz_clienttrx` (`_id`) ON DELETE SET NULL,
ADD  CONSTRAINT `biz_clienttrx_project_biz_project` FOREIGN KEY (`project`) REFERENCES `biz_project` (`_id`) ON DELETE CASCADE,
ADD  CONSTRAINT `biz_clienttrx_statement_biz_statement` FOREIGN KEY (`statement`) REFERENCES `biz_statement` (`_id`),
ADD  CONSTRAINT `biz_clienttrx_trx_actg_trx` FOREIGN KEY (`trx`) REFERENCES `actg_trx` (`_id`) ON DELETE CASCADE;
