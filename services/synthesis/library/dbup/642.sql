-- DBUP for adjustments to Identities module after months of use.

ALTER TABLE ids_provider
   ADD `loginuri` varchar(255) DEFAULT NULL COMMENT 'url: Login page' AFTER acctmanage
  ;

