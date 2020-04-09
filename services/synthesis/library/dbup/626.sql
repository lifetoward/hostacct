-- DBUP for Business module updates with revision 626

ALTER TABLE biz_client
   ADD discount DECIMAL(6,5) UNSIGNED DEFAULT NULL COMMENT 't_percent(1.5): General discount'
  ;

-- New table biz_item will be auto-created.
-- New table biz_statement will be auto-created.
