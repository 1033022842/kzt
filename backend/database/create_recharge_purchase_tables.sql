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
