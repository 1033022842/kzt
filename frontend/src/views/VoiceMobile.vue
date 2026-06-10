<template>
  <div class="mobile-app">
    <!-- 顶部导航 -->
    <div class="app-header">
      <div class="header-title">🎤 语音操控中心</div>
      <div class="header-actions">
        <button v-if="isAdmin" class="admin-btn" @click="router.push('/admin')">⚙️ 管理</button>
        <div class="connection-badge" :class="{ connected: sseConnected }">
          <span class="dot"></span>
          {{ sseConnected ? '已连接' : '未连接' }}
        </div>
      </div>
    </div>

    <!-- Tab 切换 -->
    <div class="tab-bar">
      <div class="tab-item" :class="{ active: activeTab === 'voice' }" @click="activeTab = 'voice'">
        🎙️ 语音操控
      </div>
      <div class="tab-item" :class="{ active: activeTab === 'agents' }" @click="activeTab = 'agents'">
        🤖 智能体
      </div>
      <div class="tab-item" :class="{ active: activeTab === 'wallet' }" @click="activeTab = 'wallet'">
        💰 我的钱包
      </div>
    </div>

    <!-- 语音操控 Tab -->
    <div v-if="activeTab === 'voice'" class="tab-content">
      <div class="voice-area">
        <div class="voice-btn-wrap">
          <div class="voice-btn-glow" :class="{ active: recording }"></div>
          <button
            class="voice-btn"
            :class="{ recording }"
            @mousedown.prevent="startRecord"
            @mouseup.prevent="stopRecord"
            @mouseleave.prevent="stopRecord"
            @touchstart.prevent="startRecord"
            @touchend.prevent="stopRecord"
            @touchcancel.prevent="stopRecord"
            :disabled="sending || voiceMode === 'checking'"
          >
            <span class="mic-icon">{{ recording ? '🔴' : (voiceMode === 'checking' ? '⏳' : '🎙️') }}</span>
          </button>
        </div>
        <div class="voice-hint" :class="{ recording }">
          {{ voiceMode === 'checking' ? '正在检测语音通道...' : voiceMode === 'deepgram' ? (recording ? '正在聆听...松开停止' : '按住说话（备用通道）') : (recording ? '正在聆听...松开停止' : '按住说话') }}
        </div>
        <div class="voice-preview" v-if="voiceText">{{ voiceText }}</div>
      </div>

      <!-- 文本输入 -->
      <div class="text-input-bar">
        <input v-model="textInput" placeholder="或输入指令..." @keydown.enter="sendText" />
        <button class="send-btn" @click="sendText" :disabled="sending">
          {{ sending ? '...' : '→' }}
        </button>
      </div>

      <!-- 处理状态 -->
      <div v-if="sending" class="processing-bar">
        <span class="dot-pulse"></span>
        {{ processingStatus }}
      </div>

      <!-- 指令历史 -->
      <div class="section-title">📋 指令历史</div>
      <div class="history-list" v-if="history.length">
        <div v-for="(item, idx) in history" :key="idx" class="history-item">
          <div class="cmd-text">{{ item.icon }} "{{ item.text }}"</div>
          <div class="cmd-meta">
            <span class="cmd-agent">→ {{ item.agentLabel }}</span>
            <span class="cmd-status" :class="item.status">{{ statusLabels[item.status] }}</span>
            <span class="cmd-time">{{ item.time }}</span>
          </div>
        </div>
      </div>
      <div v-else class="empty-state">暂无指令，按住麦克风说话或输入文字开始</div>
    </div>

    <!-- 智能体 Tab -->
    <div v-if="activeTab === 'agents'" class="tab-content">
      <div class="section-title">⚡ 智能体运行状态</div>
      <div class="agent-list">
        <div class="agent-card" v-for="agent in agentList" :key="agent.key">
          <span class="agent-icon">{{ agent.icon }}</span>
          <div class="agent-info">
            <div class="agent-name">{{ agent.name }}</div>
            <div class="agent-detail">{{ agent.state }}</div>
          </div>
          <span class="agent-status-badge" :class="agent.status">
            {{ agent.statusLabel }}
          </span>
        </div>
      </div>

      <!-- 最近操作日志 -->
      <div class="section-title" style="margin-top:20px;">📜 最近操作</div>
      <div class="log-mini-list" v-if="recentLogs.length">
        <div v-for="log in recentLogs" :key="log.time" class="log-mini-item">
          <span class="log-mini-time">{{ formatTime(log.time) }}</span>
          <span class="log-mini-agent">[{{ agentNameMap[log.agent] || log.agent }}]</span>
          <span class="log-mini-msg">{{ log.action }}</span>
        </div>
      </div>
      <div v-else class="empty-state">暂无操作记录</div>
    </div>

    <!-- 钱包 Tab -->
    <div v-if="activeTab === 'wallet'" class="tab-content">
      <!-- 余额卡片 -->
      <div class="balance-card">
        <div class="balance-label">账户余额</div>
        <div class="balance-amount">${{ balance.toFixed(2) }}</div>
        <div class="balance-quota">
          智能体配额 {{ agentUsed }} / {{ agentQuota }}（可用 {{ agentAvailable }} 个）
        </div>
      </div>

      <!-- 套餐购买 -->
      <div class="section-title">📦 购买套餐</div>
      <div class="package-list">
        <div v-for="pkg in packages" :key="pkg.key" class="package-card"
          :class="{ selected: selectedPackage === pkg.key, disabled: balance < pkg.price }"
          @click="selectedPackage = pkg.key">
          <div class="pkg-name">{{ pkg.name }}</div>
          <div class="pkg-price">${{ pkg.price }}</div>
          <div class="pkg-desc">{{ pkg.desc }}</div>
          <div v-if="balance < pkg.price" class="pkg-insufficient">余额不足</div>
        </div>
      </div>
      <button class="buy-btn" @click="buyPackage" :disabled="!selectedPackage || balance < packages.find(p => p.key === selectedPackage)?.price || buying">
        {{ buying ? '购买中...' : '立即购买' }}
      </button>

      <!-- 充值记录 -->
      <div class="section-title" style="margin-top:20px;">💳 充值记录</div>
      <div class="record-list" v-if="rechargeRecords.length">
        <div v-for="r in rechargeRecords" :key="r.id" class="record-item">
          <div class="record-left">
            <div class="record-amount">${{ r.amount }}</div>
            <div class="record-time">{{ r.create_time }}</div>
          </div>
          <el-tag :type="r.status === 'confirmed' ? 'success' : r.status === 'pending' ? 'warning' : 'danger'" size="small">
            {{ r.status === 'confirmed' ? '已到账' : r.status === 'pending' ? '审核中' : '已驳回' }}
          </el-tag>
        </div>
      </div>
      <div v-else class="empty-state">暂无充值记录</div>

      <!-- 购买记录 -->
      <div class="section-title" style="margin-top:20px;">🛒 购买记录</div>
      <div class="record-list" v-if="purchaseRecords.length">
        <div v-for="r in purchaseRecords" :key="r.id" class="record-item">
          <div class="record-left">
            <div class="record-amount">{{ r.package_name }}</div>
            <div class="record-time">{{ r.create_time }}</div>
          </div>
          <span style="color:#888;font-size:12px;">-${{ r.amount }}</span>
        </div>
      </div>
      <div v-else class="empty-state">暂无购买记录</div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { sendCommand, recognizeAudio } from '../api/voice'
import { getBalance, getRechargeRecords, getPurchaseRecords, purchase } from '../api/payment'

const router = useRouter()

const activeTab = ref('voice')
const textInput = ref('')
const sending = ref(false)
const processingStatus = ref('')
const sseConnected = ref(false)
const voiceText = ref('')
const recording = ref(false)
const voiceMode = ref('checking') // 'checking' | 'web-speech' | 'deepgram'

// 管理员判断
const isAdmin = computed(() => {
  try {
    const info = JSON.parse(localStorage.getItem('user_info') || '{}')
    return info.role === 'admin'
  } catch { return false }
})

const history = ref([])
const recentLogs = ref([])

// agent state
const agentStates = ref({
  marketing: { status: 'idle', label: '空闲', accounts: 0 },
  inventory: { status: 'idle', label: '空闲', accounts: 0 },
  secretary: { status: 'ready', label: '就绪', accounts: 0 }
})

// wallet
const balance = ref(0)
const agentQuota = ref(1)
const agentUsed = ref(0)
const agentAvailable = computed(() => Math.max(0, agentQuota.value - agentUsed.value))
const rechargeRecords = ref([])
const purchaseRecords = ref([])
const selectedPackage = ref('')
const buying = ref(false)

const packages = [
  { key: 'single', name: '基础版', price: 799, desc: '1 个智能体' },
  { key: 'double', name: '进阶版', price: 1399, desc: '2 个智能体' },
  { key: 'triple', name: '旗舰版', price: 1999, desc: '3 个智能体' },
]

const statusLabels = { running: '执行中', triggered: '已触发', done: '已完成', error: '失败' }
const agentNameMap = { marketing: '营销', inventory: '进销存', secretary: '秘书' }

const agentList = computed(() => [
  { key: 'marketing', icon: '🤖', name: '营销智能体', state: `已绑定 ${agentStates.value.marketing?.accounts || 0} 个账号`, status: agentStates.value.marketing?.status || 'idle', statusLabel: agentStates.value.marketing?.label || '空闲' },
  { key: 'inventory', icon: '📦', name: '进销存智能体', state: `库存管理`, status: agentStates.value.inventory?.status || 'idle', statusLabel: agentStates.value.inventory?.label || '空闲' },
  { key: 'secretary', icon: '💬', name: '秘书智能体', state: `就绪`, status: agentStates.value.secretary?.status || 'ready', statusLabel: agentStates.value.secretary?.label || '就绪' },
])

// 语音识别 — Web Speech API，失败自动降级 Deepgram
const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition
let recognition = null
let recordStartTime = 0
let accumulatedText = ''
let mediaRecorder = null
let audioChunks = []

// 页面加载时预检测 Web Speech 是否可用
function detectVoiceChannel() {
  if (!SpeechRecognition) {
    voiceMode.value = 'deepgram'
    return
  }

  const testRec = new SpeechRecognition()
  testRec.lang = 'zh-CN'
  testRec.continuous = false

  let resolved = false
  const done = (mode) => {
    if (resolved) return
    resolved = true
    voiceMode.value = mode
    try { testRec.stop() } catch (e) { /* */ }
  }

  testRec.onstart = () => done('web-speech')
  testRec.onerror = () => done('deepgram')  // WebView 里任何错误都切 deepgram
  testRec.onend = () => {
    if (!resolved) done('deepgram')
  }

  try {
    testRec.start()
  } catch (e) {
    done('deepgram')
  }

  // 2 秒内没响应也切 deepgram
  setTimeout(() => {
    if (!resolved) done('deepgram')
  }, 2000)
}

function startRecord() {
  if (sending.value || voiceMode.value === 'checking') return

  if (voiceMode.value === 'web-speech') {
    startWebSpeech()
  } else {
    startDeepgramFallback()
  }
}

function stopRecord() {
  if (!recording.value) return

  if (recognition) {
    try { recognition.stop() } catch (e) { /* */ }
  }
  if (mediaRecorder && mediaRecorder.state === 'recording') {
    mediaRecorder.stop()
  }
}

function startWebSpeech() {
  recognition = new SpeechRecognition()
  recognition.lang = 'zh-CN'
  recognition.interimResults = true
  recognition.continuous = true

  recognition.onstart = () => {
    recording.value = true
    recordStartTime = Date.now()
    accumulatedText = ''
    voiceText.value = ''
  }

  recognition.onresult = (event) => {
    let transcript = ''
    for (let i = event.resultIndex; i < event.results.length; i++) {
      transcript += (event.results[i][0]?.transcript || '')
    }
    voiceText.value = transcript
    accumulatedText = transcript
  }

  recognition.onend = () => {
    recording.value = false
    const text = accumulatedText.trim()
    if (text && (Date.now() - recordStartTime) >= 500) {
      voiceText.value = ''
      submitCommand(text)
    }
  }

  recognition.onerror = (e) => {
    recording.value = false
    if (e.error === 'not-allowed') {
      ElMessage.warning('麦克风权限被拒绝，请使用文本输入')
    } else if (e.error !== 'no-speech') {
      voiceMode.value = 'deepgram'
      ElMessage.info('已切换语音通道，请重新按住说话')
    }
  }

  try {
    recognition.start()
  } catch (e) {
    recording.value = false
    ElMessage.warning('语音启动失败，请重试或使用文本输入')
  }
}

// Deepgram 降级：浏览器 MediaRecorder 录音 → 上传到服务器 → 服务器调 Deepgram
async function startDeepgramFallback() {
  try {
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true })
    audioChunks = []

    // Android WebView 可能不支持 webm，自动选支持的格式
    let mimeType = ''
    const tryTypes = ['audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus']
    for (const t of tryTypes) {
      if (MediaRecorder.isTypeSupported(t)) { mimeType = t; break }
    }
    const blobType = mimeType || 'audio/webm'

    mediaRecorder = new MediaRecorder(stream, mimeType ? { mimeType } : undefined)

    let totalSize = 0
    mediaRecorder.ondataavailable = (e) => {
      if (e.data.size > 0) {
        audioChunks.push(e.data)
        totalSize += e.data.size
      }
    }

    mediaRecorder.onerror = () => {
      recording.value = false
      stream.getTracks().forEach(t => t.stop())
      ElMessage.warning('录音异常，请使用文本输入')
    }

    mediaRecorder.onstop = async () => {
      stream.getTracks().forEach(t => t.stop())
      recording.value = false

      if (audioChunks.length === 0 || totalSize < 100) {
        if (Date.now() - recordStartTime < 500) return  // 太短忽略
        ElMessage.warning('未录制到声音，请重试')
        return
      }

      sending.value = true
      processingStatus.value = '正在识别语音...'

      const audioBlob = new Blob(audioChunks, { type: blobType })
      try {
        const res = await recognizeAudio(audioBlob)
        const text = res.data?.text?.trim() || ''
        sending.value = false
        processingStatus.value = ''
        if (text) {
          voiceText.value = ''
          submitCommand(text)
        } else {
          ElMessage.warning('未识别到语音内容，请使用文本输入')
        }
      } catch (e) {
        sending.value = false
        processingStatus.value = ''
        // request.js 拦截器已经显示了业务错误，这里只兜底
        if (!e?.response?.data?.msg) {
          ElMessage.warning('语音识别失败，请使用文本输入')
        }
      }
    }

    recording.value = true
    recordStartTime = Date.now()
    voiceText.value = ''
    mediaRecorder.start(500)  // 每 500ms 收集一次，避免 Android 上一股脑给空数据
  } catch (e) {
    recording.value = false
    if (e.name === 'NotAllowedError') {
      ElMessage.warning('麦克风权限被拒绝，请在设置中允许或使用文本输入')
    } else {
      ElMessage.warning('无法启动录音，请使用文本输入')
    }
  }
}

function sendText() {
  const txt = textInput.value.trim()
  if (!txt || sending.value) return
  submitCommand(txt)
  textInput.value = ''
}

async function submitCommand(message) {
  sending.value = true
  processingStatus.value = 'AI 正在理解指令...'

  try {
    const res = await sendCommand(message)
    const data = res.data
    processingStatus.value = '正在触发智能体...'

    // 更新历史
    const icons = { marketing: '🤖', inventory: '📦', secretary: '💬' }
    const labels = { marketing: '营销智能体', inventory: '进销存智能体', secretary: '秘书智能体' }

    history.value.unshift({
      text: message,
      icon: icons[data.agent] || '🤖',
      agentLabel: labels[data.agent] || data.agent,
      status: 'triggered',
      time: new Date().toLocaleTimeString('zh-CN', { hour12: false })
    })
    if (history.value.length > 20) history.value.pop()

    // 更新状态
    if (data.states) {
      agentStates.value = data.states
    }

    // 更新日志
    if (data.log) {
      recentLogs.value.unshift(data.log)
      if (recentLogs.value.length > 30) recentLogs.value.pop()
    }

    // 300ms 后标记为已完成
    setTimeout(() => {
      const item = history.value[history.value.length - 1]
    }, 300)

    ElMessage.success(`已触发：${data.action}`)
  } catch (e) {
    ElMessage.error('指令发送失败: ' + (e.message || '网络错误'))
  } finally {
    sending.value = false
    processingStatus.value = ''
  }
}

// wallet — 懒加载
const walletLoaded = ref(false)

async function loadWallet() {
  if (walletLoaded.value) return
  try {
    const res = await getBalance()
    balance.value = res.data.balance || 0
    agentQuota.value = res.data.agent_quota || 1
    agentUsed.value = res.data.agent_used || 0
  } catch (e) { /* 后端未启动时静默 */ }

  try {
    const r1 = await getRechargeRecords()
    rechargeRecords.value = (r1.data?.list || []).slice(0, 10)
  } catch (e) { /* */ }

  try {
    const r2 = await getPurchaseRecords()
    purchaseRecords.value = (r2.data?.list || []).slice(0, 10)
  } catch (e) { /* */ }

  walletLoaded.value = true
}

// 监听 tab 切换，懒加载钱包
watch(activeTab, (tab) => {
  if (tab === 'wallet') {
    loadWallet()
  }
})

async function buyPackage() {
  if (!selectedPackage.value) return
  const pkg = packages.find(p => p.key === selectedPackage.value)
  if (!pkg || balance.value < pkg.price) {
    ElMessage.warning('余额不足')
    return
  }
  buying.value = true
  try {
    await purchase({ package_type: selectedPackage.value })
    ElMessage.success('购买成功！')
    selectedPackage.value = ''
    await loadWallet()
  } catch (e) {
    // error handled by interceptor
  } finally {
    buying.value = false
  }
}

function formatTime(ts) {
  return new Date(ts).toLocaleTimeString('zh-CN', { hour12: false })
}

// 轮询状态（替代 SSE，PHP 内置服务器不支持长连接并发）
import { getVoiceStatus } from '../api/voice'
let pollTimer = null
const pollInterval = 2500  // 2.5 秒轮询

function startPolling() {
  stopPolling()
  pollTimer = setInterval(async () => {
    try {
      const res = await getVoiceStatus()
      const data = res.data
      if (data?.states) {
        agentStates.value = data.states
        sseConnected.value = true
      }
      if (data?.logs?.length) {
        // 合并去重
        const existingIds = new Set(recentLogs.value.map(l => l.id))
        for (const log of data.logs) {
          if (log?.id && !existingIds.has(log.id)) {
            recentLogs.value.unshift(log)
            existingIds.add(log.id)
          }
        }
        if (recentLogs.value.length > 30) {
          recentLogs.value.splice(30)
        }
      }
    } catch (e) {
      sseConnected.value = false
    }
  }, pollInterval)
}

function stopPolling() {
  if (pollTimer) {
    clearInterval(pollTimer)
    pollTimer = null
  }
}

onMounted(() => {
  startPolling()
  detectVoiceChannel()
})

onUnmounted(() => {
  stopPolling()
})
</script>

<style scoped>
.mobile-app {
  max-width: 430px;
  margin: 0 auto;
  min-height: 100vh;
  background: #0f0f14;
  color: #e8e8f0;
  font-family: -apple-system, BlinkMacSystemFont, 'PingFang SC', sans-serif;
  padding-bottom: 40px;
}
.app-header {
  padding: 16px 20px 12px;
  text-align: center;
  border-bottom: 1px solid #2a2a3a;
}
.header-title { font-size: 18px; font-weight: 700; }
.header-actions {
  display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 6px;
}
.admin-btn {
  background: rgba(255,165,0,0.15); color: #ffa500; border: 1px solid rgba(255,165,0,0.3);
  border-radius: 20px; padding: 3px 14px; font-size: 11px; cursor: pointer;
}
.connection-badge {
  display: inline-flex; align-items: center; gap: 6px;
  background: rgba(255,118,117,0.15); border-radius: 20px;
  padding: 3px 12px; font-size: 11px; color: #ff7675; margin-top: 6px;
}
.connection-badge.connected { background: rgba(0,206,201,0.15); color: #00cec9; }
.connection-badge .dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

.tab-bar {
  display: flex; border-bottom: 1px solid #2a2a3a;
}
.tab-item {
  flex: 1; text-align: center; padding: 12px 0; font-size: 13px; color: #888;
  cursor: pointer; transition: all 0.2s; border-bottom: 2px solid transparent;
}
.tab-item.active { color: #6c5ce7; border-bottom-color: #6c5ce7; font-weight: 600; }
.tab-content { padding: 16px; }

/* voice area */
.voice-area { display: flex; flex-direction: column; align-items: center; margin-bottom: 16px; }
.voice-btn-wrap { position: relative; width: 100px; height: 100px; margin-bottom: 8px; }
.voice-btn-glow {
  position: absolute; inset: -10px; border-radius: 50%;
  background: rgba(108,92,231,0.4); filter: blur(18px); opacity: 0; transition: opacity 0.3s;
}
.voice-btn-glow.active { opacity: 1; animation: glowPulse 1.5s ease-in-out infinite; }
@keyframes glowPulse {
  0%, 100% { transform: scale(1); opacity: 0.6; }
  50% { transform: scale(1.12); opacity: 1; }
}
.voice-btn {
  position: relative; width: 100px; height: 100px; border-radius: 50%;
  border: 2px solid #3a3a4a; background: #1a1a24; cursor: pointer;
  display: flex; align-items: center; justify-content: center; z-index: 1; transition: all 0.3s;
}
.voice-btn.recording { border-color: #6c5ce7; background: rgba(108,92,231,0.1); transform: scale(1.06); }
.mic-icon { font-size: 38px; }
.voice-hint { font-size: 12px; color: #888; margin-top: 4px; }
.voice-hint.recording { color: #6c5ce7; font-weight: 500; }
.voice-preview { font-size: 14px; margin-top: 6px; min-height: 20px; text-align: center; font-style: italic; }

.text-input-bar { display: flex; gap: 8px; margin-bottom: 12px; }
.text-input-bar input {
  flex: 1; padding: 10px 14px; border-radius: 10px; border: 1px solid #3a3a4a;
  background: #1a1a24; color: #e8e8f0; font-size: 14px; outline: none;
}
.text-input-bar input:focus { border-color: #6c5ce7; }
.send-btn {
  width: 44px; border-radius: 10px; border: none; background: #6c5ce7;
  color: #fff; font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center;
}
.send-btn:disabled { opacity: 0.5; }

.processing-bar {
  display: flex; align-items: center; gap: 8px; padding: 10px 14px;
  background: rgba(108,92,231,0.1); border: 1px solid rgba(108,92,231,0.3);
  border-radius: 10px; font-size: 13px; color: #a29bfe; margin-bottom: 12px;
}
.dot-pulse { width: 6px; height: 6px; border-radius: 50%; background: #6c5ce7; animation: dotBounce 1s infinite; }
@keyframes dotBounce { 0%, 100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.5); opacity: 0.5; } }

.section-title { font-size: 12px; font-weight: 600; color: #888; letter-spacing: 1px; margin-bottom: 10px; }
.empty-state { text-align: center; color: #555; font-size: 13px; padding: 24px 0; }

/* history */
.history-list { display: flex; flex-direction: column; gap: 8px; margin-bottom: 16px; }
.history-item {
  background: #1a1a24; border: 1px solid #2a2a3a; border-radius: 10px; padding: 10px 12px;
}
.cmd-text { font-size: 14px; font-weight: 500; margin-bottom: 4px; }
.cmd-meta { display: flex; align-items: center; gap: 8px; font-size: 11px; }
.cmd-agent { color: #888; }
.cmd-time { color: #555; margin-left: auto; }
.cmd-status { padding: 1px 8px; border-radius: 8px; font-size: 10px; font-weight: 500; }
.cmd-status.running { background: rgba(108,92,231,0.2); color: #a29bfe; }
.cmd-status.triggered { background: rgba(0,206,201,0.2); color: #00cec9; }
.cmd-status.done { background: rgba(136,136,160,0.2); color: #888; }
.cmd-status.error { background: rgba(255,118,117,0.2); color: #ff7675; }

/* agent cards */
.agent-list { display: flex; flex-direction: column; gap: 8px; }
.agent-card {
  display: flex; align-items: center; gap: 12px; padding: 12px 14px;
  background: #1a1a24; border: 1px solid #2a2a3a; border-radius: 10px;
}
.agent-icon { font-size: 28px; }
.agent-info { flex: 1; }
.agent-name { font-size: 14px; font-weight: 600; }
.agent-detail { font-size: 12px; color: #888; margin-top: 2px; }
.agent-status-badge { font-size: 11px; padding: 3px 10px; border-radius: 8px; }
.agent-status-badge.idle, .agent-status-badge.ready { background: rgba(136,136,160,0.15); color: #888; }
.agent-status-badge.running { background: rgba(0,206,201,0.15); color: #00cec9; }

/* log mini */
.log-mini-list { display: flex; flex-direction: column; gap: 4px; margin-bottom: 16px; }
.log-mini-item { font-size: 12px; padding: 4px 0; border-bottom: 1px solid #1a1a24; display: flex; gap: 6px; }
.log-mini-time { color: #555; white-space: nowrap; }
.log-mini-agent { color: #6c5ce7; white-space: nowrap; }
.log-mini-msg { color: #ccc; flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* wallet */
.balance-card {
  background: linear-gradient(135deg, #6c5ce7, #a29bfe); border-radius: 14px;
  padding: 20px; text-align: center; margin-bottom: 16px;
}
.balance-label { font-size: 13px; opacity: 0.8; }
.balance-amount { font-size: 36px; font-weight: 700; margin: 8px 0; }
.balance-quota { font-size: 12px; opacity: 0.85; }

.package-list { display: flex; flex-direction: column; gap: 8px; margin-bottom: 12px; }
.package-card {
  padding: 12px 14px; border-radius: 10px; border: 2px solid #2a2a3a;
  background: #1a1a24; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 12px;
}
.package-card.selected { border-color: #6c5ce7; background: rgba(108,92,231,0.1); }
.package-card.disabled { opacity: 0.5; }
.pkg-name { font-size: 15px; font-weight: 600; }
.pkg-price { font-size: 18px; font-weight: 700; color: #6c5ce7; }
.pkg-desc { font-size: 12px; color: #888; margin-left: auto; }
.pkg-insufficient { font-size: 11px; color: #ff7675; }

.buy-btn {
  width: 100%; padding: 12px; border-radius: 10px; border: none;
  background: #6c5ce7; color: #fff; font-size: 15px; font-weight: 600; cursor: pointer;
}
.buy-btn:disabled { opacity: 0.4; cursor: not-allowed; }

.record-list { display: flex; flex-direction: column; gap: 6px; }
.record-item {
  display: flex; align-items: center; justify-content: space-between;
  padding: 8px 12px; background: #1a1a24; border-radius: 8px;
}
.record-left { display: flex; flex-direction: column; gap: 2px; }
.record-amount { font-size: 14px; font-weight: 500; }
.record-time { font-size: 11px; color: #888; }
</style>
