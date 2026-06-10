CREATE DATABASE IF NOT EXISTS `38_47_239_185_88` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `38_47_239_185_88`;

DROP TABLE IF EXISTS `streamer_config`;
DROP TABLE IF EXISTS `recharge_record`;
DROP TABLE IF EXISTS `purchase_record`;
DROP TABLE IF EXISTS `system_config`;
DROP TABLE IF EXISTS `sys_user`;

CREATE TABLE `sys_user` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `username` VARCHAR(50) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `nickname` VARCHAR(50) DEFAULT '',
    `email` VARCHAR(100) DEFAULT '',
    `role` VARCHAR(20) DEFAULT 'user',
    `status` TINYINT DEFAULT 1,
    `token_version` INT NOT NULL DEFAULT 1,
    `balance` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `agent_quota` INT NOT NULL DEFAULT 1,
    `agent_used` INT NOT NULL DEFAULT 0,
    `create_time` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `update_time` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `recharge_record` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL COMMENT '充值金额(USD)',
    `tx_hash` VARCHAR(128) NOT NULL COMMENT 'TRC20交易哈希',
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending/confirmed/rejected',
    `remark` VARCHAR(500) DEFAULT '' COMMENT '管理员备注',
    `create_time` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `update_time` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `sys_user`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `purchase_record` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `package_name` VARCHAR(50) NOT NULL COMMENT '套餐名称',
    `agent_count` INT NOT NULL COMMENT '获得智能体数量',
    `amount` DECIMAL(10,2) NOT NULL COMMENT '支付金额',
    `create_time` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `sys_user`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `system_config` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `key` VARCHAR(50) UNIQUE NOT NULL,
    `value` TEXT NOT NULL,
    `update_time` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `system_config` (`key`, `value`) VALUES
('trc20_address', ''),
('trc20_min_amount', '50');

CREATE TABLE `streamer_config` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `avatar` VARCHAR(500) DEFAULT '',
    `config_data` JSON NOT NULL,
    `system_prompt` TEXT,
    `status` TINYINT DEFAULT 1,
    `training_status` VARCHAR(20) DEFAULT 'normal',
    `create_time` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `update_time` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `sys_user`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
