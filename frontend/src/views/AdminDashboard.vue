<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { getUsersConfigs, toggleTrainingStatus, getUsers, toggleUserStatus, kickUser, assignConfig, setUserQuota, getAdminRecharges, confirmRecharge, rejectRecharge, updateTrc20Config } from '../api/admin'
import { getTrc20Address } from '../api/payment'
import { useUserStore } from '../stores/user'
import { View, ArrowLeft } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'

const router = useRouter()
const userStore = useUserStore()

const activeTab = ref('configs')

const configs = ref([])
const loading = ref(false)
const currentPage = ref(1)
const pageSize = ref(10)
const total = ref(0)

const detailVisible = ref(false)
const currentConfig = ref(null)

const users = ref([])
const usersLoading = ref(false)

const assignDialogVisible = ref(false)
const assignTargetUserId = ref('')
const assignConfigId = ref(null)

const rechargeRecords = ref([])
const rechargeLoading = ref(false)
const trc20Addr = ref('')
const trc20Min = ref(50)

async function fetchRecharges() {
  rechargeLoading.value = true
  try {
    const res = await getAdminRecharges()
    rechargeRecords.value = res.data?.list ?? []
  } finally {
    rechargeLoading.value = false
  }
}

async function fetchTrc20Config() {
  try {
    const res = await getTrc20Address()
    trc20Addr.value = res.data?.address ?? ''
    trc20Min.value = res.data?.min_amount ?? 50
  } catch { /* ignore */ }
}

async function handleConfirmRecharge(record) {
  try {
    await confirmRecharge(record.id)
    ElMessage.success('已确认到账')
    await fetchRecharges()
  } catch { /* handled */ }
}

async function handleQuotaChange(user, newQuota) {
  try {
    await setUserQuota(user.id, newQuota)
    ElMessage.success(`已设置 ${user.nickname || user.username} 的配额为 ${newQuota}`)
  } catch { /* handled */ }
}

async function handleRejectRecharge(record) {
  try {
    await rejectRecharge(record.id, '')
    ElMessage.success('已驳回')
    await fetchRecharges()
  } catch { /* handled */ }
}

async function handleSaveTrc20Config() {
  try {
    await updateTrc20Config({ address: trc20Addr.value, min_amount: trc20Min.value })
    ElMessage.success('TRC20配置已保存')
  } catch { /* handled */ }
}

const selectedUserId = ref(null)

const configUserGroups = computed(() => {
  const map = {}
  for (const c of configs.value) {
    const uid = c.user_id
    if (!map[uid]) map[uid] = { username: c.username || '-', nickname: c.nickname || '-', configs: [] }
    map[uid].configs.push(c)
  }
  return Object.entries(map).map(([uid, v]) => ({ userId: uid, ...v, count: v.configs.length }))
})

const filteredConfigs = computed(() => {
  if (!selectedUserId.value) return configs.value
  return configs.value.filter(c => String(c.user_id) === String(selectedUserId.value))
})

async function fetchConfigs() {
  loading.value = true
  try {
    const res = await getUsersConfigs({
      pageNum: currentPage.value,
      pageSize: pageSize.value
    })
    const data = res.data || res
    configs.value = data.list || data.data || data || []
    total.value = data.total || configs.value.length || 0
  } catch (error) {
    // handled in interceptor
  } finally {
    loading.value = false
  }
}

function handlePageChange(page) {
  currentPage.value = page
  fetchConfigs()
}

function handleSizeChange(size) {
  pageSize.value = size
  currentPage.value = 1
  fetchConfigs()
}

function viewDetail(config) {
  const detail = config.config || config
  currentConfig.value = {
    name: detail.name || config.name,
    username: config.username || detail.username || '-',
    nickname: config.nickname || detail.nickname || '-',
    config: detail,
    created_at: config.created_at || config.create_time,
    updated_at: config.updated_at || config.update_time
  }
  detailVisible.value = true
}

async function handleToggleTraining(config) {
  const isTraining = config.training_status === 'training'
  const action = isTraining ? '取消训练' : '设为训练中'
  try {
    await ElMessageBox.confirm(
      isTraining ? '确定要取消该智能体的训练状态吗？' : '设为训练中后，该智能体的所有编辑操作将被锁定。确定继续？',
      action,
      { confirmButtonText: action, cancelButtonText: '取消', type: 'warning' }
    )
    const res = await toggleTrainingStatus({
      id: config.id || config._id,
      training_status: isTraining ? 'normal' : 'training'
    })
    config.training_status = (res.data || res).training_status
    ElMessage.success(`${action}成功`)
  } catch (error) {
    if (error !== 'cancel') {
      // handled in interceptor
    }
  }
}

function getTrainingLabel(config) {
  return config.training_status === 'training' ? '训练中' : '正常'
}

function getTrainingType(config) {
  return config.training_status === 'training' ? 'training' : 'normal'
}

async function fetchUsers() {
  usersLoading.value = true
  try {
    const res = await getUsers()
    const data = res.data || res
    users.value = data.list || data || []
  } catch (error) {
    // handled in interceptor
  } finally {
    usersLoading.value = false
  }
}

async function handleToggleUserStatus(user) {
  try {
    const res = await toggleUserStatus(user.id)
    user.status = (res.data || res).status
    ElMessage.success(user.status === 1 ? '已启用' : '已禁用')
  } catch (error) {
    // handled in interceptor
  }
}

async function handleKickUser(user) {
  try {
    await ElMessageBox.confirm(
      `确定要踢出用户「${user.nickname || user.username}」吗？该用户的所有已登录设备将立即下线。`,
      '踢出下线',
      { confirmButtonText: '踢出', cancelButtonText: '取消', type: 'warning' }
    )
    await kickUser(user.id)
    user.token_version = (user.token_version || 0) + 1
    ElMessage.success('已踢出下线')
  } catch (error) {
    if (error !== 'cancel') {
      // handled in interceptor
    }
  }
}

async function openAssignDialog(config) {
  assignConfigId.value = config.id || config._id
  assignTargetUserId.value = ''
  if (!users.value.length) await fetchUsers()
  assignDialogVisible.value = true
}

async function handleAssign() {
  if (!assignTargetUserId.value) {
    ElMessage.warning('请选择目标用户')
    return
  }
  try {
    await assignConfig(assignConfigId.value, assignTargetUserId.value)
    ElMessage.success('分配成功')
    assignDialogVisible.value = false
    fetchConfigs()
  } catch (error) {
    // handled in interceptor
  }
}

function onTabChange(tab) {
  if (tab === 'users' && users.value.length === 0) {
    fetchUsers()
  }
  if (tab === 'recharges') {
    fetchTrc20Config()
    if (rechargeRecords.value.length === 0) {
      fetchRecharges()
    }
  }
}

const systemPromptText = ref('')

function buildSystemPromptForConfig(cfg) {
  if (!cfg) return ''

  const speedMap = { 1: '很慢', 2: '偏慢', 3: '适中', 4: '偏快', 5: '很快' }
  const lines = []

  lines.push('你是一个数字人直播主播，请严格按照以下设定进行角色扮演：')
  lines.push('')

  lines.push('【身份信息】')
  if (cfg.name) lines.push(`- 名字：${cfg.name}`)
  if (cfg.gender) lines.push(`- 性别：${cfg.gender}`)
  if (cfg.ageRange) lines.push(`- 年龄段：${cfg.ageRange}`)
  if (cfg.zodiac) lines.push(`- 星座：${cfg.zodiac}`)
  if (cfg.mbti) lines.push(`- MBTI：${cfg.mbti}`)
  if (cfg.hometown) lines.push(`- 家乡/地域：${cfg.hometown}`)
  if (cfg.country) lines.push(`- 所在国家：${cfg.country}`)
  if (cfg.nationality) lines.push(`- 国籍：${cfg.nationality}`)
  if (cfg.targetOfficialWebsite) lines.push(`- 引导目标官网：${cfg.targetOfficialWebsite}`)
  if (cfg.personaTags?.length) lines.push(`- 人设标签：${cfg.personaTags.join('、')}`)

  if (cfg.hairstyle || cfg.hairColor || cfg.dressStyle) {
    lines.push('')
    lines.push('【外貌形象】')
    if (cfg.hairstyle) lines.push(`- 发型：${cfg.hairstyle}`)
    if (cfg.hairColor) lines.push(`- 发色：${cfg.hairColor}`)
    if (cfg.dressStyle) lines.push(`- 着装风格：${cfg.dressStyle}`)
  }

  if (cfg.personality || cfg.speechStyle || cfg.catchphrase || cfg.toneWords) {
    lines.push('')
    lines.push('【性格与语言风格】')
    if (cfg.personality) lines.push(`- 性格：${cfg.personality}`)
    if (cfg.speechStyle) lines.push(`- 说话风格：${cfg.speechStyle}`)
    if (cfg.catchphrase) lines.push(`- 口头禅：${cfg.catchphrase}`)
    lines.push(`- 语速：${speedMap[cfg.speechSpeed] || '适中'}`)
    if (cfg.toneWords) lines.push(`- 常用语气词：${cfg.toneWords}`)
  }

  if (cfg.topics?.length || cfg.profession || cfg.hobbies?.length) {
    lines.push('')
    lines.push('【知识领域】')
    if (cfg.topics?.length) lines.push(`- 擅长话题：${cfg.topics.join('、')}`)
    if (cfg.profession) lines.push(`- 专业背景：${cfg.profession}`)
    if (cfg.hobbies?.length) lines.push(`- 兴趣爱好：${cfg.hobbies.join('、')}`)
  }

  if (cfg.backstory) {
    lines.push('')
    lines.push('【背景故事】')
    lines.push(cfg.backstory)
  }

  lines.push('')
  lines.push('【行为准则】')
  lines.push('- 始终保持角色设定，不要跳出角色')
  lines.push('- 用口语化、自然的方式与观众交流')
  lines.push('- 适当使用口头禅和语气词，让对话更生动')
  if (cfg.forbidden?.length) {
    lines.push(`- 严禁讨论以下话题：${cfg.forbidden.join('、')}`)
  }
  if (cfg.behaviorRules) {
    lines.push(`- ${cfg.behaviorRules.split('\n').join('\n- ')}`)
  }

  lines.push('')
  lines.push('现在，请以这个角色开始对话。')

  return lines.join('\n')
}

function formatJson(obj) {
  return JSON.stringify(obj, null, 2)
}

function formatTime(t) {
  if (!t) return '-'
  return new Date(t).toLocaleString('zh-CN')
}

onMounted(() => {
  fetchConfigs()
})
</script>

<template>
  <div>
    <div class="app-header">
      <div class="logo">
        <div class="logo-icon">🛡️</div>
        <div>
          <h1>管理后台</h1>
          <div class="subtitle">管理用户与智能体配置</div>
        </div>
      </div>
      <div style="display:flex;gap:8px;">
        <el-button plain size="small" @click="router.push('/')">
          <el-icon><ArrowLeft /></el-icon>
          返回编辑器
        </el-button>
      </div>
    </div>

    <div class="container">
      <el-tabs v-model="activeTab" @tab-change="onTabChange">
        <el-tab-pane label="智能体配置管理" name="configs">
          <div class="config-layout">
            <div class="config-left">
              <div
                class="user-card"
                :class="{ active: selectedUserId === null }"
                @click="selectedUserId = null"
              >
                <div class="user-card-icon">👥</div>
                <div class="user-card-info">
                  <div class="user-card-name">全部用户</div>
                  <div class="user-card-count">{{ configs.length }} 个配置</div>
                </div>
              </div>
              <div
                v-for="group in configUserGroups"
                :key="group.userId"
                class="user-card"
                :class="{ active: String(selectedUserId) === String(group.userId) }"
                @click="selectedUserId = group.userId"
              >
                <div class="user-card-icon">👤</div>
                <div class="user-card-info">
                  <div class="user-card-name">{{ group.nickname }} <span class="user-card-username">@{{ group.username }}</span></div>
                  <div class="user-card-count">{{ group.count }} 个配置</div>
                </div>
              </div>
            </div>
            <div class="config-right">
              <el-card v-loading="loading">
                <el-table :data="filteredConfigs" style="width:100%;">
                  <el-table-column prop="username" label="用户名" width="120" />
                  <el-table-column prop="nickname" label="昵称" width="120" />
                  <el-table-column prop="name" label="配置名称" min-width="180" />
                  <el-table-column label="类型" width="80">
                    <template #default="{ row }">
                      <span v-if="row.config_data?.agentType === 'inventory' || row.config?.agentType === 'inventory'">📦 进销存</span>
                      <span v-else-if="row.config_data?.agentType === 'secretary' || row.config?.agentType === 'secretary'">📡 秘书</span>
                      <span v-else>📢 营销</span>
                    </template>
                  </el-table-column>
                  <el-table-column label="训练" width="80">
                    <template #default="{ row }">
                      <el-tag :type="getTrainingType(row) === 'training' ? 'warning' : 'success'" size="small">
                        {{ getTrainingLabel(row) }}
                      </el-tag>
                    </template>
                  </el-table-column>
                  <el-table-column label="更新时间" width="160">
                    <template #default="{ row }">
                      {{ formatTime(row.updated_at || row.update_time) }}
                    </template>
                  </el-table-column>
                  <el-table-column label="操作" width="260" fixed="right">
                    <template #default="{ row }">
                      <el-button type="primary" link size="small" @click="viewDetail(row)">
                        <el-icon><View /></el-icon>
                        详情
                      </el-button>
                      <el-button
                        :type="row.training_status === 'training' ? 'success' : 'warning'"
                        link
                        size="small"
                        @click="handleToggleTraining(row)"
                      >
                        {{ row.training_status === 'training' ? '取消训练' : '设为训练中' }}
                      </el-button>
                      <el-button type="primary" link size="small" @click="openAssignDialog(row)">
                        分配
                      </el-button>
                    </template>
                  </el-table-column>
                  <template #empty>
                    <div style="padding:40px;text-align:center;color:var(--text-secondary);">
                      <p>该用户暂无配置</p>
                    </div>
                  </template>
                </el-table>
                <div style="display:flex;justify-content:flex-end;margin-top:16px;">
                  <el-pagination
                    v-model:current-page="currentPage"
                    v-model:page-size="pageSize"
                    :page-sizes="[10, 20, 50]"
                    :total="total"
                    layout="total, sizes, prev, pager, next"
                    @current-change="handlePageChange"
                    @size-change="handleSizeChange"
                  />
                </div>
              </el-card>
            </div>
          </div>
        </el-tab-pane>

        <el-tab-pane label="用户管理" name="users">
          <el-card v-loading="usersLoading">
            <el-table :data="users" style="width:100%;">
              <el-table-column prop="id" label="ID" width="60" />
              <el-table-column prop="username" label="用户名" width="140" />
              <el-table-column prop="nickname" label="昵称" width="140" />
              <el-table-column prop="email" label="邮箱" min-width="180" />
              <el-table-column label="角色" width="80">
                <template #default="{ row }">
                  <el-tag :type="row.role === 'admin' ? 'danger' : 'info'" size="small">
                    {{ row.role === 'admin' ? '管理员' : '用户' }}
                  </el-tag>
                </template>
              </el-table-column>
              <el-table-column label="配额" width="100">
                <template #default="{ row }">
                  <el-input-number
                    :model-value="row.agent_quota"
                    :min="1"
                    size="small"
                    controls-position="right"
                    style="width:90px;"
                    :disabled="row.role === 'admin'"
                    @change="handleQuotaChange(row, $event)"
                  />
                </template>
              </el-table-column>
              <el-table-column label="状态" width="80">
                <template #default="{ row }">
                  <el-switch
                    :model-value="row.status === 1"
                    :disabled="row.role === 'admin'"
                    @change="handleToggleUserStatus(row)"
                  />
                </template>
              </el-table-column>
              <el-table-column label="注册时间" width="170">
                <template #default="{ row }">
                  {{ formatTime(row.create_time) }}
                </template>
              </el-table-column>
              <el-table-column label="操作" width="120">
                <template #default="{ row }">
                  <el-button
                    type="danger"
                    link
                    size="small"
                    :disabled="row.role === 'admin'"
                    @click="handleKickUser(row)"
                  >
                    踢出下线
                  </el-button>
                </template>
              </el-table-column>
              <template #empty>
                <div style="padding:40px;text-align:center;color:var(--text-secondary);">
                  <p>暂无用户</p>
                </div>
              </template>
            </el-table>
          </el-card>
        </el-tab-pane>
        <el-tab-pane label="充值审核" name="recharges">
          <el-card>
            <template #header>
              <span style="font-weight:600;">🏦 TRC20 收款配置</span>
            </template>
            <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
              <span style="font-size:13px;font-weight:600;color:var(--text-secondary);white-space:nowrap;">收款地址</span>
              <el-input v-model="trc20Addr" placeholder="TRC20-USDT 收款地址" style="flex:1;min-width:260px;max-width:420px;" />
              <span style="font-size:13px;font-weight:600;color:var(--text-secondary);white-space:nowrap;">最低充值</span>
              <el-input-number v-model="trc20Min" :min="1" style="width:100px;" />
              <span>$</span>
              <el-button type="primary" @click="handleSaveTrc20Config">保存</el-button>
            </div>
          </el-card>

          <el-card style="margin-top:16px;" v-loading="rechargeLoading">
            <template #header>
              <span style="font-weight:600;">📋 充值申请</span>
              <el-tag size="small" style="margin-left:8px;">{{ rechargeRecords.filter(r => r.status === 'pending').length }} 待审核</el-tag>
            </template>
            <el-table :data="rechargeRecords" style="width:100%;">
              <el-table-column prop="username" label="用户" width="120" />
              <el-table-column label="金额" width="100">
                <template #default="{ row }">${{ row.amount }}</template>
              </el-table-column>
              <el-table-column prop="tx_hash" label="TxHash" min-width="200">
                <template #default="{ row }">
                  <span style="font-family:monospace;font-size:12px;">{{ row.tx_hash }}</span>
                </template>
              </el-table-column>
              <el-table-column label="状态" width="90">
                <template #default="{ row }">
                  <el-tag :type="row.status === 'pending' ? 'warning' : row.status === 'confirmed' ? 'success' : 'danger'" size="small">
                    {{ row.status === 'pending' ? '待审核' : row.status === 'confirmed' ? '已到账' : '已驳回' }}
                  </el-tag>
                </template>
              </el-table-column>
              <el-table-column label="时间" width="160">
                <template #default="{ row }">{{ formatTime(row.create_time) }}</template>
              </el-table-column>
              <el-table-column label="操作" width="160" v-if="rechargeRecords.some(r => r.status === 'pending')">
                <template #default="{ row }">
                  <template v-if="row.status === 'pending'">
                    <el-button type="success" size="small" @click="handleConfirmRecharge(row)">确认</el-button>
                    <el-button type="danger" size="small" @click="handleRejectRecharge(row)">驳回</el-button>
                  </template>
                  <span v-else style="font-size:12px;color:var(--text-secondary);">
                    {{ row.remark || '-' }}
                  </span>
                </template>
              </el-table-column>
              <template #empty>
                <div style="padding:40px;text-align:center;color:var(--text-secondary);">暂无充值申请</div>
              </template>
            </el-table>
          </el-card>
        </el-tab-pane>
      </el-tabs>
    </div>

    <el-dialog v-model="detailVisible" title="配置详情" width="700px">
      <template v-if="currentConfig">
        <el-descriptions :column="2" border style="margin-bottom:16px;">
          <el-descriptions-item label="用户名">{{ currentConfig.username }}</el-descriptions-item>
          <el-descriptions-item label="昵称">{{ currentConfig.nickname }}</el-descriptions-item>
          <el-descriptions-item label="配置名称">{{ currentConfig.name }}</el-descriptions-item>
          <el-descriptions-item label="创建时间">{{ formatTime(currentConfig.created_at) }}</el-descriptions-item>
        </el-descriptions>
        <el-divider content-position="left">配置 JSON</el-divider>
        <div class="prompt-box">{{ formatJson(currentConfig.config) }}</div>
        <el-divider content-position="left">System Prompt</el-divider>
        <div class="prompt-box">{{ buildSystemPromptForConfig(currentConfig.config) }}</div>
      </template>
      <template #footer>
        <el-button @click="detailVisible = false">关闭</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="assignDialogVisible" title="分配智能体" width="420px">
      <el-form-item label="目标用户">
        <el-select v-model="assignTargetUserId" placeholder="搜索用户" filterable style="width:100%;">
          <el-option v-for="u in users" :key="u.id" :label="`${u.nickname || u.username} (@${u.username})`" :value="u.id" />
        </el-select>
      </el-form-item>
      <template #footer>
        <el-button @click="assignDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleAssign">确定分配</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
.app-header {
  background: linear-gradient(135deg, rgba(108, 92, 231, 0.08), rgba(0, 206, 201, 0.05));
  border-bottom: 1px solid var(--card-border);
  padding: 16px 32px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  backdrop-filter: blur(20px);
}

.logo {
  display: flex;
  align-items: center;
  gap: 12px;
}

.logo-icon {
  width: 40px;
  height: 40px;
  background: linear-gradient(135deg, var(--accent), var(--success));
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
}

.app-header h1 {
  font-size: 20px;
  font-weight: 600;
  margin: 0;
  color: var(--text);
}

.subtitle {
  font-size: 13px;
  color: var(--text-secondary);
}

.container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 24px 32px;
}

:deep(.el-tabs__item) {
  color: var(--text);
  font-size: 14px;
  font-weight: 500;
}

:deep(.el-tabs__item.is-active) {
  color: var(--accent);
}

.config-layout {
  display: flex;
  gap: 16px;
  align-items: flex-start;
}

.config-left {
  width: 220px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.user-card {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  background: var(--bg-card, rgba(255,255,255,0.04));
  border: 1px solid var(--card-border);
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
}

.user-card:hover {
  border-color: var(--accent);
  background: rgba(108, 92, 231, 0.06);
}

.user-card.active {
  border-color: var(--accent);
  background: rgba(108, 92, 231, 0.12);
}

.user-card-icon {
  font-size: 22px;
  flex-shrink: 0;
}

.user-card-info {
  flex: 1;
  min-width: 0;
}

.user-card-name {
  font-size: 13px;
  font-weight: 600;
  color: var(--text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.user-card-username {
  font-weight: 400;
  color: var(--text-secondary);
  font-size: 11px;
}

.user-card-count {
  font-size: 11px;
  color: var(--text-secondary);
  margin-top: 2px;
}

.config-right {
  flex: 1;
  min-width: 0;
}

.prompt-box {
  background: #0a0a12;
  border: 1px solid var(--card-border);
  border-radius: var(--radius-sm);
  padding: 16px;
  font-size: 13px;
  line-height: 1.7;
  color: #b8c8d8;
  max-height: 300px;
  overflow-y: auto;
  white-space: pre-wrap;
  word-break: break-word;
  font-family: "SF Mono", "Cascadia Code", "Consolas", "Microsoft YaHei", monospace;
}

/* 手机端适配 */
@media (max-width: 768px) {
  .app-header {
    padding: 12px 16px;
    flex-direction: column;
    gap: 8px;
    align-items: flex-start;
  }
  .app-header h1 { font-size: 16px; }
  .container {
    padding: 12px;
  }
  .config-layout {
    flex-direction: column;
  }
  .config-left {
    width: 100%;
    flex-direction: row;
    overflow-x: auto;
    gap: 6px;
    padding-bottom: 4px;
  }
  .user-card {
    flex-shrink: 0;
    min-width: 130px;
    padding: 8px 10px;
  }
  .config-right {
    width: 100%;
  }
  /* 表格水平滚动 */
  :deep(.el-table) {
    display: block;
    overflow-x: auto;
  }
  :deep(.el-card__body) {
    padding: 12px;
  }
  :deep(.el-pagination) {
    flex-wrap: wrap;
    justify-content: center !important;
    gap: 4px;
  }
  :deep(.el-dialog) {
    width: 95% !important;
    margin-top: 10vh !important;
  }
  :deep(.el-descriptions) {
    --el-descriptions-item-bordered-label-background: transparent;
  }
}
</style>
