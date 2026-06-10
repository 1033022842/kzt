-- tronusdt 对接：tx_hash 改为可空（不再需要用户手动填写）
ALTER TABLE `recharge_record` MODIFY `tx_hash` VARCHAR(128) NULL DEFAULT NULL COMMENT 'TRC20交易哈希（tronusdt模式可为空）';
