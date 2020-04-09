
-- Adding enthusiasm field to entity

ALTER TABLE com_entity ADD enthusiasm TINYINT(1) DEFAULT NULL COMMENT 't_rating: 1-5 stars';
