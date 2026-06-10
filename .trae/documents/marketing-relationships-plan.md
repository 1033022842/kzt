# 营销类智能体 - 人际关系功能实施方案

## 需求分析

营销类智能体需要新增"人际关系"字段，可以绑定其他营销智能体并设定关系（如姐姐、朋友），支持多条关系。数据需持久化保存，并预留未来查询接口。

## 数据结构设计

```json
// form.relationships 新增字段，存储在 config_data 中
[
  { "targetConfigId": "12", "targetName": "莉莉", "relation": "姐姐", "avatar": "http://..." },
  { "targetConfigId": "7",  "targetName": "阿飞", "relation": "朋友", "avatar": "http://..." }
]
```

每个关系条目包含：
- `targetConfigId`: 目标智能体配置ID（用于后续查询关联信息）
- `targetName`: 目标智能体名称（冗余存储，方便展示）
- `relation`: 关系描述（用户自定义，如"姐姐"、"朋友"、"搭档"）
- `avatar`: 目标智能体头像（冗余存储，方便列表展示）

## 实施步骤

### 步骤 1：前端数据层（StreamerEditor.vue script）

1.1 在 `createDefaultForm()` 中新增字段：
```javascript
relationships: []
```

1.2 在 `setFormValues()` 中新增加载逻辑：
```javascript
form.relationships = Array.isArray(normalized.relationships) ? normalized.relationships : []
```

1.3 在 `getFormSnapshot()` 中新增保存逻辑：
```javascript
relationships: form.relationships.map(r => ({ targetConfigId: r.targetConfigId, targetName: r.targetName, relation: r.relation, avatar: r.avatar }))
```

1.4 新增响应式状态：
```javascript
const relationshipDialogVisible = ref(false)
const relationshipForm = reactive({ targetConfigId: '', targetName: '', relation: '' })
const relationshipEditIndex = ref(-1)
```

1.5 新增辅助 computed，从 `myConfigs` 中筛选营销类智能体列表：
```javascript
const marketingAgentOptions = computed(() => {
  return myConfigs.value
    .filter(c => getConfigType(c) === 'marketing' && String(getConfigIdentity(c)) !== String(selectedConfigId.value))
    .map(c => ({
      id: getConfigIdentity(c),
      name: c.name || '未命名',
      avatar: getConfigPhoto(c) || ''
    }))
})
```

1.6 新增操作函数：
- `openAddRelationship()` - 打开新增弹窗
- `editRelationship(index)` - 编辑已有关系
- `confirmRelationship()` - 确认新增/编辑（从下拉选智能体后自动带入名称和头像）
- `deleteRelationship(index)` - 删除关系
- 表单校验：不能选择自己、不能重复绑定同一智能体

### 步骤 2：前端 UI 层（StreamerEditor.vue template）

2.1 在"基本信息"卡片底部（职业字段之后）新增 `el-form-item`：
```
label="人际关系"
展示已绑定关系的 tag 列表，每行：头像 + 名称 + "是她的" + 关系
每行右侧有编辑/删除按钮
底部有「+ 添加关系」按钮
```

2.2 新增「添加/编辑人际关系」弹窗（`el-dialog`）：
- 下拉选择目标智能体（el-select 搜索营销类智能体）
- 关系输入框（el-input，placeholder："如：姐姐、朋友、搭档"）
- 预览区显示已选智能体的头像和名称
- 确定/取消按钮

2.3 样式：关系列表使用紧凑的 flex 行布局，头像 32x32 圆形，名称加粗，关系文字灰色

### 步骤 3：后端接口预留

3.1 数据已通过现有 `config_data` 保存在 `streamer_config` 表中，无需新增表结构

3.2 后端 `StreamerController` 的 create/update 方法已透传 `config_data`，无需修改

3.3 预留未来查询接口（本次不实现，仅注释标注）：
```php
// 预留：GET /api/v1/streamer/:id/relationships
// 根据智能体ID查询其人际关系，返回关联智能体的详细信息
```

### 步骤 4：交互细节

- 切换智能体类型时，`createDefaultForm()` 会重置 `relationships` 为空数组
- 切换配置时，`setFormValues` 会加载已保存的关系数据
- 编辑关系弹窗中，选择目标智能体后自动填入名称和头像，关系需手动填写
- 不允许绑定自己（排除当前配置ID）
- 不允许重复绑定同一个智能体
- 「人际关系」仅显示在营销类智能体的基本信息卡片中

## 技术要点

| 层级 | 说明 |
|------|------|
| 存储 | `config_data.relationships` JSON数组，复用现有表结构 |
| 前端 | `form.relationships` 数组，Vue响应式绑定 |
| 加载 | `setFormValues` 从后端配置中恢复 |
| 保存 | `getFormSnapshot` 序列化后随 `config_data` 提交 |
| 查询 | 通过 `myConfigs` 获取可选营销智能体列表 |
| 未来API | 预留 `/api/v1/streamer/:id/relationships` 接口注释 |
