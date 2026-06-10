ALTER TABLE `sys_user` ADD COLUMN `balance` DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER `token_version`;
ALTER TABLE `sys_user` ADD COLUMN `agent_quota` INT NOT NULL DEFAULT 1 AFTER `balance`;
ALTER TABLE `sys_user` ADD COLUMN `agent_used` INT NOT NULL DEFAULT 0 AFTER `agent_quota`;
