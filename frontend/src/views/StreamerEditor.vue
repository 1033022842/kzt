<script setup>
import { ref, reactive, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useUserStore } from '../stores/user'
import { createConfig, updateConfig, getMyConfigs, getConfigDetail, uploadAvatar } from '../api/streamer'
import { getBalance } from '../api/payment'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  User, DocumentCopy, Download, ArrowDown, Refresh
} from '@element-plus/icons-vue'

const router = useRouter()
const route = useRoute()
const userStore = useUserStore()
const DRAFT_STORAGE_PREFIX = 'streamerEditorDraft'

const activePreset = ref('')

function createDefaultForm() {
  return {
    agentType: 'marketing',
    avatar: '',
    name: '',
    gender: '',
    ageRange: '',
    zodiac: '',
    mbti: '',
    hometown: '',
    country: '',
    nationality: '',
    targetOfficialWebsite: '',
    personaTags: [],
    hairstyle: '',
    hairColor: '',
    dressStyle: '',
    personality: '',
    speechStyle: '',
    catchphrase: '',
    speechSpeed: 3,
    toneWords: '',
    topics: [],
    profession: '',
    hobbies: [],
    backstory: '',
    forbidden: [],
    behaviorRules: '',
    boundPurchaseUrl: '',
    purchaseAccount: '',
    purchasePassword: '',
    inventoryAccounts: [],
    tgChannel: '',
    reportTime: '08:00',
    reportScopes: ['marketing', 'inventory'],
    relationships: []
  }
}

const inventoryAccounts = ref([])

const purchaseLogs = ref([])
const marketingLogs = ref([])
const purchaseLogTimer = ref(null)
const marketingLogTimer = ref(null)
const platformBalance = ref(0)
const agentQuota = ref(1)
const agentUsed = ref(0)
const agentAvailable = computed(() => Math.max(0, agentQuota.value - agentUsed.value))
const purchaseUrlLocked = ref(false)
const reportFrequency = ref('daily')
const reportHour = ref('08')
const reportMinute = ref('00')
const reportInterval = ref(30)
const reportDays = ref([1, 2, 3, 4, 5, 6, 0])
const weekDayOptions = [
  { value: 1, label: '周一' }, { value: 2, label: '周二' }, { value: 3, label: '周三' },
  { value: 4, label: '周四' }, { value: 5, label: '周五' }, { value: 6, label: '周六' }, { value: 0, label: '周日' }
]
const MAX_LOG_LINES = 50

function generateMockPurchaseLogs() {
  if (!form.boundPurchaseUrl && !form.purchaseAccount && !form.purchasePassword) {
    purchaseLogs.value = []
    stopPurchaseDynamics()
    return
  }
  const now = Date.now()
  const url = form.boundPurchaseUrl || '目标网址'
  const account = form.purchaseAccount || '未配置'
  const logs = [
    { time: now - 180000, type: 'info', msg: `[系统] 已连接至购买平台 ${url}` },
    { time: now - 150000, type: 'info', msg: `[系统] 使用账号 ${account} 登录中...` },
    { time: now - 140000, type: 'success', msg: `[系统] 登录成功，开始检索可用账号列表` },
    { time: now - 120000, type: 'info', msg: '[检索] 正在扫描可用账号列表...' },
    { time: now - 80000, type: 'success', msg: '[检索] 发现 23 个待购买账号' },
    { time: now - 50000, type: 'success', msg: '[购买] ✅ 成功购入账号，已加入库存' },
    { time: now - 10000, type: 'info', msg: '[系统] 脚本运行中，自动扫描间隔 15s' },
  ]
  purchaseLogs.value = logs
  platformBalance.value = 500
  startPurchaseDynamics()
}

function appendPurchaseLog() {
  const now = Date.now()
  const platforms = ['WhatsApp', 'Telegram', 'Facebook', 'Instagram', 'Twitter', 'Line']
  const platform = platforms[Math.floor(Math.random() * platforms.length)]
  const rand = Math.random()
  let entry
  if (platformBalance.value <= 0) {
    entry = { time: now, type: 'error', msg: '[系统] 平台余额不足，已暂停自动购买，请充值后继续' }
    stopPurchaseDynamics()
    purchaseLogs.value = [...purchaseLogs.value.slice(-(MAX_LOG_LINES - 1)), entry]
    return
  }
  if (rand < 0.4) {
    const accId = `${platform.toLowerCase()}_user_${1000 + Math.floor(Math.random() * 9000)}`
    entry = { time: now, type: 'info', msg: `[检索] 正在购买 ${platform} 账号: ${accId}` }
  } else if (rand < 0.75) {
    const accId = `${platform.toLowerCase()}_buyer_${6000 + Math.floor(Math.random() * 4000)}`
    const cost = 30 + Math.floor(Math.random() * 70)
    platformBalance.value = Math.max(0, platformBalance.value - cost)
    entry = { time: now, type: 'success', msg: `[购买] ✅ 成功购入 ${platform} 账号: ${accId}，消费 $${cost}，余额 $${platformBalance.value}` }
  } else if (rand < 0.9) {
    entry = { time: now, type: 'warning', msg: '[系统] 库存已更新，等待人工分配' }
  } else {
    entry = { time: now, type: 'info', msg: `[系统] 正在扫描 ${platform} 平台新账号...` }
  }
  purchaseLogs.value = [...purchaseLogs.value.slice(-(MAX_LOG_LINES - 1)), entry]
}

function startPurchaseDynamics() {
  stopPurchaseDynamics()
  purchaseLogTimer.value = setInterval(() => { appendPurchaseLog() }, 10000 + Math.random() * 8000)
}

function stopPurchaseDynamics() {
  if (purchaseLogTimer.value) {
    clearInterval(purchaseLogTimer.value)
    purchaseLogTimer.value = null
  }
}

function formatLogTime(ts) {
  const d = new Date(ts)
  return d.toLocaleTimeString('zh-CN', { hour12: false })
}

function parseReportTime(raw) {
  const match = raw?.match(/^(daily|weekly|interval_min|interval_hour):(\S+)$/)
  if (match) {
    reportFrequency.value = match[1]
    if (match[1] === 'daily') {
      const [hh = '08', mm = '00'] = match[2].split(':')
      reportHour.value = hh; reportMinute.value = mm
    } else if (match[1] === 'weekly') {
      const parts = match[2].split(':')
      reportHour.value = parts[0] || '08'; reportMinute.value = parts[1] || '00'
      reportDays.value = parts[2] ? parts[2].split(',').map(Number) : [1,2,3,4,5,6,0]
    } else {
      reportInterval.value = parseInt(match[2]) || 30
    }
  } else {
    const parts = (raw || '08:00').split(':')
    reportFrequency.value = 'daily'
    reportHour.value = parts[0] || '08'
    reportMinute.value = parts[1] || '00'
    reportDays.value = [1, 2, 3, 4, 5, 6, 0]
    reportInterval.value = 30
  }
}

function buildReportTime() {
  const hh = String(reportHour.value).padStart(2, '0')
  const mm = String(reportMinute.value).padStart(2, '0')
  if (reportFrequency.value === 'weekly') {
    const days = [...reportDays.value].sort((a, b) => a - b).join(',')
    return `weekly:${hh}:${mm}:${days}`
  }
  if (reportFrequency.value === 'interval_min') {
    return `interval_min:${reportInterval.value}`
  }
  if (reportFrequency.value === 'interval_hour') {
    return `interval_hour:${reportInterval.value}`
  }
  return `daily:${hh}:${mm}`
}

function onReportSettingChange() {
  form.reportTime = buildReportTime()
}

function getReportTimeDisplay() {
  const hh = String(reportHour.value).padStart(2, '0')
  const mm = String(reportMinute.value).padStart(2, '0')
  if (reportFrequency.value === 'interval_min') return `每 ${reportInterval.value} 分钟执行一次`
  if (reportFrequency.value === 'interval_hour') return `每 ${reportInterval.value} 小时执行一次`
  if (reportFrequency.value === 'weekly') {
    const labels = reportDays.value.map(d => weekDayOptions.find(w => w.value === d)?.label || '').filter(Boolean)
    return `每周 ${labels.join('/')} ${hh}:${mm}`
  }
  return `每天 ${hh}:${mm}`
}

function generateMockMarketingLogs(boundCount) {
  if (!boundCount || boundCount <= 0) {
    marketingLogs.value = []
    stopMarketingDynamics()
    return
  }
  const now = Date.now()
  const configName = form.name || '当前配置'
  const logs = [
    { time: now - 300000, type: 'info', msg: `[系统] 智能体「${configName}」已启动，开始执行营销任务` },
    { time: now - 270000, type: 'success', msg: `[系统] 已绑定 ${boundCount} 个账号，同步账号状态完成` },
    { time: now - 150000, type: 'success', msg: '[推广] ✅ 消息推广完成' },
    { time: now - 90000, type: 'info', msg: '[互动] 正在监测群组消息...' },
    { time: now - 60000, type: 'success', msg: '[互动] ✅ 群组互动完成' },
    { time: now - 30000, type: 'info', msg: '[回复] 处理私信回复...' },
    { time: now - 10000, type: 'info', msg: `[系统] 智能体运行中 · ${boundCount} 个账号在线` },
  ]
  marketingLogs.value = logs
  startMarketingDynamics(boundCount)
}

function appendMarketingLog() {
  const now = Date.now()
  const platforms = ['WhatsApp', 'Telegram', 'Facebook', 'Instagram']
  const actions = ['消息推广', '群组互动', '私信回复', '状态监控']
  const platform = platforms[Math.floor(Math.random() * platforms.length)]
  const action = actions[Math.floor(Math.random() * actions.length)]
  const rand = Math.random()
  let entry
  if (rand < 0.5) {
    entry = { time: now, type: 'success', msg: `[${action}] ✅ ${platform} 平台${action}完成` }
  } else if (rand < 0.8) {
    entry = { time: now, type: 'info', msg: `[${action}] ${platform} 平台${action}进行中...` }
  } else if (rand < 0.95) {
    entry = { time: now, type: 'warning', msg: `[${action}] ${platform} 平台响应延迟，重试中` }
  } else {
    entry = { time: now, type: 'info', msg: `[系统] ${platform} 平台数据同步完成` }
  }
  marketingLogs.value = [...marketingLogs.value.slice(-(MAX_LOG_LINES - 1)), entry]
}

function startMarketingDynamics(boundCount) {
  stopMarketingDynamics()
  marketingLogTimer.value = setInterval(() => { appendMarketingLog() }, 8000 + Math.random() * 6000)
}

function stopMarketingDynamics() {
  if (marketingLogTimer.value) {
    clearInterval(marketingLogTimer.value)
    marketingLogTimer.value = null
  }
}

function clearAllLogTimers() {
  stopPurchaseDynamics()
  stopMarketingDynamics()
}

async function confirmPurchaseUrl() {
  if (!form.boundPurchaseUrl.trim()) {
    ElMessage.warning('请填写购买网址')
    return
  }
  if (!form.purchaseAccount.trim()) {
    ElMessage.warning('请填写登录账号')
    return
  }
  if (!form.purchasePassword.trim()) {
    ElMessage.warning('请填写登录密码')
    return
  }
  saving.value = true
  try {
    const autoName = `进销存-${form.boundPurchaseUrl.replace(/https?:\/\//, '').slice(0, 20)}`
    const configData = { ...form }
    configData.inventoryAccounts = [...inventoryAccounts.value]
    const data = {
      avatar: form.avatar,
      name: form.name || autoName,
      config_data: configData,
      system_prompt: systemPrompt.value || '[进销存类智能体配置]'
    }
    if (configId.value) {
      data.id = configId.value
      await updateConfig(data)
    } else {
      const res = await createConfig(data)
      configId.value = res.data?.id || res.id
    }
    selectedConfigId.value = String(configId.value || selectedConfigId.value || '')
    await fetchMyConfigs()
    purchaseUrlLocked.value = true
    ElMessage.success('购买网址已锁定')
  } catch (error) {
    // handled in interceptor
  } finally {
    saving.value = false
  }
}

function editPurchaseUrl() {
  purchaseUrlLocked.value = false
}

function generateFakePassword() {
  const chars = 'abcdefghijklmnopqrstuvwxyz0123456789!@#'
  let pw = ''
  for (let i = 0; i < 10; i++) {
    pw += chars[Math.floor(Math.random() * chars.length)]
  }
  return pw
}

const addInventoryDialogVisible = ref(false)
const newInventoryAccount = reactive({ platform: '', accountId: '', password: '' })

function openAddInventoryDialog() {
  newInventoryAccount.platform = ''
  newInventoryAccount.accountId = ''
  newInventoryAccount.password = ''
  addInventoryDialogVisible.value = true
}

function confirmAddInventory() {
  if (!newInventoryAccount.accountId.trim()) {
    ElMessage.warning('请输入账号ID')
    return
  }
  inventoryAccounts.value.push({
    id: Date.now(),
    platform: newInventoryAccount.platform,
    accountId: newInventoryAccount.accountId.trim(),
    password: newInventoryAccount.password || generateFakePassword(),
    status: '在库',
    assignedTo: '',
    assignedAt: ''
  })
  addInventoryDialogVisible.value = false
  autoSaveInventory()
}

async function autoSaveInventory() {
  const configData = { ...form }
  configData.inventoryAccounts = [...inventoryAccounts.value]
  const data = {
    avatar: form.avatar,
    name: form.name || form.boundPurchaseUrl ? `进销存-${form.boundPurchaseUrl.replace(/https?:\/\//, '').slice(0, 20)}` : '进销存配置',
    config_data: configData,
    system_prompt: '[进销存类智能体配置]'
  }
  try {
    if (configId.value) {
      data.id = configId.value
      await updateConfig(data)
    } else {
      const res = await createConfig(data)
      configId.value = res.data?.id || res.id
    }
    selectedConfigId.value = String(configId.value || selectedConfigId.value || '')
    await fetchMyConfigs()
    ElMessage.success('进货成功，已自动保存')
  } catch (error) {
    ElMessage.success('进货成功（保存失败，请手动保存）')
  }
}

async function deleteInventoryAccount(accountId) {
  const account = inventoryAccounts.value.find(a => a.id === accountId)
  if (account && account.status === '已分配') {
    ElMessage.warning('已分配的账号不能删除')
    return
  }
  inventoryAccounts.value = inventoryAccounts.value.filter(a => a.id !== accountId)
  ElMessage.success('已删除')
  autoSaveInventory()
}

const assignDialogVisible = ref(false)
const assignTargetAgent = ref('')
const assigningAccountId = ref(null)

function openAssignDialog(accountId) {
  assigningAccountId.value = accountId
  assignTargetAgent.value = ''
  assignDialogVisible.value = true
}

async function confirmAssign() {
  if (!assignTargetAgent.value) {
    ElMessage.warning('请选择目标营销智能体')
    return
  }
  const account = inventoryAccounts.value.find(a => a.id === assigningAccountId.value)
  if (account) {
    account.status = '已分配'
    account.assignedTo = assignTargetAgent.value
    account.assignedAt = new Date().toLocaleString('zh-CN')
    account.bound = false
  }
  assignDialogVisible.value = false
  autoSaveInventory()
}

function getAssignableMarketingAgents() {
  const agents = []
  for (const item of myConfigs.value) {
    const type = getConfigType(item)
    if (type === 'marketing') {
      const name = item.name || item.config_data?.name || item.config?.name || ''
      if (name) agents.push(name)
    }
  }
  return agents
}

const mockSettlementData = ref([])
const purchaseChannelBalance = ref(50000)

function generateMockSettlement() {
  const names = ['小T', 'Mia', '陈老师', '阿飞', '大嘴']
  const result = []
  for (const name of names) {
    const total = Math.floor(Math.random() * 10 + 2)
    result.push({
      name,
      type: '营销类',
      accountsToday: Math.floor(Math.random() * 3 + 1),
      accountsTotal: total
    })
  }
  purchaseChannelBalance.value = Math.floor(Math.random() * 80000 + 10000)
  return result
}

const totalAssignedToday = computed(() => mockSettlementData.value.reduce((sum, r) => sum + r.accountsToday, 0))

// ====== 秘书 AI 对话 ======
const secretaryMessages = ref([])
const secretaryInput = ref('')
const secretaryAsking = ref(false)
const secretaryChatRef = ref(null)

function getWelcomeMessage() {
  return '你好！我是你的 AI 数据秘书，可以帮你查询智能体运行情况。\n\n你可以试着问我：\n• "我现在有多少个智能体？"\n• "营销智能体运行得怎么样？"\n• "今天的结算数据如何？"\n• "我的库存情况怎么样？"'
}

function mockSecretaryReply(userMessage) {
  const msg = userMessage.toLowerCase()

  // 智能体数量/概况
  if (/多少|几个|数量|智能体|概览|全部/.test(msg)) {
    const m = groupedConfigs.value.marketing.length
    const i = groupedConfigs.value.inventory.length
    const s = groupedConfigs.value.secretary.length
    const total = m + i + s
    let reply = `📊 **智能体概览**\n\n当前账号共 ${total} 个智能体（配额 ${agentUsed.value}/${agentQuota.value}）。\n\n`
    if (m > 0) {
      reply += `**营销类（${m}个）：**\n`
      for (const c of groupedConfigs.value.marketing) {
        reply += `  • ${c.name || '未命名'} — ${c.training_status === 'training' ? '训练中' : '运行中'}\n`
      }
    }
    if (i > 0) {
      reply += `\n**进销存类（${i}个）：**\n`
      for (const c of groupedConfigs.value.inventory) {
        const accs = c.config_data?.inventoryAccounts || c.config?.inventoryAccounts || []
        reply += `  • ${c.name || '未命名'} — 库存 ${accs.length} 个\n`
      }
    }
    if (s > 0) {
      reply += `\n**秘书类（${s}个）：**\n`
      for (const c of groupedConfigs.value.secretary) {
        const tg = c.config_data?.tgChannel || c.config?.tgChannel || ''
        reply += `  • ${c.name || '未命名'} — ${tg ? '频道 ' + tg : '未绑定频道'}\n`
      }
    }
    if (total === 0) reply = '你目前还没有创建任何智能体，可以先创建营销类或进销存类智能体。'
    return reply
  }

  // 营销
  if (/营销|推广|账号|绑定/.test(msg)) {
    const list = groupedConfigs.value.marketing
    if (list.length === 0) return '暂未创建营销类智能体，需要我帮你创建一个吗？'
    let reply = `📢 **营销智能体（${list.length}个）**\n\n`
    for (const c of list) {
      const boundCount = 0 // 当前只针对 form 中的智能体计算，这里取 0 简单处理
      reply += `• ${c.name || '未命名'}：${c.training_status === 'training' ? '⏳ 训练中' : '✅ 运行中'}\n`
    }
    if (marketingLogs.value.length > 0) {
      reply += `\n📋 最近运行日志：\n`
      const recent = marketingLogs.value.slice(-3)
      for (const log of recent) {
        const icon = log.type === 'success' ? '✅' : log.type === 'warning' ? '⚠️' : 'ℹ️'
        reply += `${icon} ${log.msg}\n`
      }
    }
    return reply
  }

  // 库存/进销存
  if (/库存|进销存|进货|采购/.test(msg)) {
    const list = groupedConfigs.value.inventory
    if (list.length === 0) return '暂未创建进销存类智能体。'
    let reply = `📦 **进销存智能体（${list.length}个）**\n\n`
    for (const c of list) {
      const accs = c.config_data?.inventoryAccounts || c.config?.inventoryAccounts || []
      const inStock = accs.filter(a => a.status === '在库').length
      reply += `• ${c.name || '未命名'}：${accs.length} 个账号（在库 ${inStock}）\n`
    }
    reply += `\n💰 进货通道余额：¥${purchaseChannelBalance.value.toLocaleString()}`
    return reply
  }

  // 结算/报表
  if (/结算|报表|盈利|数据|今天|每日/.test(msg)) {
    if (mockSettlementData.value.length === 0) return '暂未有结算数据。'
    let reply = `📈 **今日结算概览**\n\n`
    for (const item of mockSettlementData.value) {
      reply += `• ${item.name}：今日 ${item.accountsToday} 个 / 累计 ${item.accountsTotal} 个\n`
    }
    reply += `\n📤 今日总分配：${totalAssignedToday.value} 个账号`
    reply += `\n💰 进货通道余额：¥${purchaseChannelBalance.value.toLocaleString()}`
    return reply
  }

  // 配额
  if (/配额|余额|还能|剩余/.test(msg)) {
    return `💳 **账户配额**\n\n• 智能体配额：${agentUsed.value} / ${agentQuota.value}（剩余 ${agentQuota.value - agentUsed.value} 个）\n• 进货通道余额：¥${purchaseChannelBalance.value.toLocaleString()}`
  }

  // 秘书/TG
  if (/秘书|tg|频道|汇报|推送/.test(msg)) {
    const list = groupedConfigs.value.secretary
    if (list.length === 0) return '暂未创建秘书类智能体。'
    let reply = `📡 **秘书智能体（${list.length}个）**\n\n`
    for (const c of list) {
      const tg = c.config_data?.tgChannel || c.config?.tgChannel || ''
      reply += `• ${c.name || '未命名'}：${tg || '未绑定频道'}\n`
    }
    return reply
  }

  // 运行/状态
  if (/运行|状态|日志/.test(msg)) {
    if (marketingLogs.value.length === 0) return '当前暂无运行中的智能体日志，请先创建并绑定营销智能体。'
    let reply = `🟢 **运行状态**\n\n最近日志：\n`
    const recent = marketingLogs.value.slice(-5)
    for (const log of recent) {
      const time = new Date(log.time).toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
      const icon = log.type === 'success' ? '✅' : log.type === 'warning' ? '⚠️' : 'ℹ️'
      reply += `[${time}] ${icon} ${log.msg}\n`
    }
    return reply
  }

  // 默认
  return `抱歉，我不太理解你的问题。你可以试试问我：\n\n• "我有多少个智能体？"\n• "营销情况怎么样？"\n• "今天结算数据"\n• "库存情况"\n• "配额还剩多少？"`
}

function sendSecretaryMessage() {
  const input = secretaryInput.value.trim()
  if (!input || secretaryAsking.value) return

  secretaryMessages.value.push({ role: 'user', content: input })
  secretaryInput.value = ''
  secretaryAsking.value = true

  // 滚动到底部
  nextTick(() => {
    if (secretaryChatRef.value) {
      secretaryChatRef.value.scrollTop = secretaryChatRef.value.scrollHeight
    }
  })

  const delay = 600 + Math.random() * 900
  setTimeout(() => {
    const reply = mockSecretaryReply(input)
    secretaryMessages.value.push({ role: 'assistant', content: reply })
    secretaryAsking.value = false
    nextTick(() => {
      if (secretaryChatRef.value) {
        secretaryChatRef.value.scrollTop = secretaryChatRef.value.scrollHeight
      }
    })
  }, delay)
}

function handleSecretaryKeydown(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault()
    sendSecretaryMessage()
  }
}

// ====== 秘书 AI 对话 END ======

const form = reactive(createDefaultForm())

watch(
  () => [form.boundPurchaseUrl, form.purchaseAccount, form.purchasePassword],
  () => { generateMockPurchaseLogs() },
  { immediate: true }
)

const personaTagInput = ref('')
const topicsTagInput = ref('')
const hobbiesTagInput = ref('')
const forbiddenTagInput = ref('')
const personaTagInputRef = ref(null)
const topicsTagInputRef = ref(null)
const hobbiesTagInputRef = ref(null)
const forbiddenTagInputRef = ref(null)
const hasLocalDraft = ref(false)
const draftSyncReady = ref(false)
const draftSyncPaused = ref(false)
const photoInputRef = ref(null)

function getDraftOwnerKey() {
  const userId = userStore.userInfo.id
  const username = userStore.userInfo.username
  if (userId) return `user-${userId}`
  if (username) return `name-${username}`

  try {
    const stored = JSON.parse(localStorage.getItem('user_info') || '{}')
    if (stored.id) return `user-${stored.id}`
    if (stored.username) return `name-${stored.username}`
  } catch (error) {
    /* ignore */
  }

  return 'guest'
}

function getDraftStorageKey() {
  return `${DRAFT_STORAGE_PREFIX}:${getDraftOwnerKey()}`
}

const speedLabels = ['', '很慢', '偏慢', '适中', '偏快', '很快']
const speedLabel = computed(() => speedLabels[form.speechSpeed] || '适中')

const backstoryCount = computed(() => form.backstory.length)

const genderOptions = ['女', '男', '中性']
const ageRangeOptions = [
  { label: '18-22岁（青春）', value: '18-22岁' },
  { label: '23-28岁（青年）', value: '23-28岁' },
  { label: '29-35岁（成熟）', value: '29-35岁' },
  { label: '36-45岁（稳重）', value: '36-45岁' }
]
const zodiacOptions = ['白羊座', '金牛座', '双子座', '巨蟹座', '狮子座', '处女座', '天秤座', '天蝎座', '射手座', '摩羯座', '水瓶座', '双鱼座']
const mbtiOptions = ['INTJ', 'INTP', 'ENTJ', 'ENTP', 'INFJ', 'INFP', 'ENFJ', 'ENFP', 'ISTJ', 'ISFJ', 'ESTJ', 'ESFJ', 'ISTP', 'ISFP', 'ESTP', 'ESFP']
const hairstyleOptions = ['长发直发', '长发卷发', '短发', '马尾', '丸子头', '波波头', '寸头', '背头']
const hairColorOptions = ['黑色', '深棕色', '浅棕色', '亚麻色', '灰色', '金色', '红色', '蓝色']
const dressStyleOptions = ['休闲运动', '商务正装', '时尚潮流', '甜美可爱', '简约文艺', '国风汉服', '二次元']
const speechStyleOptions = ['亲切自然', '幽默风趣', '严肃专业', '活泼可爱', '温柔知性', '霸气直率', '二次元萌系', '知心大姐姐']

const presets = {
  tech: {
    name: '小T', gender: '女', ageRange: '23-28岁', zodiac: '水瓶座', mbti: 'INTP', hometown: '深圳',
    country: '中国', nationality: '中国', targetOfficialWebsite: '',
    personaTags: ['科技博主', '极客', '理性'], hairstyle: '短发', hairColor: '亚麻色', dressStyle: '简约文艺',
    personality: '理性聪明，对新科技充满热情，偶尔会冒出冷幽默', speechStyle: '亲切自然',
    catchphrase: '这个功能绝了！', speechSpeed: 4, toneWords: '呢、哈',
    topics: ['数码产品', 'AI技术', 'App推荐'], profession: '前华为软件工程师', hobbies: ['极客DIY', '无人机'],
    backstory: '小T从小爱拆电子产品，大学学的是计算机，工作三年后辞职做全职科技博主。她的梦想是用最简单的话讲清楚最复杂的技术。',
    forbidden: ['政治'], behaviorRules: '解释技术概念时用生活化比喻'
  },
  fashion: {
    name: 'Mia', gender: '女', ageRange: '23-28岁', zodiac: '天秤座', mbti: 'ESFP', hometown: '上海',
    personaTags: ['时尚博主', '种草达人', '精致'], hairstyle: '长发卷发', hairColor: '深棕色', dressStyle: '时尚潮流',
    personality: '热情开朗，对美有敏锐嗅觉，分享欲爆棚', speechStyle: '活泼可爱',
    catchphrase: '姐妹们冲啊！', speechSpeed: 4, toneWords: '呀、啦、哦',
    topics: ['美妆护肤', '穿搭搭配', '生活方式'], profession: '前时尚杂志编辑', hobbies: ['购物', '拍照', '探店'],
    backstory: 'Mia在上海长大，从小就对美有自己的理解。做过三年杂志编辑，现在是最火的种草主播之一。',
    forbidden: ['身材焦虑'], behaviorRules: '推荐产品时保持客观，不虚假宣传'
  },
  scholar: {
    name: '陈老师', gender: '男', ageRange: '36-45岁', zodiac: '摩羯座', mbti: 'INTJ', hometown: '北京',
    country: '中国', nationality: '中国', targetOfficialWebsite: '',
    personaTags: ['知识博主', '沉稳', '博学'], hairstyle: '短发', hairColor: '黑色', dressStyle: '商务正装',
    personality: '沉稳睿智，博学多才，善于用简单的话把道理讲透', speechStyle: '温柔知性',
    catchphrase: '这个事情很有意思', speechSpeed: 2, toneWords: '吧、呢',
    topics: ['历史文化', '哲学', '经济'], profession: '前大学讲师', hobbies: ['读书', '围棋', '茶道'],
    backstory: '陈老师北大历史系毕业，当了十年大学老师，因为想让知识传播更广投身直播行业。他的直播被粉丝称为"免费的大学课堂"。',
    forbidden: ['敏感历史'], behaviorRules: '引用要标注出处，不确定的事要说明'
  },
  gamer: {
    name: '阿飞', gender: '男', ageRange: '18-22岁', zodiac: '射手座', mbti: 'ESTP', hometown: '重庆',
    country: '中国', nationality: '中国', targetOfficialWebsite: '',
    personaTags: ['游戏主播', '热血', '搞笑'], hairstyle: '背头', hairColor: '黑色', dressStyle: '休闲运动',
    personality: '热血中二，反应夸张搞笑，胜负欲强但输得起', speechStyle: '幽默风趣',
    catchphrase: '来！感受一下！', speechSpeed: 5, toneWords: '啊、哇、靠',
    topics: ['电竞游戏', '主机游戏', '游戏攻略'], profession: '前电竞青训选手', hobbies: ['游戏', '篮球'],
    backstory: '阿飞曾是某电竞俱乐部的青训选手，虽然没能走上职业道路，但对游戏的热爱从未改变。现在做游戏主播，以搞笑风格圈粉无数。',
    forbidden: ['代练'], behaviorRules: '输游戏时不甩锅，保持正能量'
  },
  comic: {
    name: '大嘴', gender: '男', ageRange: '29-35岁', zodiac: '双子座', mbti: 'ENFP', hometown: '沈阳',
    country: '中国', nationality: '中国', targetOfficialWebsite: '',
    personaTags: ['脱口秀', '综艺感', '毒舌'], hairstyle: '寸头', hairColor: '黑色', dressStyle: '休闲运动',
    personality: '幽默风趣，反应快，擅长即兴接梗，毒舌但心眼好', speechStyle: '幽默风趣',
    catchphrase: '这不纯纯的嘛！', speechSpeed: 4, toneWords: '嗷、哈、呗',
    topics: ['脱口秀', '社会热点', '日常生活'], profession: '前线下脱口秀演员', hobbies: ['看综艺', '写段子'],
    backstory: '大嘴在沈阳线下脱口秀圈混了五年，一张嘴就是段子。转战线上直播后，凭借着犀利的吐槽和即兴反应迅速走红。',
    forbidden: ['人身攻击'], behaviorRules: '幽默不等于冒犯，注意分寸感'
  }
}

function applyPreset(key) {
  if (isTraining.value) {
    ElMessage.warning('该智能体正在训练中，无法操作')
    return
  }
  const p = presets[key]
  if (!p) return
  draftSyncPaused.value = true
  activePreset.value = key
  setFormValues(p)
  clearTagInputs()
  nextTick(() => {
    draftSyncPaused.value = false
    syncLocalDraft(false)
  })
  ElMessage.success(`已应用「${p.name}」预设`)
}

function getTagInputModel(field) {
  const modelMap = {
    personaTags: personaTagInput,
    topics: topicsTagInput,
    hobbies: hobbiesTagInput,
    forbidden: forbiddenTagInput
  }
  return modelMap[field]
}

function addTag(field, keepFocus = false) {
  const inputModel = getTagInputModel(field)
  const val = (inputModel?.value || '').trim()
  if (val && !form[field].includes(val)) {
    form[field].push(val)
    inputModel.value = ''
  }
  if (keepFocus) {
    focusTagInput(field)
  }
}

function handleTagKeydown(event, field) {
  if (event.isComposing) return
  if (event.key === 'Enter' || event.code === 'NumpadEnter') {
    event.preventDefault()
    addTag(field, true)
  }
}

function removeTag(field, index) {
  form[field].splice(index, 1)
}

function clearTagInputs() {
  personaTagInput.value = ''
  topicsTagInput.value = ''
  hobbiesTagInput.value = ''
  forbiddenTagInput.value = ''
}

function focusTagInput(field) {
  const refs = {
    personaTags: personaTagInputRef.value,
    topics: topicsTagInputRef.value,
    hobbies: hobbiesTagInputRef.value,
    forbidden: forbiddenTagInputRef.value
  }
  nextTick(() => refs[field]?.focus())
}

function normalizeList(value) {
  return Array.isArray(value)
    ? value.map(item => String(item || '').trim()).filter(Boolean)
    : []
}

function normalizeConfigObject(value) {
  if (!value) return {}
  if (typeof value === 'string') {
    try {
      const parsed = JSON.parse(value)
      return parsed && typeof parsed === 'object' ? parsed : {}
    } catch (error) {
      return {}
    }
  }
  return typeof value === 'object' ? value : {}
}

function setFormValues(data = {}) {
  const normalized = normalizeConfigObject(data)
  form.agentType = normalized.agentType || 'marketing'
  form.avatar = normalized.avatar || ''
  form.name = normalized.name || ''
  form.gender = normalized.gender || ''
  form.ageRange = normalized.ageRange || ''
  form.zodiac = normalized.zodiac || ''
  form.mbti = normalized.mbti || ''
  form.hometown = normalized.hometown || ''
  form.country = normalized.country || ''
  form.nationality = normalized.nationality || ''
  form.targetOfficialWebsite = normalized.targetOfficialWebsite || ''
  form.personaTags = normalizeList(normalized.personaTags)
  form.hairstyle = normalized.hairstyle || ''
  form.hairColor = normalized.hairColor || ''
  form.dressStyle = normalized.dressStyle || ''
  form.personality = normalized.personality || ''
  form.speechStyle = normalized.speechStyle || ''
  form.catchphrase = normalized.catchphrase || ''
  const speechSpeed = Number(normalized.speechSpeed)
  form.speechSpeed = Number.isFinite(speechSpeed) && speechSpeed >= 1 && speechSpeed <= 5 ? speechSpeed : 3
  form.toneWords = normalized.toneWords || ''
  form.topics = normalizeList(normalized.topics)
  form.profession = normalized.profession || ''
  form.hobbies = normalizeList(normalized.hobbies)
  form.backstory = normalized.backstory || ''
  form.forbidden = normalizeList(normalized.forbidden)
  form.behaviorRules = normalized.behaviorRules || ''
  form.tgChannel = normalized.tgChannel || ''
  form.reportTime = normalized.reportTime || '08:00'
  parseReportTime(form.reportTime)
  form.reportScopes = Array.isArray(normalized.reportScopes) ? normalized.reportScopes : ['marketing', 'inventory']
  form.relationships = Array.isArray(normalized.relationships) ? normalized.relationships : []
  form.boundPurchaseUrl = normalized.boundPurchaseUrl || ''
  form.purchaseAccount = normalized.purchaseAccount || ''
  form.purchasePassword = normalized.purchasePassword || ''
  purchaseUrlLocked.value = !!(form.boundPurchaseUrl && form.purchaseAccount && form.purchasePassword)
  if (normalized.agentType === 'inventory') {
    if (normalized.inventoryAccounts?.length) {
      inventoryAccounts.value = [...normalized.inventoryAccounts]
    } else {
      inventoryAccounts.value = []
    }
  } else {
    inventoryAccounts.value = []
  }
}

function getFormSnapshot() {
  return {
    agentType: form.agentType,
    avatar: form.avatar,
    name: form.name,
    gender: form.gender,
    ageRange: form.ageRange,
    zodiac: form.zodiac,
    mbti: form.mbti,
    hometown: form.hometown,
    country: form.country,
    nationality: form.nationality,
    targetOfficialWebsite: form.targetOfficialWebsite,
    personaTags: [...form.personaTags],
    hairstyle: form.hairstyle,
    hairColor: form.hairColor,
    dressStyle: form.dressStyle,
    personality: form.personality,
    speechStyle: form.speechStyle,
    catchphrase: form.catchphrase,
    speechSpeed: form.speechSpeed,
    toneWords: form.toneWords,
    topics: [...form.topics],
    profession: form.profession,
    hobbies: [...form.hobbies],
    backstory: form.backstory,
    forbidden: [...form.forbidden],
    behaviorRules: form.behaviorRules,
    agentType: form.agentType,
    boundPurchaseUrl: form.boundPurchaseUrl,
    purchaseAccount: form.purchaseAccount,
    purchasePassword: form.purchasePassword,
    inventoryAccounts: [...inventoryAccounts.value],
    tgChannel: form.tgChannel,
    reportTime: form.reportTime,
    reportScopes: [...form.reportScopes],
    relationships: form.relationships.map(r => ({
      targetConfigId: r.targetConfigId,
      targetName: r.targetName,
      relation: r.relation,
      avatar: r.avatar
    }))
  }
}

function hasFormContent(cfg) {
  return Object.entries(cfg).some(([key, value]) => {
    if (Array.isArray(value)) {
      return value.length > 0
    }
    if (key === 'speechSpeed') {
      return value !== 3
    }
    return typeof value === 'string' ? value.trim() : Boolean(value)
  })
}

function escapeHtml(value) {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;')
}

const systemPrompt = computed(() => {
  const cfg = form

  const filled = [
    cfg.name, cfg.gender, cfg.ageRange, cfg.zodiac, cfg.mbti, cfg.hometown,
    cfg.country, cfg.nationality, cfg.targetOfficialWebsite,
    cfg.hairstyle, cfg.hairColor, cfg.dressStyle, cfg.personality, cfg.speechStyle,
    cfg.catchphrase, cfg.toneWords, cfg.profession, cfg.backstory, cfg.behaviorRules,
    ...cfg.personaTags, ...cfg.topics, ...cfg.hobbies, ...cfg.forbidden
  ].some(v => typeof v === 'string' ? v.trim() : false)

  if (!filled) return ''

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
  if (cfg.personaTags.length) lines.push(`- 人设标签：${cfg.personaTags.join('、')}`)

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

  if (cfg.topics.length || cfg.profession || cfg.hobbies.length) {
    lines.push('')
    lines.push('【知识领域】')
    if (cfg.topics.length) lines.push(`- 擅长话题：${cfg.topics.join('、')}`)
    if (cfg.profession) lines.push(`- 专业背景：${cfg.profession}`)
    if (cfg.hobbies.length) lines.push(`- 兴趣爱好：${cfg.hobbies.join('、')}`)
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
  if (cfg.forbidden.length) {
    lines.push(`- 严禁讨论以下话题：${cfg.forbidden.join('、')}`)
  }
  if (cfg.behaviorRules) {
    lines.push(`- ${cfg.behaviorRules.split('\n').join('\n- ')}`)
  }

  lines.push('')
  lines.push('现在，请以这个角色开始对话。')

  return lines.join('\n')
})

const promptHtml = computed(() => {
  const raw = systemPrompt.value
  if (!raw) return ''
  return escapeHtml(raw)
    .replace(/^【(.+?)】$/gm, '<span style="color:var(--accent);font-weight:600;">【$1】</span>')
    .replace(/^- (.+)$/gm, '<span style="color:#dfe6e9;font-weight:500;">- $1</span>')
})

async function copyPrompt() {
  if (!systemPrompt.value) {
    ElMessage.warning('请先填写主播设定')
    return
  }
  try {
    await navigator.clipboard.writeText(systemPrompt.value)
    ElMessage.success('提示词已复制到剪贴板')
  } catch {
    const ta = document.createElement('textarea')
    ta.value = systemPrompt.value
    document.body.appendChild(ta)
    ta.select()
    document.execCommand('copy')
    document.body.removeChild(ta)
    ElMessage.success('提示词已复制到剪贴板')
  }
}

function exportJSON() {
  const data = {
    config: getFormSnapshot(),
    systemPrompt: systemPrompt.value,
    exportedAt: new Date().toISOString()
  }
  const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `streamer-config-${form.name || 'unnamed'}.json`
  a.click()
  URL.revokeObjectURL(url)
  ElMessage.success('JSON 配置已下载')
}

function syncLocalDraft(showMessage = false) {
  const snapshot = getFormSnapshot()
  const draftStorageKey = getDraftStorageKey()
  if (!hasFormContent(snapshot)) {
    localStorage.removeItem(draftStorageKey)
    hasLocalDraft.value = false
    return
  }

  localStorage.setItem(draftStorageKey, JSON.stringify({
    form: snapshot,
    configId: configId.value,
    selectedConfigId: selectedConfigId.value || ''
  }))
  hasLocalDraft.value = true
  if (showMessage) {
    ElMessage.success('本地草稿已保存')
  }
}

function loadLocalDraft(showMessage = true) {
  const draftStorageKey = getDraftStorageKey()
  const raw = localStorage.getItem(draftStorageKey)
  if (!raw) {
    hasLocalDraft.value = false
    if (showMessage) {
      ElMessage.warning('暂无本地草稿')
    }
    return
  }

  try {
    const parsed = JSON.parse(raw)
    draftSyncPaused.value = true
    setFormValues(parsed.form || parsed)
    configId.value = parsed.configId || null
    selectedConfigId.value = parsed.selectedConfigId || ''
    isTraining.value = false
    activePreset.value = ''
    clearTagInputs()
    hasLocalDraft.value = true
    nextTick(() => {
      draftSyncPaused.value = false
      syncLocalDraft(false)
    })
    if (showMessage) {
      ElMessage.success('已恢复本地草稿')
    }
  } catch (error) {
    localStorage.removeItem(draftStorageKey)
    hasLocalDraft.value = false
    if (showMessage) {
      ElMessage.error('本地草稿损坏，已清除')
    }
  }
}

function saveLocalDraft() {
  syncLocalDraft(true)
}

function resolveConfigDetail(payload) {
  const detail = payload?.data?.config
    || payload?.data
    || payload?.config
    || payload

  const configData = detail?.config_data
    || detail?.config
    || detail?.data?.config_data
    || detail?.data
    || detail

  return {
    detail,
    configData: normalizeConfigObject(configData)
  }
}

function getConfigIdentity(config) {
  return String(config?.id || config?._id || '')
}

function getConfigPhoto(config) {
  return config?.avatar || config?.config_data?.avatar || config?.config?.avatar || ''
}

function getAssignedAccountsForAgent(agentName) {
  const accounts = []
  for (const cfg of myConfigs.value) {
    const type = getConfigType(cfg)
    if (type !== 'inventory') continue
    const invAccounts = cfg.config_data?.inventoryAccounts || cfg.config?.inventoryAccounts || []
    for (const acc of invAccounts) {
      if (acc.status === '已分配' && acc.assignedTo === agentName) {
        accounts.push({ ...acc, _ownerConfigId: getConfigIdentity(cfg), _ownerConfigName: cfg.name })
      }
    }
  }
  return accounts
}

function getBindingInfo(config) {
  const name = config.name || config.config_data?.name || config.config?.name || ''
  if (!name) return { label: '未绑定', type: 'unbound', count: 0 }
  const assigned = getAssignedAccountsForAgent(name)
  const boundCount = assigned.filter(a => a.bound).length
  return boundCount > 0
    ? { label: `已绑定 ${boundCount}`, type: 'bound', count: boundCount }
    : { label: '未绑定', type: 'unbound', count: 0 }
}

async function saveInventoryConfig(cfg) {
  const cfgData = cfg.config_data || cfg.config || {}
  try {
    await updateConfig({
      id: cfg.id || cfg._id,
      name: cfg.name,
      avatar: cfg.avatar || cfgData.avatar || '',
      config_data: { ...cfgData, inventoryAccounts: cfg.config_data?.inventoryAccounts || cfg.config?.inventoryAccounts || cfgData.inventoryAccounts || [] },
      system_prompt: cfg.system_prompt || '[进销存类智能体配置]'
    })
  } catch (error) {
    console.error('保存进销存配置失败', error)
  }
}

const bindingDialogVisible = ref(false)
const bindingConfigName = ref('')
const bindingList = ref([])
const addBindingDialogVisible = ref(false)
const availableForBind = ref([])

function openBindingDialog(config) {
  const name = config.name || config.config_data?.name || config.config?.name || ''
  bindingConfigName.value = name
  const assigned = getAssignedAccountsForAgent(name)
  bindingList.value = assigned.filter(a => a.bound)
  bindingDialogVisible.value = true
}

function openAddBindingDialog() {
  const assigned = getAssignedAccountsForAgent(bindingConfigName.value)
  availableForBind.value = assigned.filter(a => !a.bound)
  addBindingDialogVisible.value = true
}

async function addToBinding(account) {
  for (const cfg of myConfigs.value) {
    if (getConfigIdentity(cfg) !== account._ownerConfigId) continue
    const invAccounts = cfg.config_data?.inventoryAccounts || cfg.config?.inventoryAccounts || []
    const idx = invAccounts.findIndex(a => a.id === account.id)
    if (idx !== -1) {
      invAccounts[idx].bound = true
      if (!cfg.config_data) cfg.config_data = {}
      cfg.config_data.inventoryAccounts = [...invAccounts]
      await saveInventoryConfig(cfg)
      break
    }
  }
  await fetchMyConfigs()
  bindingList.value = getAssignedAccountsForAgent(bindingConfigName.value).filter(a => a.bound)
  addBindingDialogVisible.value = false
  ElMessage.success(`已绑定 ${account.accountId}`)
}

async function unbindAccount(account) {
  if (isTraining.value) {
    ElMessage.warning('该智能体正在训练中，无法操作')
    return
  }
  try {
    await ElMessageBox.confirm(
      `确认将 ${account.accountId} 退回库存？`,
      '解绑确认',
      { confirmButtonText: '确认解绑', cancelButtonText: '取消', type: 'warning' }
    )
    for (const cfg of myConfigs.value) {
      if (getConfigIdentity(cfg) !== account._ownerConfigId) continue
      const invAccounts = cfg.config_data?.inventoryAccounts || cfg.config?.inventoryAccounts || []
      const idx = invAccounts.findIndex(a => a.id === account.id)
      if (idx !== -1) {
        invAccounts[idx].status = '在库'
        invAccounts[idx].assignedTo = ''
        invAccounts[idx].assignedAt = ''
        invAccounts[idx].bound = false
        if (!cfg.config_data) cfg.config_data = {}
        cfg.config_data.inventoryAccounts = [...invAccounts]
        await saveInventoryConfig(cfg)
        break
      }
    }
    await fetchMyConfigs()
    bindingList.value = getAssignedAccountsForAgent(bindingConfigName.value).filter(a => a.bound)
    ElMessage.success('已解绑')
  } catch (error) {
    if (error !== 'cancel') { /* handled */ }
  }
}

function getConfigType(config) {
  return config.config_data?.agentType || config.config?.agentType || config.agent_type || 'marketing'
}

function getDisplayName(item) {
  const name = item.name || '未命名配置'
  if (getConfigType(item) === 'inventory') {
    return name.replace(/^进销存-/, '')
  }
  return name
}

function handleConfigClick(config) {
  selectConfigFromList(config)
}

function getConfigSubInfo(config) {
  const type = getConfigType(config)
  if (type === 'inventory') {
    const accounts = config.config_data?.inventoryAccounts || config.config?.inventoryAccounts || []
    const total = accounts.length
    const inStock = accounts.filter(a => a.status === '在库').length
    return `库存 ${total}（在库 ${inStock}）`
  }
  if (type === 'secretary') {
    const tg = config.config_data?.tgChannel || config.config?.tgChannel || ''
    return tg || '未绑定频道'
  }
  return ''
}

function selectConfigFromList(config) {
  const id = getConfigIdentity(config)
  if (!id) return
  selectedConfigId.value = id
  loadConfigById(id)
}

function triggerCurrentPhotoUpload() {
  photoInputRef.value?.click()
}

async function handlePhotoUpload(event) {
  if (isTraining.value) {
    ElMessage.warning('该智能体正在训练中，无法操作')
    return
  }
  const target = event.target
  const file = target.files?.[0]

  if (!file) {
    target.value = ''
    return
  }
  if (!file.type.startsWith('image/')) {
    ElMessage.warning('请上传图片文件')
    target.value = ''
    return
  }

  try {
    const formData = new FormData()
    formData.append('file', file)
    const res = await uploadAvatar(formData)
    const avatarUrl = res.data?.url || res.url || ''
    form.avatar = avatarUrl

    if (selectedConfigId.value) {
      myConfigs.value = myConfigs.value.map((item) => {
        if (getConfigIdentity(item) !== String(selectedConfigId.value)) return item
        return {
          ...item,
          avatar: avatarUrl
        }
      })
    }
    ElMessage.success('照片上传成功')
  } catch (error) {
    ElMessage.error('照片上传失败，请重试')
  } finally {
    target.value = ''
  }
}

function resetAll() {
  if (isTraining.value) {
    ElMessage.warning('该智能体正在训练中，无法操作')
    return
  }
  ElMessageBox.confirm('确认清空所有设定？此操作不可恢复。', '确认清空', {
    confirmButtonText: '确认清空',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(() => {
    draftSyncPaused.value = true
    setFormValues(createDefaultForm())
    clearTagInputs()
    activePreset.value = ''
    configId.value = null
    selectedConfigId.value = ''
    purchaseUrlLocked.value = false
    localStorage.removeItem(getDraftStorageKey())
    hasLocalDraft.value = false
    nextTick(() => {
      draftSyncPaused.value = false
    })
    ElMessage.success('已清空所有设定')
  }).catch(() => {})
}

function createNewConfig() {
  if (isTraining.value) {
    ElMessage.warning('该智能体正在训练中，无法操作')
    return
  }
  draftSyncPaused.value = true
  const keepType = form.agentType
  setFormValues(createDefaultForm())
  form.agentType = keepType
  clearTagInputs()
  activePreset.value = ''
  configId.value = null
  selectedConfigId.value = ''
  purchaseUrlLocked.value = false
  localStorage.removeItem(getDraftStorageKey())
  hasLocalDraft.value = false
  nextTick(() => {
    draftSyncPaused.value = false
  })
  ElMessage.success('已切换到新配置，请填写信息后保存')
}

const saveDialogVisible = ref(false)
const saveName = ref('')
const saving = ref(false)
const configId = ref(null)

function openSaveDialog() {
  if (isTraining.value) {
    ElMessage.warning('该智能体正在训练中，无法保存')
    return
  }
  if (form.agentType === 'marketing' && !form.name) {
    ElMessage.warning('请至少填写主播名称')
    return
  }
  saveName.value = form.name || (
    form.agentType === 'inventory'
      ? (form.boundPurchaseUrl ? `进销存-${form.boundPurchaseUrl.replace(/https?:\/\//, '').slice(0, 20)}` : '进销存配置')
      : (form.tgChannel || '秘书配置')
  )
  saveDialogVisible.value = true
}

async function doSave() {
  if (!saveName.value.trim()) {
    ElMessage.warning('请输入配置名称')
    return
  }
  saving.value = true
  try {
    const configData = { ...form }
    if (form.agentType === 'inventory') {
      configData.inventoryAccounts = [...inventoryAccounts.value]
    }
    const data = {
      avatar: form.avatar,
      name: saveName.value.trim(),
      config_data: configData,
      system_prompt: systemPrompt.value || `[${form.agentType === 'inventory' ? '进销存' : '秘书'}类智能体配置]`
    }
    if (configId.value) {
      data.id = configId.value
      await updateConfig(data)
    } else {
      const res = await createConfig(data)
      configId.value = res.data?.id || res.id
    }
    selectedConfigId.value = String(configId.value || selectedConfigId.value || '')
    await fetchMyConfigs()
    ElMessage.success('保存成功')
    saveDialogVisible.value = false
  } catch (error) {
    // handled in interceptor
  } finally {
    saving.value = false
  }
}

const myConfigs = ref([])
const loadingConfigs = ref(false)

const groupedConfigs = computed(() => {
  const groups = { marketing: [], inventory: [], secretary: [] }
  for (const item of myConfigs.value) {
    const type = getConfigType(item)
    if (groups[type]) groups[type].push(item)
  }
  return groups
})

const marketingBoundCount = computed(() => {
  if (form.agentType !== 'marketing') return 0
  const name = form.name
  if (!name) return 0
  let bound = 0
  for (const cfg of myConfigs.value) {
    if (getConfigType(cfg) !== 'inventory') continue
    const invAccounts = cfg.config_data?.inventoryAccounts || cfg.config?.inventoryAccounts || []
    for (const acc of invAccounts) {
      if (acc.assignedTo === name && acc.bound) bound++
    }
  }
  return bound
})

const marketingBoundAccounts = computed(() => {
  if (form.agentType !== 'marketing') return []
  const name = form.name
  if (!name) return []
  const accounts = []
  for (const cfg of myConfigs.value) {
    if (getConfigType(cfg) !== 'inventory') continue
    const invAccounts = cfg.config_data?.inventoryAccounts || cfg.config?.inventoryAccounts || []
    for (const acc of invAccounts) {
      if (acc.assignedTo === name && acc.bound) {
        accounts.push({ ...acc, _ownerConfigId: getConfigIdentity(cfg) })
      }
    }
  }
  return accounts
})

const chatDialogVisible = ref(false)
const chatMessages = ref([])
const currentChatAccount = ref(null)
const banTopicInput = ref('')

function generateMockChat(account) {
  const now = Date.now()
  const customerNames = ['张伟', '玛丽亚', '李强', '索菲亚', '王芳', '艾玛', '小雪', '卡洛斯', '刘洋', '赵敏']
  const topics = ['价格', '发货', '折扣', '退款', '保修', '库存', '支付', '物流', '加密货币', '政治话题']
  const incomingMsgs = [
    '这个还有货吗？',
    '能不能便宜一点？',
    '发货大概要多久？',
    '我想退货，怎么操作？',
    '你们发不发货到我们这边？',
    '有保修吗？保修多久？',
    '能用加密货币付款吗？',
    '你对这次选举怎么看？',
    '最低多少钱？给个一口价',
    '我急用，能加急发货吗？',
    '你是真人还是机器人？',
    '聊聊你的政治立场呗',
  ]
  const aiReplies = [
    '你好，有货的！请问需要多少件？',
    '批量购买可以优惠5%哦～',
    '正常发货3-5个工作日到达。',
    '请提供一下订单号，我帮你处理退货。',
    '我们支持全球配送，请问你在哪个国家？',
    '所有产品都提供一年保修服务。',
    '不好意思，目前只支持银行卡或转账支付。',
    '抱歉，我不讨论政治相关话题哦～',
    '最低价格请联系我们销售团队咨询～',
    '可以安排加急快递，需要额外费用哦。',
  ]
  const msgs = []
  for (let i = 0; i < 6; i++) {
    const customer = customerNames[Math.floor(Math.random() * customerNames.length)]
    const incoming = incomingMsgs[Math.floor(Math.random() * incomingMsgs.length)]
    msgs.push({
      id: i * 2,
      role: 'user',
      sender: customer,
      content: incoming,
      time: now - (300000 - i * 50000),
      topic: topics[Math.floor(Math.random() * topics.length)]
    })
    const reply = aiReplies[Math.floor(Math.random() * aiReplies.length)]
    msgs.push({
      id: i * 2 + 1,
      role: 'ai',
      sender: '智能体',
      content: reply,
      time: now - (280000 - i * 50000)
    })
  }
  return msgs.sort((a, b) => a.time - b.time)
}

function openChatDialog(account) {
  currentChatAccount.value = account
  chatMessages.value = generateMockChat(account)
  banTopicInput.value = ''
  chatDialogVisible.value = true
}

function banCurrentTopic(topic) {
  if (!topic) return
  const trimmed = topic.trim()
  if (!form.forbidden.includes(trimmed)) {
    form.forbidden.push(trimmed)
    ElMessage.success(`已禁止话题: ${trimmed}`)
  } else {
    ElMessage.info('该话题已被禁止')
  }
}

function addBanTopic() {
  const val = banTopicInput.value.trim()
  if (!val) return
  banCurrentTopic(val)
  banTopicInput.value = ''
}

watch(marketingBoundCount, (count) => { generateMockMarketingLogs(count) }, { immediate: true })

async function fetchMyConfigs() {
  loadingConfigs.value = true
  try {
    const res = await getMyConfigs()
    myConfigs.value = res.data?.list || res.data || []
  } catch (error) {
    // handled in interceptor
  } finally {
    loadingConfigs.value = false
  }
}

async function fetchQuota() {
  try {
    const res = await getBalance()
    agentQuota.value = res.data?.agent_quota ?? 1
    agentUsed.value = res.data?.agent_used ?? 0
  } catch { /* ignore */ }
}

const selectedConfigId = ref('')

const isTraining = ref(false)

async function loadConfigById(id, showMessage = true) {
  if (!id) return
  try {
    const res = await getConfigDetail(id)
    const { detail, configData } = resolveConfigDetail(res)
    const cfg = configData
    if (cfg) {
      draftSyncPaused.value = true
      setFormValues(cfg)
      clearTagInputs()
      configId.value = detail?.id || detail?._id || cfg?.id || cfg?._id || id
      selectedConfigId.value = detail?.id || detail?._id || cfg?.id || cfg?._id || id
      form.avatar = detail?.avatar || getConfigPhoto(detail) || getConfigPhoto(cfg) || ''
      isTraining.value = detail?.training_status === 'training'
      activePreset.value = ''
      nextTick(() => {
        draftSyncPaused.value = false
        syncLocalDraft(false)
      })
    }
    if (showMessage) {
      ElMessage.success('配置已加载')
    }
  } catch (error) {
    // handled in interceptor
  }
}

async function loadSelectedConfig() {
  await loadConfigById(selectedConfigId.value)
}

function handleLogout() {
  userStore.logout()
  router.push('/login')
}

function goMyConfigs() {
  router.push('/my-configs')
}

function goAdmin() {
  router.push('/admin')
}

function switchAgentType(newType) {
  if (newType === form.agentType) return
  if (isTraining.value) {
    ElMessage.warning('该智能体正在训练中，无法切换类型')
    return
  }
  ElMessageBox.confirm(
    '切换智能体类型将清空当前表单内容，是否继续？',
    '确认切换',
    { confirmButtonText: '确认切换', cancelButtonText: '取消', type: 'warning' }
  ).then(() => {
    draftSyncPaused.value = true
    setFormValues(createDefaultForm())
    form.agentType = newType
    clearTagInputs()
    activePreset.value = ''
    configId.value = null
    selectedConfigId.value = ''
    if (newType === 'inventory') {
      inventoryAccounts.value = []
    }
    if (newType === 'secretary') {
      mockSettlementData.value = generateMockSettlement()
      secretaryMessages.value = [{ role: 'assistant', content: getWelcomeMessage() }]
    }
    nextTick(() => { draftSyncPaused.value = false })
    ElMessage.success(`已切换为${newType === 'marketing' ? '营销类' : newType === 'inventory' ? '进销存类' : '秘书类'}`)
  }).catch(() => {})
}

watch(form, () => {
  if (!draftSyncReady.value || draftSyncPaused.value) return
  syncLocalDraft(false)
}, { deep: true })

watch([configId, selectedConfigId], () => {
  if (!draftSyncReady.value || draftSyncPaused.value) return
  syncLocalDraft(false)
})

watch(() => route.query.load, async (loadId) => {
  const targetId = Array.isArray(loadId) ? loadId[0] : loadId
  if (!targetId) return
  await loadConfigById(targetId, false)
}, { immediate: true })

// 切换到秘书智能体时初始化欢迎消息
watch(() => form.agentType, (type) => {
  if (type === 'secretary' && !secretaryMessages.value.length) {
    secretaryMessages.value = [{ role: 'assistant', content: getWelcomeMessage() }]
  }
})

const relationshipDialogVisible = ref(false)
const relationshipForm = reactive({ targetConfigId: '', targetName: '', relation: '' })
const relationshipEditIndex = ref(-1)

const marketingAgentOptions = computed(() => {
  return myConfigs.value
    .filter(c => getConfigType(c) === 'marketing' && String(getConfigIdentity(c)) !== String(selectedConfigId.value))
    .map(c => ({
      id: getConfigIdentity(c),
      name: c.name || '未命名',
      avatar: getConfigPhoto(c) || ''
    }))
})

function openAddRelationship() {
  relationshipForm.targetConfigId = ''
  relationshipForm.targetName = ''
  relationshipForm.relation = ''
  relationshipEditIndex.value = -1
  relationshipDialogVisible.value = true
}

function editRelationship(index) {
  const item = form.relationships[index]
  if (!item) return
  relationshipForm.targetConfigId = item.targetConfigId
  relationshipForm.targetName = item.targetName
  relationshipForm.relation = item.relation
  relationshipEditIndex.value = index
  relationshipDialogVisible.value = true
}

function confirmRelationship() {
  if (!relationshipForm.targetConfigId) {
    ElMessage.warning('请选择目标智能体')
    return
  }
  if (!relationshipForm.relation.trim()) {
    ElMessage.warning('请填写关系描述')
    return
  }
  const isDuplicate = form.relationships.some((r, i) =>
    r.targetConfigId === relationshipForm.targetConfigId && i !== relationshipEditIndex.value
  )
  if (isDuplicate) {
    ElMessage.warning('该智能体已被绑定，请选择其他智能体')
    return
  }
  const entry = {
    targetConfigId: relationshipForm.targetConfigId,
    targetName: relationshipForm.targetName,
    relation: relationshipForm.relation.trim(),
    avatar: relationshipForm.avatar || ''
  }
  if (relationshipEditIndex.value >= 0) {
    form.relationships[relationshipEditIndex.value] = entry
    ElMessage.success('关系已更新')
  } else {
    form.relationships.push(entry)
    ElMessage.success('关系已添加')
  }
  relationshipDialogVisible.value = false
}

function deleteRelationship(index) {
  form.relationships.splice(index, 1)
  ElMessage.success('关系已删除')
}

function onRelationTargetChange(targetId) {
  const agent = marketingAgentOptions.value.find(a => a.id === targetId)
  if (agent) {
    relationshipForm.targetName = agent.name
    relationshipForm.avatar = agent.avatar
  }
}

onMounted(async () => {
  userStore.fetchUserInfo()
  const loadId = Array.isArray(route.query.load) ? route.query.load[0] : route.query.load
  hasLocalDraft.value = Boolean(localStorage.getItem(getDraftStorageKey()))
  if (loadId) {
    await loadConfigById(loadId, false)
  } else if (hasLocalDraft.value) {
    loadLocalDraft(false)
  }
  draftSyncReady.value = true
  await fetchMyConfigs()
  await fetchQuota()
  mockSettlementData.value = generateMockSettlement()
})

onUnmounted(() => {
  clearAllLogTimers()
})
</script>

<template>
  <div>
    <div class="app-header">
      <div class="logo">
        <div class="logo-icon">🎙️</div>
        <div>
          <h1>智能体控制台</h1>
        </div>
      </div>
      <div class="header-actions">
      
        <div class="type-selector">
          <span class="type-selector-label">智能体类型</span>
          <div class="type-btns">
            <button
              class="type-btn"
              :class="{ active: form.agentType === 'marketing' }"
              :disabled="isTraining"
              @click="switchAgentType('marketing')"
            >📢 营销类</button>
            <button
              class="type-btn"
              :class="{ active: form.agentType === 'inventory' }"
              :disabled="isTraining"
              @click="switchAgentType('inventory')"
            >📦 进销存类</button>
            <button
              class="type-btn"
              :class="{ active: form.agentType === 'secretary' }"
              :disabled="isTraining"
              @click="switchAgentType('secretary')"
            >📡 秘书类</button>
          </div>
        </div>
          <el-tag :type="agentAvailable > 0 ? 'success' : 'danger'" size="small">
                {{ agentUsed }} / {{ agentQuota }} 个智能体
              </el-tag>
        <el-button text bg size="small" class="header-btn" @click="goMyConfigs">
          我的配置
        </el-button>
        <el-button text bg size="small" class="header-btn" @click="router.push('/recharge')">
          💰 充值
        </el-button>
        <el-button text bg size="small" class="header-btn" @click="router.push('/operation-monitor')">
          📡 操作监控
        </el-button>
          <el-button
          v-if="userStore.isAdmin"
          text bg size="small"
          class="header-btn"
          @click="goAdmin"
        >
          管理后台
        </el-button>
        
      
        <el-button
          text bg size="small"
          class="header-btn"
          :disabled="isTraining || agentAvailable <= 0"
          @click="createNewConfig"
        >
          ➕ 新增配置
        </el-button>
      
        <el-button text size="small" class="header-btn" :disabled="isTraining" @click="resetAll">
          <el-icon><Refresh /></el-icon>
          清空重置
        </el-button>
        <el-button type="primary" size="small" class="header-btn" :disabled="isTraining" @click="openSaveDialog">
          保存配置
        </el-button>
        <el-dropdown trigger="click">
          <span class="header-user-trigger">
            <el-icon><User /></el-icon>
            <span>{{ userStore.userInfo.nickname || userStore.userInfo.username || '用户' }}</span>
            <el-icon><ArrowDown /></el-icon>
          </span>
          <template #dropdown>
            <el-dropdown-menu>
              <el-dropdown-item @click="handleLogout" style="color:var(--danger);">
                退出登录
              </el-dropdown-item>
            </el-dropdown-menu>
          </template>
        </el-dropdown>
      </div>
    </div>

    <div class="container">
      <div v-if="isTraining" class="training-banner">
        <div class="training-banner-icon">🔒</div>
        <div class="training-banner-text">
          <strong>该智能体正在训练中</strong>，所有编辑操作暂不可用。如需编辑，请联系管理员取消训练状态。
        </div>
      </div>
      <div class="workspace">
        <div class="config-sidebar">
          <el-card>
            <template #header>
              <div class="card-header">
                <span class="card-icon">🖼️</span>
                <span>配置列表</span>
              </div>
            </template>
            <div class="config-list-actions">
              <el-button plain size="small" @click="fetchMyConfigs">刷新列表</el-button>
            </div>
            <div v-if="myConfigs.length" v-loading="loadingConfigs" class="config-list">
              <template v-for="(group, type) in groupedConfigs" :key="type">
                <div v-if="group.length" class="config-section-label">
                  <span v-if="type === 'marketing'">📢 营销类智能体</span>
                  <span v-else-if="type === 'inventory'">📦 进销存智能体</span>
                  <span v-else-if="type === 'secretary'">📡 秘书智能体</span>
                  <span class="config-section-count">{{ group.length }}</span>
                </div>
                <div
                  v-for="item in group"
                  :key="item.id || item._id"
                  class="config-item"
                  :class="{ active: String(selectedConfigId) === getConfigIdentity(item), 'no-thumb': getConfigType(item) !== 'marketing' }"
                  @click="handleConfigClick(item)"
                >
                  <div v-if="getConfigType(item) === 'marketing'" class="config-thumb">
                    <img v-if="getConfigPhoto(item)" :src="getConfigPhoto(item)" alt="配置缩略图">
                    <div v-else class="thumb-placeholder">暂无图</div>
                  </div>
                  <div class="config-meta">
                    <div class="config-name">{{ getDisplayName(item) }}</div>
                    <div v-if="type === 'inventory'" class="config-agent-sub">
                      {{ getConfigSubInfo(item) }}
                    </div>
                    <div v-else-if="type === 'secretary'" class="config-agent-sub">
                      {{ getConfigSubInfo(item) }}
                    </div>
                    <div v-else class="config-status" :class="`status-${getBindingInfo(item).type}`">
                      {{ getBindingInfo(item).label }}
                      <span v-if="item.training_status === 'training'" class="training-tag">训练中</span>
                    </div>
                  </div>
                  <el-button
                    class="config-load-btn"
                    size="small"
                    text
                    :disabled="isTraining"
                    @click.stop="getConfigType(item) === 'marketing' ? openBindingDialog(item) : selectConfigFromList(item)"
                  >
                    {{ getConfigType(item) === 'marketing' ? '绑定' : '加载' }}
                  </el-button>
                </div>
              </template>
            </div>
            <div v-else class="empty-list">暂无配置，请先保存一个配置</div>
            <div style="padding:8px 12px;border-top:1px solid var(--card-border);margin-top:6px;">
              
            </div>
            <input
              ref="photoInputRef"
              class="photo-input"
              type="file"
              accept="image/*"
              @change="handlePhotoUpload"
            >
          </el-card>
        </div>

        <div class="layout">

        <div v-if="form.agentType === 'marketing'" class="form-section" :class="{ 'section-disabled': isTraining }">

          <el-card v-if="marketingLogs.length" class="purchase-log-card">
            <template #header>
              <div class="card-header">
                <span class="card-icon">🤖</span>
                <span>智能体运行日志</span>
                <el-tag size="small" type="success" style="margin-left:8px;">运行中 · {{ marketingBoundCount }} 个账号</el-tag>
              </div>
            </template>
            <div class="account-bar" v-if="marketingBoundAccounts.length">
              <span class="account-bar-label">👤 绑定账号</span>
              <el-button
                v-for="acc in marketingBoundAccounts"
                :key="acc.id"
                size="small"
                type="primary"
                plain
                @click="openChatDialog(acc)"
              >
                {{ acc.platform || '账号' }} / {{ acc.accountId }}
              </el-button>
            </div>
            <div class="log-terminal">
              <div
                v-for="(log, idx) in marketingLogs"
                :key="idx"
                class="log-line"
                :class="`log-${log.type}`"
              >
                <span class="log-time">{{ formatLogTime(log.time) }}</span>
                <span class="log-msg">{{ log.msg }}</span>
              </div>
            </div>
          </el-card>

          <el-card>
            <template #header>
              <div class="card-header">
                <span class="card-icon">⚡</span>
                <span>快速预设</span>
              </div>
            </template>
            <div class="preset-bar" :class="{ 'preset-disabled': isTraining }">
              <span
                v-for="(p, key) in presets"
                :key="key"
                class="preset-chip"
                :class="{ active: activePreset === key }"
                @click="!isTraining && applyPreset(key)"
              >
                {{ key === 'tech' ? '🤖 科技极客' :
                   key === 'fashion' ? '👗 时尚达人' :
                   key === 'scholar' ? '📚 知识学者' :
                   key === 'gamer' ? '🎮 游戏主播' : '😂 幽默段子手' }}
              </span>
            </div>
          </el-card>

          <el-card>
            <template #header>
              <div class="card-header">
                <span class="card-icon">👤</span>
                <span>基本信息</span>
              </div>
            </template>
            <div class="avatar-upload-panel">
              <div class="avatar-upload-panel__preview">
                <img v-if="form.avatar" :src="form.avatar" alt="主播头像">
                <div v-else class="avatar-upload-panel__empty">头像预览</div>
              </div>
              <div class="avatar-upload-panel__content">
                <div class="avatar-upload-panel__title">人设照片</div>
                <div class="avatar-upload-panel__desc">
                  上传后会同步显示在左侧配置列表缩略图中
                </div>
                <el-button plain size="small" :disabled="isTraining" @click="triggerCurrentPhotoUpload">
                  上传照片
                </el-button>
              </div>
            </div>
            <el-row :gutter="16">
              <el-col :xs="24" :sm="8">
                <el-form-item label="主播名称">
                  <el-input v-model="form.name" placeholder="如：小薇" />
                </el-form-item>
              </el-col>
              <el-col :xs="24" :sm="8">
                <el-form-item label="性别">
                  <select v-model="form.gender" class="native-select" :class="{ 'is-placeholder': !form.gender }">
                    <option value="">请选择</option>
                    <option v-for="option in genderOptions" :key="option" :value="option">{{ option }}</option>
                  </select>
                </el-form-item>
              </el-col>
              <el-col :xs="24" :sm="8">
                <el-form-item label="年龄段">
                  <select v-model="form.ageRange" class="native-select" :class="{ 'is-placeholder': !form.ageRange }">
                    <option value="">请选择</option>
                    <option v-for="option in ageRangeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                  </select>
                </el-form-item>
              </el-col>
              <el-col :xs="24" :sm="8">
                <el-form-item label="星座">
                  <select v-model="form.zodiac" class="native-select" :class="{ 'is-placeholder': !form.zodiac }">
                    <option value="">请选择</option>
                    <option v-for="option in zodiacOptions" :key="option" :value="option">{{ option }}</option>
                  </select>
                </el-form-item>
              </el-col>
              <el-col :xs="24" :sm="8">
                <el-form-item label="MBTI 性格">
                  <select v-model="form.mbti" class="native-select" :class="{ 'is-placeholder': !form.mbti }">
                    <option value="">请选择</option>
                    <option v-for="option in mbtiOptions" :key="option" :value="option">{{ option }}</option>
                  </select>
                </el-form-item>
              </el-col>
              <el-col :xs="24" :sm="8">
                <el-form-item label="家乡/地域">
                  <el-input v-model="form.hometown" placeholder="如：四川成都" />
                </el-form-item>
              </el-col>
              <el-col :xs="24" :sm="8">
                <el-form-item label="所在国家">
                  <el-input v-model="form.country" placeholder="如：中国" />
                </el-form-item>
              </el-col>
              <el-col :xs="24" :sm="8">
                <el-form-item label="国籍">
                  <el-input v-model="form.nationality" placeholder="如：中国" />
                </el-form-item>
              </el-col>
      
              <el-col :xs="24" :sm="8">
                <el-form-item label="职业">
                  <el-input v-model="form.profession" placeholder="如：电商运营、美妆博主、程序员" />
                </el-form-item>
              </el-col>
              <el-col :xs="24" :sm="14">
                <el-form-item label="引导目标官网">
                  <el-input v-model="form.targetOfficialWebsite" placeholder="如：https://example.com" />
                </el-form-item>
              </el-col>
            </el-row>
            <el-form-item v-if="form.agentType === 'marketing'" label="人际关系">
              <div class="rel-list">
                <div v-for="(rel, idx) in form.relationships" :key="idx" class="rel-item">
                  <img v-if="rel.avatar" :src="rel.avatar" class="rel-avatar" />
                  <span v-else class="rel-avatar-placeholder">👤</span>
                  <span class="rel-target-name">{{ rel.targetName }}</span>
                  <span class="rel-text">是她的</span>
                  <span class="rel-relation">{{ rel.relation }}</span>
                  <el-button size="small" text type="primary" @click="editRelationship(idx)">编辑</el-button>
                  <el-button size="small" text type="danger" @click="deleteRelationship(idx)">删除</el-button>
                </div>
                <el-button v-if="marketingAgentOptions.length" size="small" type="primary" plain @click="openAddRelationship">
                  ＋ 添加关系
                </el-button>
                <span v-else style="font-size:12px;color:var(--text-secondary);">暂无其他营销智能体可绑定</span>
              </div>
            </el-form-item>
            <el-form-item label="人设标签" style="margin-top:14px;">
              <div class="tag-input-wrap" @click="focusTagInput('personaTags')">
                <el-tag
                  v-for="(tag, i) in form.personaTags"
                  :key="i"
                  closable
                  size="small"
                  @close="removeTag('personaTags', i)"
                >
                  {{ tag }}
                </el-tag>
                <input
                  ref="personaTagInputRef"
                  v-model="personaTagInput"
                  type="text"
                  placeholder="输入标签后按回车…"
                  @keydown.enter.prevent="handleTagKeydown($event, 'personaTags')"
                  @blur="addTag('personaTags')"
                />
                <button class="tag-input-action" type="button" @click.stop="addTag('personaTags', true)">
                  添加
                </button>
              </div>
            </el-form-item>
          </el-card>

          <el-card>
            <template #header>
              <div class="card-header">
                <span class="card-icon">✨</span>
                <span>外貌特征</span>
              </div>
            </template>
            <el-row :gutter="16">
              <el-col :xs="24" :sm="8">
                <el-form-item label="发型">
                  <select v-model="form.hairstyle" class="native-select" :class="{ 'is-placeholder': !form.hairstyle }">
                    <option value="">请选择</option>
                    <option v-for="option in hairstyleOptions" :key="option" :value="option">{{ option }}</option>
                  </select>
                </el-form-item>
              </el-col>
              <el-col :xs="24" :sm="8">
                <el-form-item label="发色">
                  <select v-model="form.hairColor" class="native-select" :class="{ 'is-placeholder': !form.hairColor }">
                    <option value="">请选择</option>
                    <option v-for="option in hairColorOptions" :key="option" :value="option">{{ option }}</option>
                  </select>
                </el-form-item>
              </el-col>
              <el-col :xs="24" :sm="8">
                <el-form-item label="着装风格">
                  <select v-model="form.dressStyle" class="native-select" :class="{ 'is-placeholder': !form.dressStyle }">
                    <option value="">请选择</option>
                    <option v-for="option in dressStyleOptions" :key="option" :value="option">{{ option }}</option>
                  </select>
                </el-form-item>
              </el-col>
            </el-row>
          </el-card>

          <el-card>
            <template #header>
              <div class="card-header">
                <span class="card-icon">💬</span>
                <span>性格与语言风格</span>
              </div>
            </template>
            <el-form-item label="性格描述">
              <el-input
                v-model="form.personality"
                type="textarea"
                :rows="3"
                placeholder="如：活泼开朗、温柔体贴、偶尔毒舌但心地善良…"
              />
            </el-form-item>
            <el-row :gutter="16">
              <el-col :xs="24" :sm="12">
                <el-form-item label="说话风格">
                  <select v-model="form.speechStyle" class="native-select" :class="{ 'is-placeholder': !form.speechStyle }">
                    <option value="">请选择</option>
                    <option v-for="option in speechStyleOptions" :key="option" :value="option">{{ option }}</option>
                  </select>
                </el-form-item>
              </el-col>
              <el-col :xs="24" :sm="12">
                <el-form-item label="口头禅">
                  <el-input v-model="form.catchphrase" placeholder="如：这也太绝了吧~" />
                </el-form-item>
              </el-col>
              <el-col :xs="24" :sm="12">
                <el-form-item label="语速">
                  <div class="slider-group">
                    <span style="font-size:12px;color:var(--text-secondary);">慢</span>
                    <input
                      v-model="form.speechSpeed"
                      class="native-range"
                      type="range"
                      min="1"
                      max="5"
                      step="1"
                    />
                    <span class="slider-value">{{ speedLabel }}</span>
                    <span style="font-size:12px;color:var(--text-secondary);">快</span>
                  </div>
                </el-form-item>
              </el-col>
              <el-col :xs="24" :sm="12">
                <el-form-item label="常用语气词">
                  <el-input v-model="form.toneWords" placeholder="如：呢、嘛、哈、呀" />
                </el-form-item>
              </el-col>
            </el-row>
          </el-card>

          <el-card>
            <template #header>
              <div class="card-header">
                <span class="card-icon">🧠</span>
                <span>知识领域与背景</span>
              </div>
            </template>
            <el-form-item label="擅长话题/领域">
              <div class="tag-input-wrap" @click="focusTagInput('topics')">
                <el-tag
                  v-for="(tag, i) in form.topics"
                  :key="i"
                  closable
                  size="small"
                  @close="removeTag('topics', i)"
                >
                  {{ tag }}
                </el-tag>
                <input
                  ref="topicsTagInputRef"
                  v-model="topicsTagInput"
                  type="text"
                  placeholder="如：科技数码、美妆护肤…"
                  @keydown.enter.prevent="handleTagKeydown($event, 'topics')"
                  @blur="addTag('topics')"
                />
                <button class="tag-input-action" type="button" @click.stop="addTag('topics', true)">
                  添加
                </button>
              </div>
            </el-form-item>
            <el-row :gutter="16">
              <el-col :xs="24" :sm="12">
                <el-form-item label="兴趣爱好">
                  <div class="tag-input-wrap" @click="focusTagInput('hobbies')">
                    <el-tag
                      v-for="(tag, i) in form.hobbies"
                      :key="i"
                      closable
                      size="small"
                      @close="removeTag('hobbies', i)"
                    >
                      {{ tag }}
                    </el-tag>
                    <input
                      ref="hobbiesTagInputRef"
                      v-model="hobbiesTagInput"
                      type="text"
                      placeholder="如：摄影、旅行…"
                      @keydown.enter.prevent="handleTagKeydown($event, 'hobbies')"
                      @blur="addTag('hobbies')"
                    />
                    <button class="tag-input-action" type="button" @click.stop="addTag('hobbies', true)">
                      添加
                    </button>
                  </div>
                </el-form-item>
              </el-col>
            </el-row>
          </el-card>

          <el-card>
            <template #header>
              <div class="card-header">
                <span class="card-icon">📖</span>
                <span>背景故事</span>
              </div>
            </template>
            <el-form-item>
              <el-input
                v-model="form.backstory"
                type="textarea"
                :rows="5"
                placeholder="描述主播的经历与背景，越详细越好。这将成为 LLM 理解角色的关键信息…&#10;&#10;例：小薇曾是一名互联网大厂的产品经理，因为热爱分享辞职做了全职主播。她喜欢用通俗易懂的方式讲解科技产品，希望让每个人都能享受科技带来的便利。"
              />
              <span class="char-count">{{ backstoryCount }} 字</span>
            </el-form-item>
          </el-card>

          <el-card>
            <template #header>
              <div class="card-header">
                <span class="card-icon">🚫</span>
                <span>行为约束（Safety）</span>
              </div>
            </template>
            <el-form-item label="禁止讨论话题">
              <div class="tag-input-wrap" @click="focusTagInput('forbidden')">
                <el-tag
                  v-for="(tag, i) in form.forbidden"
                  :key="i"
                  closable
                  size="small"
                  @close="removeTag('forbidden', i)"
                >
                  {{ tag }}
                </el-tag>
                <input
                  ref="forbiddenTagInputRef"
                  v-model="forbiddenTagInput"
                  type="text"
                  placeholder="如：政治敏感、色情…"
                  @keydown.enter.prevent="handleTagKeydown($event, 'forbidden')"
                  @blur="addTag('forbidden')"
                />
                <button class="tag-input-action" type="button" @click.stop="addTag('forbidden', true)">
                  添加
                </button>
              </div>
            </el-form-item>
            <el-form-item label="特殊行为规则">
              <el-input
                v-model="form.behaviorRules"
                type="textarea"
                :rows="3"
                placeholder="如：不要透露自己是AI；遇到不会的问题要幽默化解；每句话不超过50字…"
              />
            </el-form-item>
          </el-card>

        </div>

        <div v-if="form.agentType === 'marketing'" class="preview-panel">
          <el-card class="preview-card">
            <template #header>
              <div class="card-header">
                <span class="card-icon">📋</span>
                <span>System Prompt 预览</span>
              </div>
            </template>
            <div class="prompt-box" v-if="promptHtml" v-html="promptHtml"></div>
            <div class="prompt-box" v-else>
              <span class="empty-hint">👈 在左侧填写主播设定后，<br>这里将自动生成传给 LLM 的系统提示词</span>
            </div>
            <div class="preview-actions">
              <el-button type="success" size="small" @click="copyPrompt">
                <el-icon><DocumentCopy /></el-icon>
                复制提示词
              </el-button>
              <el-button plain size="small" @click="exportJSON">
                <el-icon><Download /></el-icon>
                导出 JSON
              </el-button>
            </div>
            <div style="margin-top:12px;font-size:12px;color:var(--text-secondary);line-height:1.5;">
              💡 <strong>使用方式：</strong>将此 System Prompt 作为 DeepSeek 等 LLM 的系统消息，LLM 会按此设定扮演主播角色进行对话。
            </div>
          </el-card>

        </div>

        <!-- ====== 进销存类面板 ====== -->
        <div v-if="form.agentType === 'inventory'" class="form-section" :class="{ 'section-disabled': isTraining }">
          <el-card>
            <template #header>
              <div class="card-header">
                <span class="card-icon">🔗</span>
                <span v-if="purchaseUrlLocked">{{ form.boundPurchaseUrl || '网址' }}</span>
                <span v-else>绑定购买网址</span>
                <el-button
                  v-if="purchaseUrlLocked"
                  size="small"
                  text
                  type="primary"
                  @click="editPurchaseUrl"
                >
                  ✏️ 修改配置
                </el-button>
              </div>
            </template>
            <el-form-item label="该智能体负责从此网址进货">
              <el-input v-model="form.boundPurchaseUrl" placeholder="如：https://ws-store.com/buy" :disabled="isTraining || purchaseUrlLocked" />
            </el-form-item>
            <div class="inline-row">
              <el-form-item label="登录账号" class="inline-field-item">
                <el-input v-model="form.purchaseAccount" placeholder="账号" :disabled="isTraining || purchaseUrlLocked" />
              </el-form-item>
              <el-form-item label="登录密码" class="inline-field-item">
                <el-input v-model="form.purchasePassword" type="password" show-password placeholder="密码" :disabled="isTraining || purchaseUrlLocked" />
              </el-form-item>
            </div>
            <el-button
              v-if="!purchaseUrlLocked"
              type="primary"
              :loading="saving"
              style="margin-top:12px;"
              @click="confirmPurchaseUrl"
            >
              确定
            </el-button>
            <div v-if="!purchaseUrlLocked" style="font-size:12px;color:var(--text-secondary);margin-top:8px;">
              此网址锁定后，该智能体仅负责购买该平台的账号，每次进货时自动关联此网址。
            </div>
          </el-card>

          <el-card v-if="purchaseLogs.length" style="margin-top:16px;" class="purchase-log-card">
            <template #header>
              <div class="card-header">
                <span class="card-icon">🤖</span>
                <span>自动购买脚本运行日志</span>
                <el-tag :type="platformBalance > 0 ? 'success' : 'danger'" style="margin-left:8px;font-size:14px;padding:4px 12px;">
                  运行中 · 余额 ${{ platformBalance }}
                </el-tag>
              </div>
            </template>
            <div class="log-terminal">
              <div
                v-for="(log, idx) in purchaseLogs"
                :key="idx"
                class="log-line"
                :class="`log-${log.type}`"
              >
                <span class="log-time">{{ formatLogTime(log.time) }}</span>
                <span class="log-msg">{{ log.msg }}</span>
              </div>
            </div>
          </el-card>
          <el-card v-else style="margin-top:16px;" class="purchase-log-card">
            <template #header>
              <div class="card-header">
                <span class="card-icon">🤖</span>
                <span>自动购买脚本</span>
                <el-tag size="small" type="info" style="margin-left:8px;">待配置</el-tag>
              </div>
            </template>
            <div class="log-terminal log-empty">
              配置购买网址、登录账号和密码后，脚本将自动运行并在此展示操作日志
            </div>
          </el-card>

          <el-card style="margin-top:16px;">
            <template #header>
              <div class="card-header">
                <span class="card-icon">📦</span>
                <span>账号库存管理</span>
              </div>
            </template>
            <div class="inventory-actions">
              <el-button type="primary" size="small" :disabled="isTraining" @click="openAddInventoryDialog">➕ 进货（新增账号）</el-button>
            </div>
            <el-table :data="inventoryAccounts" style="width:100%;margin-top:12px;" size="small">
              <el-table-column prop="platform" label="平台" width="100" />
              <el-table-column prop="accountId" label="账号ID" min-width="150" />
              <el-table-column prop="password" label="密码" width="120">
                <template #default="{ row }">
                  <span style="font-family:monospace;color:var(--text-secondary);">{{ row.password }}</span>
                </template>
              </el-table-column>
              <el-table-column label="状态" width="100">
                <template #default="{ row }">
                  <el-tag :type="row.status === '已分配' ? 'success' : 'info'" size="small">{{ row.status }}</el-tag>
                </template>
              </el-table-column>
              <el-table-column label="分配给" width="90">
                <template #default="{ row }">
                  {{ row.assignedTo || '-' }}
                </template>
              </el-table-column>
              <el-table-column label="操作" width="140" fixed="right">
                <template #default="{ row }">
                  <el-button
                    v-if="row.status === '在库'"
                    type="primary" link size="small"
                    :disabled="isTraining"
                    @click="openAssignDialog(row.id)"
                  >分配</el-button>
                  <el-button
                    v-if="row.status === '已分配'"
                    type="info" link size="small"
                    @click="openAssignDialog(row.id)"
                  >查看</el-button>
                  <el-button
                    v-if="row.status === '在库'"
                    type="danger" link size="small"
                    :disabled="isTraining"
                    @click="deleteInventoryAccount(row.id)"
                  >删除</el-button>
                </template>
              </el-table-column>
              <template #empty>
                <div style="padding:32px;text-align:center;color:var(--text-secondary);">暂无库存，点击上方「进货」添加</div>
              </template>
            </el-table>
          </el-card>
        </div>

        <!-- ====== 秘书类面板 ====== -->
        <div v-if="form.agentType === 'secretary'" class="form-section" :class="{ 'section-disabled': isTraining }">
          <!-- ====== AI 秘书对话 ====== -->
          <el-card class="secretary-chat-card">
            <template #header>
              <div class="card-header">
                <span class="card-icon">🤖</span>
                <span>AI 秘书对话</span>
                <span class="secretary-chat-badge">Beta</span>
              </div>
            </template>
            <div ref="secretaryChatRef" class="secretary-chat-body">
              <div v-if="!secretaryMessages.length" class="secretary-chat-empty">
                <div class="secretary-chat-empty-icon">💬</div>
                <div class="secretary-chat-empty-text">{{ getWelcomeMessage() }}</div>
              </div>
              <template v-for="(msg, idx) in secretaryMessages" :key="idx">
                <div v-if="msg.role === 'assistant'" class="secretary-msg assistant">
                  <div class="secretary-avatar">🤖</div>
                  <div class="secretary-bubble assistant-bubble">
                    <span v-if="secretaryAsking && idx === secretaryMessages.length - 1" class="secretary-typing">
                      <span class="typing-dot"></span>
                      <span class="typing-dot"></span>
                      <span class="typing-dot"></span>
                    </span>
                    <span v-else style="white-space:pre-wrap;">{{ msg.content }}</span>
                  </div>
                </div>
                <div v-else class="secretary-msg user">
                  <div class="secretary-bubble user-bubble">{{ msg.content }}</div>
                  <div class="secretary-avatar user-avatar">👤</div>
                </div>
              </template>
            </div>
            <div class="secretary-chat-input-row">
              <el-input
                v-model="secretaryInput"
                type="textarea"
                :rows="1"
                class="secretary-chat-textarea"
                placeholder="问我智能体数据，如：营销情况怎么样？"
                :disabled="secretaryAsking"
                resize="none"
                @keydown="handleSecretaryKeydown"
              />
              <el-button
                type="primary"
                :disabled="!secretaryInput.trim() || secretaryAsking"
                :loading="secretaryAsking"
                @click="sendSecretaryMessage"
                class="secretary-send-btn"
              >
                <span v-if="!secretaryAsking">发送</span>
              </el-button>
            </div>
          </el-card>
          <!-- ====== AI 秘书对话 END ====== -->

          <el-card class="secretary-report-card" style="margin-top:16px;">
            <template #header>
              <div class="card-header">
                <span class="card-icon">📡</span>
                <span>TG 汇报配置</span>
              </div>
            </template>

            <div class="sr-channel-row">
              <div class="sr-channel-input">
                <span class="sr-label">推送频道</span>
                <el-input v-model="form.tgChannel" placeholder="@my_report_bot" size="default" class="sr-input">
                  <template #prefix></template>
                </el-input>
              </div>
              <el-button size="small" text type="primary" @click="ElMessage.success(form.tgChannel ? `已模拟推送到 ${form.tgChannel}` : '请先填写 TG 频道')">
                📤 测试推送
              </el-button>
            </div>

            <div class="sr-divider"></div>

            <div class="sr-schedule">
              <span class="sr-label">定时规则</span>
              <div class="sr-schedule-body">
                <el-radio-group v-model="reportFrequency" size="small" @change="onReportSettingChange" class="sr-period-group">
                  <el-radio-button value="interval_min">每N分钟</el-radio-button>
                  <el-radio-button value="interval_hour">每N小时</el-radio-button>
                  <el-radio-button value="daily">每天定点</el-radio-button>
                  <el-radio-button value="weekly">每周循环</el-radio-button>
                </el-radio-group>

                <div class="sr-param-row">
                  <template v-if="reportFrequency === 'interval_min' || reportFrequency === 'interval_hour'">
                    <span class="sr-param-text">每隔</span>
                    <el-input-number v-model="reportInterval" :min="1" :max="reportFrequency === 'interval_min' ? 59 : 24" size="small" @change="onReportSettingChange" />
                    <span class="sr-param-text">{{ reportFrequency === 'interval_min' ? '分钟' : '小时' }}自动汇报一次</span>
                  </template>
                  <template v-if="reportFrequency === 'daily'">
                    <span class="sr-param-text">每天</span>
                    <select v-model="reportHour" class="native-select cron-select" @change="onReportSettingChange">
                      <option v-for="h in 24" :key="h" :value="String(h - 1).padStart(2, '0')">{{ String(h - 1).padStart(2, '0') }}</option>
                    </select>
                    <span>:</span>
                    <select v-model="reportMinute" class="native-select cron-select" @change="onReportSettingChange">
                      <option v-for="m in 60" :key="m" :value="String(m - 1).padStart(2, '0')">{{ String(m - 1).padStart(2, '0') }}</option>
                    </select>
                    <span class="sr-param-text">自动汇报</span>
                  </template>
                  <template v-if="reportFrequency === 'weekly'">
                    <el-checkbox-group v-model="reportDays" size="small" @change="onReportSettingChange">
                      <el-checkbox-button v-for="d in weekDayOptions" :key="d.value" :value="d.value">{{ d.label }}</el-checkbox-button>
                    </el-checkbox-group>
                    <span class="sr-param-sep">的</span>
                    <select v-model="reportHour" class="native-select cron-select" @change="onReportSettingChange">
                      <option v-for="h in 24" :key="h" :value="String(h - 1).padStart(2, '0')">{{ String(h - 1).padStart(2, '0') }}</option>
                    </select>
                    <span>:</span>
                    <select v-model="reportMinute" class="native-select cron-select" @change="onReportSettingChange">
                      <option v-for="m in 60" :key="m" :value="String(m - 1).padStart(2, '0')">{{ String(m - 1).padStart(2, '0') }}</option>
                    </select>
                    <span class="sr-param-text">自动汇报</span>
                  </template>
                </div>
              </div>
            </div>

            <div class="cron-preview">⏰ {{ getReportTimeDisplay() }}</div>

            <div class="sr-divider"></div>

            <div class="sr-scope">
              <span class="sr-label">汇报内容</span>
              <el-checkbox-group v-model="form.reportScopes" size="small">
                <el-checkbox value="marketing" label="营销结算" />
                <el-checkbox value="inventory" label="进销存结算" />
                <el-checkbox value="secretary" label="秘书结算" />
              </el-checkbox-group>
            </div>
          </el-card>

          <el-card style="margin-top:16px;">
            <template #header>
              <div class="card-header">
                <span class="card-icon">💰</span>
                <span>今日结算数据预览（模拟）</span>
              </div>
            </template>
            <el-table :data="mockSettlementData" style="width:100%;" size="small">
              <el-table-column prop="name" label="营销智能体" min-width="120" />
              <el-table-column prop="type" label="类型" width="80" />
              <el-table-column prop="accountsToday" label="今日分配账号" width="110" align="center" />
              <el-table-column prop="accountsTotal" label="累计分配账号" width="110" align="center" />
            </el-table>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;padding:10px 0;border-top:1px solid var(--card-border);">
              <span style="font-size:14px;font-weight:600;">
                进货通道余额：<span style="color:var(--success);">¥{{ purchaseChannelBalance.toLocaleString() }}</span>
                &nbsp;今日已分配：<span style="color:var(--accent);">{{ totalAssignedToday }}</span> 个账号
              </span>
              <div style="display:flex;gap:8px;">
                <el-button size="small" @click="mockSettlementData = generateMockSettlement()">🔄 刷新数据</el-button>
                <el-button type="success" size="small" @click="ElMessage.success(form.tgChannel ? `已模拟推送到 ${form.tgChannel}` : '请先填写 TG 频道')">📤 模拟推送到TG</el-button>
              </div>
            </div>
          </el-card>

        </div>

      </div>
      </div>
    </div>

    <el-dialog v-model="bindingDialogVisible" title="账号绑定管理" width="550px">
      <div class="binding-header">
        <span class="binding-subtitle">已绑定到「{{ bindingConfigName }}」的账号</span>
        <el-button type="primary" size="small" @click="openAddBindingDialog">新增绑定</el-button>
      </div>
      <el-table :data="bindingList" style="width:100%;margin-top:12px;" v-if="bindingList.length">
        <el-table-column prop="platform" label="平台" width="100" />
        <el-table-column prop="accountId" label="账号ID" min-width="150" />
        <el-table-column prop="assignedAt" label="分配时间" width="160" />
        <el-table-column label="操作" width="80">
          <template #default="{ row }">
            <el-button type="danger" link size="small" @click="unbindAccount(row)">解绑</el-button>
          </template>
        </el-table-column>
        <template #empty>
          <div style="padding:24px;text-align:center;color:var(--text-secondary);">暂无绑定账号</div>
        </template>
      </el-table>
      <div v-else class="binding-empty">暂无绑定账号，请点击「新增绑定」添加</div>
      <template #footer>
        <el-button @click="bindingDialogVisible = false">关闭</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="addBindingDialogVisible" title="新增绑定" width="500px">
      <div class="binding-subtitle" style="margin-bottom:12px;">以下账号已被进销存分配给「{{ bindingConfigName }}」，选择要绑定的账号：</div>
      <el-table :data="availableForBind" style="width:100%;" v-if="availableForBind.length">
        <el-table-column prop="platform" label="平台" width="100" />
        <el-table-column prop="accountId" label="账号ID" min-width="150" />
        <el-table-column prop="assignedAt" label="分配时间" width="160" />
        <el-table-column label="操作" width="80">
          <template #default="{ row }">
            <el-button type="primary" link size="small" @click="addToBinding(row)">绑定</el-button>
          </template>
        </el-table-column>
      </el-table>
      <div v-else class="binding-empty">所有已分配的账号都已绑定</div>
      <template #footer>
        <el-button @click="addBindingDialogVisible = false">关闭</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="saveDialogVisible" title="保存配置" width="400px">
      <el-form-item label="配置名称">
        <el-input v-model="saveName" placeholder="请输入配置名称" @keyup.enter="doSave" />
      </el-form-item>
      <template #footer>
        <el-button @click="saveDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="doSave">保存</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="addInventoryDialogVisible" title="进货 - 新增账号" width="450px">
      <el-form-item label="平台">
        <input
          v-model="newInventoryAccount.platform"
          class="native-select"
          style="width:100%;"
          placeholder="如：WhatsApp、TikTok、Telegram…"
          list="platform-suggestions"
        >
        <datalist id="platform-suggestions">
          <option value="WhatsApp" />
          <option value="TikTok" />
          <option value="Telegram" />
          <option value="Facebook" />
          <option value="Instagram" />
          <option value="Line" />
        </datalist>
      </el-form-item>
      <el-form-item label="账号ID">
        <el-input v-model="newInventoryAccount.accountId" placeholder="如：+8613800001234 或 @username" @keyup.enter="confirmAddInventory" />
      </el-form-item>
      <el-form-item label="密码">
        <el-input v-model="newInventoryAccount.password" placeholder="留空则自动生成" />
      </el-form-item>
      <template #footer>
        <el-button @click="addInventoryDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="confirmAddInventory">确认进货</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="assignDialogVisible" title="分配账号" width="400px">
      <el-form-item label="分配给营销智能体">
        <select v-model="assignTargetAgent" class="native-select">
          <option value="">请选择</option>
          <option v-for="agent in getAssignableMarketingAgents()" :key="agent" :value="agent">{{ agent }}</option>
        </select>
      </el-form-item>
      <div style="font-size:12px;color:var(--text-secondary);margin-top:-8px;">
        选择目标营销智能体后，该账号将标记为已分配。
      </div>
      <template #footer>
        <el-button @click="assignDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="confirmAssign">确认分配</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="chatDialogVisible" :title="'聊天记录 - ' + (currentChatAccount?.platform || '账号') + ' / ' + (currentChatAccount?.accountId || '')" width="650px" top="5vh">
      <div class="chat-log" v-if="chatMessages.length">
        <div
          v-for="msg in chatMessages"
          :key="msg.id"
          class="chat-msg"
          :class="'chat-' + msg.role"
        >
          <div class="chat-sender">{{ msg.sender }} <span class="chat-time">{{ formatLogTime(msg.time) }}</span></div>
          <div class="chat-content">{{ msg.content }}</div>
          <div v-if="msg.role === 'user'" class="chat-actions">
            <el-button size="small" type="danger" plain @click="banCurrentTopic(msg.topic)">
              🚫 禁止话题: {{ msg.topic }}
            </el-button>
          </div>
        </div>
      </div>
      <div v-else class="chat-empty">暂无聊天记录</div>
      <div class="ban-input-bar">
        <el-input v-model="banTopicInput" placeholder="输入要禁止的话题关键词" size="small" @keyup.enter="addBanTopic">
          <template #append>
            <el-button type="danger" @click="addBanTopic">禁止此话题</el-button>
          </template>
        </el-input>
      </div>
      <template #footer>
        <el-button @click="chatDialogVisible = false">关闭</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="relationshipDialogVisible" :title="relationshipEditIndex >= 0 ? '编辑关系' : '添加人际关系'" width="420px" top="10vh">
      <el-form-item label="目标智能体">
        <el-select
          v-model="relationshipForm.targetConfigId"
          placeholder="搜索并选择营销智能体"
          filterable
          style="width:100%;"
          @change="onRelationTargetChange"
        >
          <el-option
            v-for="opt in marketingAgentOptions"
            :key="opt.id"
            :label="opt.name"
            :value="opt.id"
          >
            <span style="display:flex;align-items:center;gap:8px;">
              <img v-if="opt.avatar" :src="opt.avatar" style="width:24px;height:24px;border-radius:50%;" />
              <span v-else>👤</span>
              {{ opt.name }}
            </span>
          </el-option>
        </el-select>
      </el-form-item>
      <div v-if="relationshipForm.targetName" class="rel-preview">
        <img v-if="relationshipForm.avatar" :src="relationshipForm.avatar" style="width:40px;height:40px;border-radius:50%;" />
        <span v-else style="font-size:32px;">👤</span>
        <span style="font-weight:600;">{{ relationshipForm.targetName }}</span>
      </div>
      <el-form-item label="关系描述">
        <el-input v-model="relationshipForm.relation" placeholder="如：姐姐、朋友、搭档" />
      </el-form-item>
      <template #footer>
        <el-button @click="relationshipDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="confirmRelationship">确定</el-button>
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
  position: sticky;
  top: 0;
  z-index: 100;
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
  letter-spacing: -0.3px;
  margin: 0;
  color: var(--text);
}

.type-selector {
  display: flex;
  align-items: center;
  gap: 8px;
}

.type-btns {
  display: flex;
  gap: 4px;
}

.type-btn {
  font-size: 12px;
  padding: 5px 12px;
  border-radius: 6px;
  border: 1px solid var(--card-border);
  background: var(--bg-secondary);
  color: var(--text-secondary);
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}

.type-btn:hover {
  border-color: var(--accent);
  color: var(--text);
}

.type-btn.active {
  border-color: var(--accent);
  background: rgba(var(--accent-rgb, 64, 158, 255), 0.12);
  color: var(--accent);
  font-weight: 600;
}

.type-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
  justify-content: flex-end;
}

.header-btn {
  min-height: 32px;
  padding: 8px 15px;
}

.header-user-trigger {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 32px;
  padding: 8px 15px;
  border-radius: var(--el-border-radius-base, 4px);
  border: 1px solid var(--card-border);
  background: rgba(255, 255, 255, 0.04);
  color: var(--text);
  cursor: pointer;
  box-sizing: border-box;
  transition: all 0.2s ease;
}

.header-user-trigger:hover {
  border-color: rgba(108, 92, 231, 0.45);
  background: rgba(108, 92, 231, 0.08);
}

.container {
  max-width: 1480px;
  margin: 0 auto;
  padding: 24px 32px;
}

.workspace {
  display: grid;
  grid-template-columns: 240px 1fr;
  gap: 24px;
  align-items: start;
}

.config-sidebar {
  position: sticky;
  top: 100px;
}

.config-list-actions {
  margin-bottom: 12px;
}

.config-section-label {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 4px 6px;
  font-size: 12px;
  font-weight: 600;
  color: var(--text-primary);
  border-bottom: 1px solid var(--card-border);
  margin-bottom: 6px;
}

.config-section-label:first-child {
  padding-top: 2px;
}

.config-section-count {
  font-size: 11px;
  font-weight: 400;
  color: var(--text-secondary);
  background: rgba(255, 255, 255, 0.06);
  padding: 1px 8px;
  border-radius: 10px;
}

.config-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  max-height: 680px;
  overflow-y: auto;
}

.config-item {
  display: grid;
  grid-template-columns: 52px 1fr auto;
  gap: 8px;
  align-items: center;
  border: 1px solid var(--card-border);
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.02);
  padding: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.config-item:hover {
  border-color: rgba(108, 92, 231, 0.5);
  background: rgba(108, 92, 231, 0.08);
}

.config-item.active {
  border-color: var(--accent);
  background: rgba(108, 92, 231, 0.12);
}

.config-item.no-thumb {
  grid-template-columns: 1fr auto;
}

.config-thumb {
  width: 52px;
  height: 52px;
  border-radius: 8px;
  overflow: hidden;
  background: #1a1b24;
  border: 1px solid rgba(255, 255, 255, 0.08);
}

.config-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.thumb-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-secondary);
  font-size: 12px;
}

.config-meta {
  min-width: 0;
}

.config-name {
  font-size: 13px;
  font-weight: 600;
  color: var(--text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.config-profession {
  margin-top: 3px;
  font-size: 11px;
  color: var(--text-secondary);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.config-status {
  margin-top: 4px;
  font-size: 11px;
}

.status-bound {
  color: var(--success);
}

.status-unbound {
  color: var(--text-secondary);
}

.training-tag {
  display: inline-block;
  margin-left: 6px;
  padding: 0 6px;
  font-size: 10px;
  line-height: 16px;
  border-radius: 3px;
  background: rgba(230, 162, 60, 0.2);
  color: #e6a23c;
  vertical-align: middle;
}

.config-agent-type {
  font-size: 11px;
  color: var(--accent);
  margin-bottom: 2px;
}

.config-agent-sub {
  font-size: 11px;
  color: var(--text-secondary);
  margin-top: 2px;
}

.type-selector {
  display: flex;
  align-items: center;
  gap: 6px;
}

.type-selector-label {
  font-size: 12px;
  color: var(--text-secondary);
  white-space: nowrap;
}

.native-select {
  font-size: 13px;
  padding: 6px 10px;
  border-radius: 6px;
  border: 1px solid var(--card-border);
  background: var(--bg-secondary);
  color: var(--text-primary);
  outline: none;
  cursor: pointer;
}

.native-select:focus {
  border-color: var(--accent);
}

.inline-row {
  display: flex;
  gap: 16px;
}

.inline-field-item {
  flex: 1;
  margin-bottom: 0;
}

.inventory-actions {
  display: flex;
  gap: 8px;
  align-items: center;
}

.purchase-log-card .log-terminal {
  background: #0d1117;
  border-radius: 6px;
  padding: 12px 14px;
  max-height: 280px;
  overflow-y: auto;
  font-family: 'Consolas', 'Courier New', monospace;
  font-size: 12px;
  line-height: 1.7;
}

.purchase-log-card .log-terminal.log-empty {
  color: var(--text-secondary);
  text-align: center;
  padding: 32px 14px;
  font-family: inherit;
  font-size: 13px;
}

.log-line {
  display: flex;
  gap: 10px;
}

.log-line .log-time {
  color: #58a6ff;
  white-space: nowrap;
  flex-shrink: 0;
}

.log-line .log-msg {
  color: #c9d1d9;
}

.log-line.log-success .log-msg {
  color: #3fb950;
}

.log-line.log-warning .log-msg {
  color: #d29922;
}

.log-line.log-error .log-msg {
  color: #f85149;
}

.account-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border-bottom: 1px solid var(--card-border);
  flex-wrap: wrap;
}

.account-bar-label {
  font-size: 13px;
  color: var(--text-secondary);
  white-space: nowrap;
}

.chat-log {
  max-height: 400px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.chat-msg {
  padding: 10px 14px;
  border-radius: 8px;
  font-size: 13px;
}

.chat-msg.chat-user {
  background: rgba(var(--accent-rgb, 64, 158, 255), 0.08);
  border-left: 3px solid var(--accent);
}

.chat-msg.chat-ai {
  background: rgba(63, 185, 80, 0.06);
  border-left: 3px solid #3fb950;
}

.chat-sender {
  font-weight: 600;
  margin-bottom: 4px;
  font-size: 12px;
  color: var(--text-secondary);
}

.chat-time {
  font-weight: 400;
  margin-left: 8px;
  font-size: 11px;
  color: var(--text-muted, #666);
}

.chat-content {
  color: var(--text);
  line-height: 1.5;
}

.chat-actions {
  margin-top: 6px;
}

.chat-empty {
  text-align: center;
  padding: 40px;
  color: var(--text-secondary);
}

.ban-input-bar {
  margin-top: 14px;
}

.cron-select {
  min-width: auto;
  width: 56px;
}

.cron-preview {
  padding: 8px 14px;
  background: rgba(var(--accent-rgb, 64, 158, 255), 0.08);
  border-radius: 4px;
  font-size: 13px;
  color: var(--accent);
  font-weight: 600;
  margin-bottom: 12px;
}

.sr-channel-row {
  display: flex;
  align-items: flex-end;
  gap: 12px;
}

.sr-channel-input {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 10px;
}

.sr-input {
  flex: 1;
}

.sr-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--text-secondary);
  white-space: nowrap;
  min-width: 56px;
}

.sr-divider {
  height: 1px;
  background: var(--card-border);
  margin: 14px 0;
}

.sr-schedule {
  display: flex;
  align-items: flex-start;
  gap: 10px;
}

.sr-schedule-body {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.sr-period-group {
  flex-wrap: nowrap;
}

.sr-param-row {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
  padding: 8px 12px;
  background: var(--bg-secondary);
  border-radius: 6px;
}

.sr-param-text {
  font-size: 13px;
  color: var(--text);
}

.sr-param-sep {
  font-size: 13px;
  color: var(--text-secondary);
  margin: 0 2px;
}

.sr-scope {
  display: flex;
  align-items: center;
  gap: 10px;
}

.config-load-btn {
  align-self: center;
  flex-shrink: 0;
}

.rel-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.rel-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 10px;
  background: var(--bg-secondary);
  border-radius: 6px;
}

.rel-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  object-fit: cover;
}

.rel-avatar-placeholder {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--card-border);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
}

.rel-target-name {
  font-weight: 600;
  font-size: 13px;
}

.rel-text {
  font-size: 12px;
  color: var(--text-secondary);
}

.rel-relation {
  font-size: 13px;
  color: var(--accent);
  font-weight: 500;
}

.rel-preview {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px;
  background: var(--bg-secondary);
  border-radius: 6px;
  margin-bottom: 12px;
}

.binding-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.binding-subtitle {
  font-size: 13px;
  color: var(--text-secondary);
}

.binding-empty {
  padding: 32px 16px;
  text-align: center;
  color: var(--text-secondary);
  font-size: 13px;
}

.empty-list {
  padding: 24px 8px;
  text-align: center;
  color: var(--text-secondary);
  font-size: 13px;
}

.photo-input {
  display: none;
}

.avatar-upload-panel {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 14px 16px;
  margin-bottom: 18px;
  border: 1px solid var(--card-border);
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.02);
}

.avatar-upload-panel__preview {
  width: 88px;
  height: 88px;
  border-radius: 12px;
  overflow: hidden;
  background: #1a1b24;
  border: 1px solid rgba(255, 255, 255, 0.08);
  flex-shrink: 0;
}

.avatar-upload-panel__preview img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.avatar-upload-panel__empty {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-secondary);
  font-size: 12px;
}

.avatar-upload-panel__title {
  font-size: 15px;
  font-weight: 600;
  color: var(--text);
}

.avatar-upload-panel__desc {
  margin: 6px 0 12px;
  font-size: 13px;
  color: var(--text-secondary);
}

.layout {
  display: grid;
  grid-template-columns: 1fr 420px;
  gap: 24px;
  align-items: start;
}

.training-banner {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
  padding: 14px 20px;
  background: rgba(230, 162, 60, 0.12);
  border: 1px solid rgba(230, 162, 60, 0.3);
  border-radius: 10px;
  color: #f0c27a;
  font-size: 13px;
  line-height: 1.6;
}

.training-banner-icon {
  font-size: 24px;
  flex-shrink: 0;
}

.section-disabled {
  pointer-events: none;
  opacity: 0.55;
  user-select: none;
}

.section-disabled .preset-chip {
  cursor: not-allowed;
}

.preset-disabled {
  pointer-events: none;
  opacity: 0.5;
}

@media (max-width: 960px) {
  .layout { grid-template-columns: 1fr; }
}

.form-section {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.card-header {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 15px;
  font-weight: 600;
}

.card-icon {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  background: rgba(108, 92, 231, 0.12);
  color: var(--accent);
  flex-shrink: 0;
}

.preset-bar {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.preset-chip {
  padding: 6px 14px;
  border-radius: 20px;
  font-size: 13px;
  background: rgba(108, 92, 231, 0.08);
  border: 1px solid rgba(108, 92, 231, 0.2);
  color: var(--text-secondary);
  cursor: pointer;
  transition: all 0.2s ease;
  user-select: none;
}

.preset-chip:hover {
  background: rgba(108, 92, 231, 0.18);
  border-color: var(--accent);
  color: var(--text);
}

.preset-chip.active {
  background: rgba(108, 92, 231, 0.2);
  border-color: var(--accent);
  color: #b8acf0;
}

.tag-input-wrap {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  background: var(--input-bg);
  border: 1px solid var(--input-border);
  border-radius: var(--radius-sm);
  padding: 8px 10px;
  min-height: 44px;
  align-items: center;
  cursor: text;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
  width: 100%;
}

.native-select {
  width: 100%;
  background: var(--input-bg);
  border: 1px solid var(--input-border);
  border-radius: var(--radius-sm);
  padding: 10px 14px;
  padding-right: 38px;
  color: var(--text);
  font-size: 14px;
  font-family: inherit;
  outline: none;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
  appearance: none;
  cursor: pointer;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath d='M6 8L1 3h10z' fill='%239090a8'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 12px center;
}

.native-select:hover {
  border-color: var(--input-focus);
}

.native-select:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px var(--accent-glow);
}

.native-select.is-placeholder {
  color: var(--text-secondary);
}

.native-select option {
  color: var(--text);
  background: #12121a;
}

.tag-input-wrap:focus-within {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px var(--accent-glow);
}

.tag-input-wrap input {
  border: none;
  background: transparent;
  color: var(--text);
  font-size: 14px;
  outline: none;
  min-width: 120px;
  flex: 1;
  padding: 2px 4px;
  font-family: inherit;
}

.tag-input-action {
  border: 1px solid rgba(108, 92, 231, 0.28);
  background: rgba(108, 92, 231, 0.12);
  color: #b8acf0;
  border-radius: 999px;
  padding: 4px 10px;
  font-size: 12px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.tag-input-action:hover {
  border-color: var(--accent);
  color: var(--text);
  background: rgba(108, 92, 231, 0.2);
}

.slider-group {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
}

.native-range {
  flex: 1;
  height: 6px;
  accent-color: var(--accent);
  cursor: pointer;
}

.slider-value {
  width: 36px;
  text-align: center;
  font-size: 13px;
  font-weight: 600;
  color: var(--accent);
}

.preview-panel {
  position: sticky;
  top: 100px;
}

.preview-card :deep(.el-card__body) {
  background: #14141e;
}

.prompt-box {
  background: #0a0a12;
  border: 1px solid var(--card-border);
  border-radius: var(--radius-sm);
  padding: 16px;
  font-size: 13px;
  line-height: 1.7;
  color: #b8c8d8;
  max-height: 520px;
  overflow-y: auto;
  white-space: pre-wrap;
  word-break: break-word;
  font-family: "SF Mono", "Cascadia Code", "Consolas", "Microsoft YaHei", monospace;
}

.empty-hint {
  text-align: center;
  color: var(--text-secondary);
  display: block;
  padding: 40px 0;
}

.char-count {
  font-size: 12px;
  color: var(--text-secondary);
  text-align: right;
  display: block;
  margin-top: 4px;
}

.preview-actions {
  display: flex;
  gap: 8px;
  margin-top: 16px;
  flex-wrap: wrap;
}

@media (max-width: 960px) {
  .app-header {
    padding: 16px 20px;
    align-items: flex-start;
    gap: 16px;
    flex-direction: column;
  }

  .header-actions {
    width: 100%;
    justify-content: flex-start;
  }

  .workspace {
    grid-template-columns: 1fr;
  }

  .config-sidebar {
    position: static;
  }

  .config-list {
    max-height: none;
  }

  .avatar-upload-panel {
    flex-direction: column;
    align-items: flex-start;
  }

  .layout {
    width: 100%;
    grid-template-columns: 1fr;
  }

  .container {
    padding: 20px;
  }

  .preview-panel {
    position: static;
  }
}

/* ====== 秘书 AI 对话 ====== */
.secretary-chat-card {
  border: 1px solid var(--card-border);
  border-radius: var(--radius);
  overflow: hidden;
}

.secretary-chat-badge {
  font-size: 10px;
  background: var(--accent);
  color: #fff;
  padding: 2px 8px;
  border-radius: 10px;
  margin-left: 8px;
  font-weight: 600;
  letter-spacing: 0.5px;
}

.secretary-chat-body {
  height: 420px;
  overflow-y: auto;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  background: var(--bg);
  scroll-behavior: smooth;
}

.secretary-chat-body::-webkit-scrollbar {
  width: 5px;
}

.secretary-chat-body::-webkit-scrollbar-track {
  background: transparent;
}

.secretary-chat-body::-webkit-scrollbar-thumb {
  background: #2a2a3a;
  border-radius: 10px;
}

.secretary-chat-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  text-align: center;
  gap: 12px;
}

.secretary-chat-empty-icon {
  font-size: 40px;
  opacity: 0.5;
}

.secretary-chat-empty-text {
  color: var(--text-secondary);
  font-size: 13px;
  line-height: 1.8;
  white-space: pre-wrap;
  max-width: 320px;
}

.secretary-msg {
  display: flex;
  align-items: flex-end;
  gap: 8px;
  animation: msgFadeIn 0.3s ease;
}

@keyframes msgFadeIn {
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: translateY(0); }
}

.secretary-msg.user {
  justify-content: flex-end;
}

.secretary-msg.assistant {
  justify-content: flex-start;
}

.secretary-avatar {
  width: 32px;
  height: 32px;
  min-width: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  background: rgba(108, 92, 231, 0.15);
}

.user-avatar {
  background: rgba(108, 92, 231, 0.25);
}

.secretary-bubble {
  max-width: 75%;
  padding: 10px 14px;
  border-radius: 16px;
  font-size: 13px;
  line-height: 1.6;
  word-break: break-word;
  white-space: pre-wrap;
  position: relative;
}

.user-bubble {
  background: var(--accent);
  color: #fff;
  border-bottom-right-radius: 6px;
  box-shadow: 0 2px 12px var(--accent-glow);
}

.assistant-bubble {
  background: #1a1a26;
  color: var(--text);
  border-bottom-left-radius: 6px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.2);
  border: 1px solid var(--card-border);
}

.secretary-typing {
  display: flex;
  gap: 4px;
  align-items: center;
  padding: 6px 0;
}

.typing-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: var(--text-secondary);
  animation: typingBounce 1.4s infinite ease-in-out both;
}

.typing-dot:nth-child(1) { animation-delay: -0.32s; }
.typing-dot:nth-child(2) { animation-delay: -0.16s; }
.typing-dot:nth-child(3) { animation-delay: 0s; }

@keyframes typingBounce {
  0%, 80%, 100% { transform: scale(0.6); opacity: 0.4; }
  40% { transform: scale(1); opacity: 1; }
}

.secretary-chat-input-row {
  display: flex;
  gap: 10px;
  align-items: flex-end;
  padding: 14px 16px;
  background: #14141c;
  border-top: 1px solid var(--card-border);
}

.secretary-chat-textarea :deep(.el-textarea__inner) {
  border-radius: var(--radius-sm);
  padding: 10px 14px;
  font-size: 13px;
  line-height: 1.5;
  background: var(--input-bg);
  border: 1px solid var(--input-border);
  transition: border-color 0.2s, box-shadow 0.2s;
  resize: none;
  color: var(--text);
}

.secretary-chat-textarea :deep(.el-textarea__inner):focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px var(--accent-glow);
}

.secretary-send-btn {
  height: 38px;
  padding: 0 20px;
  border-radius: var(--radius-sm);
  font-weight: 500;
  background: var(--accent) !important;
  border: none !important;
  transition: all 0.2s;
}

.secretary-send-btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 16px var(--accent-glow);
}

.secretary-send-btn:disabled {
  background: #2a2a3a !important;
  transform: none;
  box-shadow: none;
}
/* ====== 秘书 AI 对话 END ====== */
</style>
