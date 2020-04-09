
-- ids_identity:
-- Add the role field which references a system role.
-- We're converting the _owner sysval to a _creator sysval with a different type.

ALTER TABLE ids_identity ADD _creator INT(10) UNSIGNED NOT NULL AFTER _capcel,
    ADD `role` int(10) unsigned DEFAULT NULL COMMENT 'e_role: Authorized role' AFTER emailias,
    ADD CONSTRAINT `ids_identity_role_auth_role` FOREIGN KEY (`role`) REFERENCES `auth_role` (`_id`) ON DELETE SET NULL;
UPDATE ids_identity, auth_login SET _creator = auth_login._id WHERE _owner = username;
ALTER TABLE  ids_identity
  DROP FOREIGN KEY ids_identity__owner_auth_login,
  ADD CONSTRAINT ids_identity__creator_auth_login FOREIGN KEY (`_creator`) REFERENCES  auth_login(_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  DROP _owner;
