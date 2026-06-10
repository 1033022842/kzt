# 套餐购买 & TRC20-USDT 充值系统 — 落地方案

## 需求回顾

> - 套餐：$799（1个智能体）/ $1399（2个）/ $1999（3个）
> - 单独加智能体 $799/个
> - 余额购买（先充值后购买）
> - 充值方式：TRC20-USDT

---

## 一、总体流程

```
用户 → 充值页 → 获取平台TRC20地址 → 转账USDT → 提交TxHash
管理员 → 后台审核TxHash → 确认到账 → 用户余额增加
用户 → 套餐页 → 选购套餐 → 余额扣减 → 智能体配额增加
用户 → 创建智能体 → 校验配额 → 配额-1
```

---

## 二、数据库设计

### 2.1 sys_user 新增字段

```sql
ALTER TABLE `sys_user` ADD COLUMN `balance` DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER `token_version`;
ALTER TABLE `sys_user` ADD COLUMN `agent_quota` INT NOT NULL DEFAULT 1 AFTER `balance`;
ALTER TABLE `sys_user` ADD COLUMN `agent_used` INT NOT NULL DEFAULT 0 AFTER `agent_quota`;
```

| 字段 | 说明 |
|------|------|
| `balance` | 账户余额（美元） |
| `agent_quota` | 智能体总配额 |
| `agent_used` | 已使用智能体数 |

### 2.2 新建充值记录表

```sql
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2.3 新建购买记录表（审计用）

```sql
CREATE TABLE `purchase_record` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `package_name` VARCHAR(50) NOT NULL COMMENT '套餐名称',
    `agent_count` INT NOT NULL COMMENT '获得智能体数量',
    `amount` DECIMAL(10,2) NOT NULL COMMENT '支付金额',
    `create_time` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `sys_user`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2.4 平台 TRC20 配置

```sql
CREATE TABLE `system_config` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `key` VARCHAR(50) UNIQUE NOT NULL,
    `value` TEXT NOT NULL,
    `update_time` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 初始数据
INSERT INTO `system_config` (`key`, `value`) VALUES
('trc20_address', ''),
('trc20_min_amount', '50');
```

---

## 三、类目/模型结构

| 层级 | 文件 | 说明 |
|------|------|------|
| DB Migration | `database/migration_add_balance.sql` | 新增 balance + agent_quota + agent_used |
| DB Migration | `database/create_recharge_purchase_tables.sql` | 新建 recharge_record + purchase_record + system_config |
| Model | `app/model/RechargeRecord.php` | 充值记录模型 |
| Model | `app/model/PurchaseRecord.php` | 购买记录模型 |
| Model | `app/model/SystemConfig.php` | 系统配置模型 |
| Controller | `app/controller/PaymentController.php` | 支付相关接口（充值+购买） |
| Validate | `app/validate/PaymentValidate.php` | 参数校验 |
| Route | `route/app.php` | 新增充值+购买路由，admin审核路由 |

---

## 四、后端 API 设计

### 4.1 用户端

| 方法 | 路径 | 说明 |
|------|------|------|
| `GET` | `/api/v1/payment/balance` | 查询余额和配额 |
| `GET` | `/api/v1/payment/trc20-address` | 获取平台 TRC20 地址 |
| `POST` | `/api/v1/payment/recharge` | 提交充值请求（amount + tx_hash） |
| `GET` | `/api/v1/payment/recharge-records` | 我的充值记录 |
| `POST` | `/api/v1/payment/purchase` | 购买套餐（package_type） |
| `GET` | `/api/v1/payment/purchase-records` | 我的购买记录 |

### 4.2 管理员

| 方法 | 路径 | 说明 |
|------|------|------|
| `GET` | `/api/v1/admin/recharges` | 所有充值申请列表 |
| `PUT` | `/api/v1/admin/recharges/:id/confirm` | 确认到账 |
| `PUT` | `/api/v1/admin/recharges/:id/reject` | 驳回充值 |
| `PUT` | `/api/v1/admin/trc20-config` | 修改 TRC20 地址 |

---

## 五、前端页面设计

### 5.1 顶部余额展示（全局）

在 App.vue 或各页面顶部栏显示：余额 $X.XX | 智能体 X/Y 个

### 5.2 充值页面（新页面 `/recharge`）

1. 显示平台 TRC20-USDT 地址（可复制）
2. 提示最低充值金额
3. 输入转账金额 + TxHash
4. 提交后显示"待审核"
5. 充值记录列表（状态标签）

### 5.3 套餐购买弹窗/页面

在 StreamerEditor 或独立页面：
1. 三个套餐卡片：$799(1个) / $1399(2个) / $1999(3个)
2. 单独加智能体 $799
3. 余额不足提示充值
4. 购买记录

### 5.4 管理员审核（AdminDashboard 新增 Tab）

1. 「充值审核」Tab
2. 列表：用户、金额、TxHash、时间、操作（确认/驳回）
3. 确认后自动加余额

---

## 六、配额校验集成

在 `StreamerController.create()` 中新增配额校验：
- `agent_used >= agent_quota` → 提示配额不足，引导购买
- 创建成功后 `agent_used + 1`

在 `StreamerController.delete()` 中：
- `agent_used - 1`

---

## 七、套餐定价常量

```php
const PACKAGES = [
    'single'  => ['name' => '1个智能体',  'agents' => 1, 'price' => 799],
    'double'  => ['name' => '2个智能体',  'agents' => 2, 'price' => 1399],
    'triple'  => ['name' => '3个智能体',  'agents' => 3, 'price' => 1999],
    'add_one' => ['name' => '加购1个智能体', 'agents' => 1, 'price' => 799],
];
```

---

## 八、实施步骤

| Step | 内容 |
|------|------|
| 1 | 数据库迁移：sys_user 新增 balance/agent_quota/agent_used；新建 recharge_record/purchase_record/system_config 表 |
| 2 | 后端模型：RechargeRecord、PurchaseRecord、SystemConfig |
| 3 | 后端 PaymentController：用户充值+购买接口 |
| 4 | 后端 Admin 审核接口：确认/驳回充值 |
| 5 | 后端配额校验：StreamerController create/delete 集成 agent_quota |
| 6 | 前端路由：新增 `/recharge` 页面路由 |
| 7 | 前端页面：充值页（TRC20地址 + 提交TxHash） |
| 8 | 前端页面：套餐购买弹窗/组件 |
| 9 | 前端 AdminDashboard：新增「充值审核」Tab |
| 10 | 全局余额/配额展示 + 构建验证 |
