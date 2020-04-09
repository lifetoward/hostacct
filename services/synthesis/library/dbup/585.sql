
-- com_organization:
-- Add field empident to hold EIN

ALTER TABLE com_organization ADD empident VARCHAR(255) DEFAULT NULL COMMENT 'string: Employer Identification Number' AFTER contact;
