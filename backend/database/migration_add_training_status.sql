ALTER TABLE `streamer_config` ADD COLUMN `training_status` VARCHAR(20) DEFAULT 'normal' AFTER `status`;
