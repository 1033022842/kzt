# 系统操作智能体 - 实现方案

## 可行性结论：可以实现，但需分阶段交付

这是一个中等复杂度的功能，核心技术栈均可行。以下是分阶段方案，Phase 1 为核心闭环（最快上线），Phase 2 为增强体验。

***

## 一、当前项目状态

| 维度    | 现状                                           |
| ----- | -------------------------------------------- |
| 后端框架  | ThinkPHP 8.0，无 AI/LLM 依赖                     |
| 前端框架  | Vue 3 + Element Plus + Pinia                 |
| 认证    | JWT（firebase/php-jwt）                        |
| 智能体类型 | marketing / inventory / secretary（已有）        |
| AI 集成 | **无**（秘书对话是纯前端 mock）                         |
| 数据库   | MySQL，`streamer_config.config_data` 为 JSON 列 |

***

## 二、整体架构

```
┌─────────────────────────────────────────────────────┐
│                     用户                             │
│  (上传手册 → 填网址/账号/密码 → 对话 → 看操作结果)   │
└────────────┬───────────────────────────┬────────────┘
             │ 上传文件                    │ 对话/操作请求
             ▼                             ▼
┌─────────────────────┐    ┌──────────────────────────────┐
│   后端 ThinkPHP     │    │   AI 服务层（新增）           │
│  - 文件上传/解析    │◄──►│  - LLM API 调用 (OpenAI等)    │
│  - 凭据加密存储     │    │  - 手册 RAG 检索              │
│  - 操作记录         │    │  - 浏览器自动化 (Playwright)  │
│  - SSE 流式推送     │    │  - 截图/日志回传             │
└─────────────────────┘    └──────────────────────────────┘
             │
             ▼
┌─────────────────────┐
│   MySQL 数据库       │
│  - 操作手册文本      │
│  - 凭据(加密)        │
│  - 对话历史          │
│  - 操作记录          │
└─────────────────────┘
```

***

## 三、Phase 1：核心闭环（推荐首次交付）

**范围：** 手册上传解析 → LLM 理解手册 → 用户对话获取操作指引（不含实际浏览器自动化）

### 3.1 数据库变更

**新建** **`operation_manuals`** **表：**

```sql
CREATE TABLE `operation_manuals` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `config_id` INT DEFAULT NULL COMMENT '关联的智能体配置ID',
    `filename` VARCHAR(255) NOT NULL COMMENT '原始文件名',
    `file_path` VARCHAR(500) NOT NULL COMMENT '文件存储路径',
    `file_type` VARCHAR(20) NOT NULL COMMENT 'pdf/docx/txt',
    `content_text` LONGTEXT COMMENT '解析后的纯文本',
    `content_json` JSON COMMENT '结构化操作步骤(LLM预解析)',
    `status` VARCHAR(20) DEFAULT 'pending' COMMENT 'pending/parsing/ready/error',
    `error_msg` VARCHAR(500) DEFAULT '',
    `create_time` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `update_time` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `sys_user`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`config_id`) REFERENCES `streamer_config`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**修改** **`streamer_config`** **表 —— 新增字段：**

```sql
ALTER TABLE `streamer_config`
ADD COLUMN `website_url` VARCHAR(500) DEFAULT '' COMMENT '目标网站后台地址',
ADD COLUMN `website_account` VARCHAR(255) DEFAULT '' COMMENT '后台账号(AES加密)',
ADD COLUMN `website_password` VARCHAR(500) DEFAULT '' COMMENT '后台密码(AES加密)',
ADD COLUMN `manual_id` INT DEFAULT NULL COMMENT '关联的操作手册ID';
```

### 3.2 后端新增

#### 新增文件

| 文件                                            | 说明                        |
| --------------------------------------------- | ------------------------- |
| `app/controller/OperationAgentController.php` | 操作智能体控制器                  |
| `app/model/OperationManual.php`               | 操作手册模型                    |
| `app/common/ai/LlmService.php`                | LLM API 调用封装（OpenAI/兼容接口） |
| `app/common/ai/ManualParser.php`              | 手册文本提取（PDF/Word/TXT）      |
| `app/common/security/CryptoService.php`       | AES 加密/解密服务（凭据存储）         |

#### 新增路由（route/app.php）

```php
Route::group('operation-agent', function () {
    // 手册管理
    Route::post('manuals/upload', 'app\controller\OperationAgentController@uploadManual');
    Route::get('manuals', 'app\controller\OperationAgentController@manuals');
    Route::delete('manuals/:id', 'app\controller\OperationAgentController@deleteManual');
    // 对话
    Route::post('chat', 'app\controller\OperationAgentController@chat');
    Route::post('chat/stream', 'app\controller\OperationAgentController@chatStream');
    Route::get('history/:configId', 'app\controller\OperationAgentController@history');
    // 操作执行（Phase 2）
    Route::post('execute', 'app\controller\OperationAgentController@execute');
    Route::get('operations/:configId', 'app\controller\OperationAgentController@operationLogs');
});
```

#### 关键 API 设计

**POST** **`/api/v1/operation-agent/manuals/upload`**

* 接收 multipart/form-data，file 字段为手册文件

* 支持 pdf / docx / txt，最大 20MB

* 后端调用 `ManualParser` 提取文本

* 调用 LLM 预解析结构化为 JSON 操作步骤

* 返回 manual\_id + 解析预览

**POST** **`/api/v1/operation-agent/chat`**（SSE 流式）

* 参数：`{ config_id, message }`

* 后端拼接：system prompt（手册内容 + 网站信息） + 历史对话 + 用户消息

* 调用 LLM，通过 SSE 流式返回

* 保存对话历史到 `operation_chat_history` 表

**POST** **`/api/v1/operation-agent/execute`**（Phase 2 启用）

* 参数：`{ config_id, instruction }`

* 后端调用 Playwright 执行浏览器操作

* 通过 SSE 实时推送操作状态、截图、日志

### 3.3 前端新增

#### 新增文件

| 文件                             | 说明           |
| ------------------------------ | ------------ |
| `src/api/operationAgent.js`    | 操作智能体 API 封装 |
| `src/views/OperationAgent.vue` | 操作智能体主页面     |

#### 修改文件

| 文件                             | 变更说明                      |
| ------------------------------ | ------------------------- |
| `src/router/index.js`          | 新增路由 `/operation-agent`   |
| `src/views/StreamerEditor.vue` | 左侧类型选择增加"操作类"按钮，新增"操作类"面板 |
| `src/views/AdminDashboard.vue` | 智能体列表中增加"操作类"显示           |

> **简化为独立页面方案（推荐）：** 如果 StreamerEditor.vue 已经非常复杂，可将操作智能体做成独立页面 `/operation-agent`，与现有三种类型并行。

#### 操作智能体页面 UI 结构

```
┌──────────────────────────────────────────────────────┐
│  📋 系统操作智能体                                    │
├──────────────────┬───────────────────────────────────┤
│  左侧：配置面板    │  右侧：对话区域                     │
│                  │                                   │
│  ┌─ 基本设置 ───┐│  ┌─────────────────────────────┐  │
│  │ 智能体名称    ││  │ 欢迎消息                     │  │
│  │ 目标网址      ││  │                            │  │
│  │ 后台账号      ││  │ [用户消息气泡]              │  │
│  │ 后台密码      ││  │                            │  │
│  └──────────────┘│  │ [AI回复气泡(Markdown)]      │  │
│                  ││  │                            │  │
│  ┌─ 操作手册 ───┐│  ├─────────────────────────────┤  │
│  │ 📎 上传手册   ││  │ [输入框.............] [发送] │  │
│  │ 手册列表...   ││  └─────────────────────────────┘  │
│  │ (解析状态)    ││                                   │
│  └──────────────┘│                                   │
└──────────────────┴───────────────────────────────────┘
```

### 3.4 外部依赖

| 依赖                  | 用途             | 安装方式                                 |
| ------------------- | -------------- | ------------------------------------ |
| `openai-php/client` | OpenAI API 客户端 | `composer require openai-php/client` |
| `smalot/pdfparser`  | PDF 文本提取       | `composer require smalot/pdfparser`  |
| `phpoffice/phpword` | Word 文本提取      | `composer require phpoffice/phpword` |
| OpenAI API Key      | LLM 调用         | 用户配置在 `.env` 或后台管理                   |

### 3.5 凭据安全

* 网站账号、密码使用 AES-256-CBC 加密后存入数据库

* 加密密钥从 `.env` 读取（`WEBSITE_CREDENTIALS_KEY`）

* API 返回给前端时脱敏显示（如 `admin***`）

***

## 四、Phase 2：浏览器自动化（进阶）

在 Phase 1 基础上，增加实际网页操作能力：

### 4.1 新增依赖

* Node.js 服务（Playwright 通过 Node 运行更好）或 PHP `browserless/chrome` 容器

* 推荐方案：部署一个轻量 Python/Node sidecar 容器，运行 Playwright 脚本

### 4.2 操作流程

```
用户: "帮我在后台创建一个新用户，用户名 testuser"
    ↓
LLM 解析手册 → 理解操作为"用户管理→新增用户→填写表单→提交"
    ↓
生成 Playwright 脚本 → 执行浏览器操作
    ↓
截图每一步 → 返回结果
    ↓
用户看到：操作步骤 × 截图 + 成功/失败状态
```

***

## 五、关键决策点

1. **LLM 提供商：** 使用 OpenAI 兼容接口（可配置 base\_url，支持国产模型如 DeepSeek/Qwen）
2. **流式响应：** 使用 SSE（Server-Sent Events），ThinkPHP 原生支持
3. **手册存储：** 文本内容存入数据库（LONGTEXT），便于全文检索；原始文件存磁盘
4. **智能体类型扩展：** 在现有 `StreamerEditor.vue` 中增加 `operation` 类型，还是独立页面？
5. **浏览器自动化：** Phase 1 先不做，只做"AI 读手册给指引"；Phase 2 再加真实操作

***

## 六、验证步骤（Phase 1）

1. 在 ThinkPHP 中引入 `openai-php/client`，配置 API Key
2. 测试手册上传（PDF/Word/TXT）→ 文本提取是否正常
3. 提交手册文本 + 用户问题到 LLM → 验证回复质量
4. 验证 SSE 流式响应是否正常工作
5. 前端操作智能体 UI 完整对话流程
6. 凭据加密/解密正确性验证

***

## 七、工程量评估

| 阶段           | 后端                 | 前端      | 总工作量 |
| ------------ | ------------------ | ------- | ---- |
| Phase 1（核心）  | 约 800 行            | 约 600 行 | 中    |
| Phase 2（自动化） | 约 1500 行 + sidecar | 约 400 行 | 大    |

