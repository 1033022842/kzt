# 管理员主账号功能 - 踢出子账号 & 分配智能体

## 现状分析

| 已有功能 | 缺失功能 |
|---------|---------|
| `role=admin` 角色区分 | 踢出子账号（强制下线） |
| JWT Token 认证 | Token 主动失效机制 |
| AdminDashboard 查看所有配置 | 用户管理（列表/禁用/启用） |
| 配置归属 `user_id` | 配置所有权限变更（分配智能体） |
| `sys_user.status` 字段 | status 在认证流程中的使用 |

---

## 方案设计

### 一、踢出子账号

**策略：Token 版本号（token_version）**

在 `sys_user` 表新增 `token_version INT DEFAULT 1`。JWT 签发时嵌入此版本号，Auth 中间件校验版本号。管理员踢人时递增版本号 → 所有已签发 Token 立即失效。

**流程：**
```
登录 → JWT { sub, role, token_version: 1 } → 正常请求
管理员踢出 → token_version → 2
下次请求 → 中间件发现版本不匹配 → 返回 401 → 前端跳转登录
登录时检查 status=0 → 禁止登录
```

**涉及改动：**

1. DB Migration：`sys_user` 新增 `token_version INT DEFAULT 1 NOT NULL`
2. AuthController.login：JWT payload 中加入 `token_version`
3. AuthMiddleware：解析 Token 后查 `user.token_version`，不匹配则拒绝
4. AdminController 新增：
   - `GET /admin/users` — 用户列表
   - `PUT /admin/users/:id/status` — 启用/禁用用户
   - `PUT /admin/users/:id/kick` — 踢出（token_version++）
5. AdminDashboard.vue 新增「用户管理」Tab

### 二、分配智能体

**策略：允许管理员修改 `streamer_config.user_id`**

在 AdminDashboard 中，每个配置新增"分配用户"操作，弹出下拉选择目标用户，确认后更新 `user_id`。

**涉及改动：**

1. AdminController 新增：
   - `PUT /admin/configs/:id/assign` — 变更配置的 `user_id`
2. AdminDashboard.vue 配置表格新增"分配"按钮和弹窗

---

## 实施步骤

### Step 1：数据库迁移

**文件：** `backend/database/migration_add_token_version.sql`
```sql
ALTER TABLE `sys_user` ADD COLUMN `token_version` INT NOT NULL DEFAULT 1 AFTER `status`;
```

执行迁移。

### Step 2：后端 — Auth 层改造

**2.1 AuthController.login（JWT payload 加 token_version）**
```php
// 查询用户
$user = User::where('username', $params['username'])->find();
// 检查 status
if ($user->status !== 1) {
    throw new BusinessException(ResultCode::PARAM_ERROR, '账号已被禁用');
}
// JWT payload
$accessPayload = [
    'sub'          => $user->id,
    'username'     => $user->username,
    'role'         => $user->role,
    'token_version'=> $user->token_version,  // 新增
    'iat'          => time(),
];
```

**2.2 Auth 中间件（校验 token_version）**
```php
$payload = JwtService::parseToken($token);
// 新增：验证 token_version
$user = User::find($payload['sub'] ?? 0);
if (!$user || $user->status !== 1) {
    return json(Result::failedWith(ResultCode::ACCESS_TOKEN_INVALID));
}
if (($payload['token_version'] ?? 0) !== $user->token_version) {
    return json(Result::failedWith(ResultCode::ACCESS_TOKEN_INVALID, '账号已在其他地方登录'));
}
```

### Step 3：后端 — AdminController 新增接口

**3.1 用户列表 `GET /admin/users`**
```php
public function users() {
    $this->checkAdmin();
    $list = User::field('id,username,nickname,email,role,status,token_version,create_time')
        ->order('id', 'asc')->select();
    return $this->success(['list' => $list, 'total' => count($list)]);
}
```

**3.2 禁用/启用用户 `PUT /admin/users/:id/status`**
```php
public function toggleUserStatus($id) {
    $this->checkAdmin();
    $user = User::find($id);
    $user->status = $user->status === 1 ? 0 : 1;
    $user->save();
    return $this->success(['status' => $user->status]);
}
```

**3.3 踢出用户 `PUT /admin/users/:id/kick`**
```php
public function kickUser($id) {
    $this->checkAdmin();
    $user = User::find($id);
    $user->token_version = $user->token_version + 1;
    $user->save();
    return $this->success();
}
```

**3.4 分配智能体 `PUT /admin/configs/:id/assign`**
```php
public function assignConfig($id) {
    $this->checkAdmin();
    $targetUserId = $this->request->post('user_id');
    $config = StreamerConfig::find($id);
    $config->user_id = $targetUserId;
    $config->save();
    return $this->success();
}
```

### Step 4：路由注册

```php
// admin 路由组
Route::get('admin/users', 'app\controller\AdminController@users');
Route::put('admin/users/:id/status', 'app\controller\AdminController@toggleUserStatus');
Route::put('admin/users/:id/kick', 'app\controller\AdminController@kickUser');
Route::put('admin/configs/:id/assign', 'app\controller\AdminController@assignConfig');
```

### Step 5：前端 — AdminDashboard.vue 改造

**5.1 新增 Tab 切换**
- Tab 1：「智能体配置管理」（现有表格）
- Tab 2：「用户管理」（新增）

**5.2 用户管理表格**
| 列 | 说明 |
|----|------|
| ID | 用户 ID |
| 用户名 | username |
| 昵称 | nickname |
| 邮箱 | email |
| 角色 | `el-tag` 显示 admin/user |
| 状态 | `el-switch` 启用/禁用 |
| 操作 | 「踢出下线」按钮 + `el-popconfirm` |

- 「启用/禁用」调用 `PUT /admin/users/:id/status`
- 「踢出下线」调用 `PUT /admin/users/:id/kick`，确认后执行

**5.3 配置表格新增"分配"列**
- 在现有配置表格操作列新增「分配」按钮
- 点击弹出 `el-dialog`，显示当前所属用户
- 下拉选择目标用户（`el-select` + 搜索）
- 确认后调用 `PUT /admin/configs/:id/assign { user_id }`
- 刷新表格

### Step 6：前端 API 层

```javascript
// admin.js 新增
export function getUsers() {
  return request.get('/admin/users')
}
export function toggleUserStatus(id) {
  return request.put(`/admin/users/${id}/status`)
}
export function kickUser(id) {
  return request.put(`/admin/users/${id}/kick`)
}
export function assignConfig(id, userId) {
  return request.put(`/admin/configs/${id}/assign`, { user_id: userId })
}
```

---

## 关键设计决策

| 决策 | 原因 |
|------|------|
| Token 版本号而非黑名单 | 无需额外存储，一个整数字段即可让所有旧 Token 立即失效 |
| 禁用用户同时阻止新登录和已有 Token | Auth 中间件检查 `status !== 1` → 所有请求被拒绝 |
| 踢出仅递增 token_version | 不改变 status，用户仍可重新登录获取新 Token |
| 分配智能体直接改 `user_id` | 简单直接，原有 owner 信息可通过操作日志追溯（后续可选） |

## 技术要点

| 层级 | 文件 | 改动 |
|------|------|------|
| DB | `migration_add_token_version.sql` | 新增 `token_version` 字段 |
| 后端 Login | `AuthController.php` | JWT 加入 token_version |
| 后端 Middleware | `Auth.php` | 校验 token_version + status |
| 后端 User 模型 | `User.php` | 新增 token_version 类型定义 |
| 后端 Admin | `AdminController.php` | 新增 4 个接口 |
| 后端 Route | `route/app.php` | 新增 4 条路由 |
| 前端 API | `admin.js` | 新增 4 个请求函数 |
| 前端 UI | `AdminDashboard.vue` | Tab 切换 + 用户表格 + 分配弹窗 |
