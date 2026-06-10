<template>
  <div class="monitor-page">
    <!-- header -->
    <div class="monitor-header">
      <h2>📡 智能体操作监控</h2>
      <div class="header-right">
       
        <span class="connection-status" :class="{ connected: sseConnected }">
          <span class="dot"></span>
          {{ sseConnected ? 'SSE 已连接' : '连接断开' }}
        </span>
         <el-button size="small" @click="router.push('/')">← 返回首页</el-button>
        <el-button size="small" @click="clearLogs">清空日志</el-button>
      </div>
    </div>

    <!-- 智能体状态卡片 -->
    <div class="agent-cards">
      <div class="agent-card" v-for="a in agentCards" :key="a.key" :class="`agent-${a.key} ${a.status}`">
        <div class="agent-card-icon">{{ a.icon }}</div>
        <div class="agent-card-body">
          <div class="agent-card-name">{{ a.name }}</div>
          <div class="agent-card-state">{{ a.state }}</div>
        </div>
        <div class="agent-card-badge" :class="a.status">
          {{ a.statusLabel }}
        </div>
      </div>
    </div>

    <div class="monitor-layout">
      <!-- 操作日志区 -->
      <div class="log-panel">
        <div class="panel-header">
          <span class="panel-icon">📋</span>
          <span>操作日志</span>
          <span class="log-count">共 {{ filteredLogs.length }} 条</span>
          <div class="filter-btns">
            <button
              v-for="f in filters"
              :key="f.key"
              class="filter-btn"
              :class="{ active: currentFilter === f.key }"
              @click="currentFilter = f.key"
            >
              {{ f.label }}
            </button>
          </div>
        </div>

        <div class="log-terminal" ref="logTerminal">
          <div v-if="filteredLogs.length === 0" class="log-empty">
            等待指令... 打开移动端语音操控页面发出语音指令即可看到操作
          </div>

          <div v-for="log in filteredLogs" :key="log.id" class="log-entry">
            <div class="log-entry-header">
              <span class="log-agent-badge" :class="`badge-${log.agent}`">
                {{ agentLabels[log.agent] || log.agent }}
              </span>
              <span class="log-action">{{ log.action }}</span>
              <span class="log-time">{{ formatTime(log.time) }}</span>
            </div>

            <!-- 账号列表 -->
            <div v-if="log.accounts && log.accounts.length" class="log-accounts">
              <span v-for="acc in log.accounts" :key="acc.account" class="log-account-tag">
                {{ acc.platform }}: {{ acc.account }}
              </span>
            </div>

            <!-- 操作步骤 -->
            <div class="log-steps">
              <div
                v-for="step in log.steps"
                :key="step.time"
                class="log-step"
                :class="`step-${step.type}`"
              >
                <span class="step-time">{{ formatTime(step.time) }}</span>
                <span class="step-dot" :class="step.type"></span>
                <span class="step-msg">{{ step.msg }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 右侧信息区 -->
      <div class="info-panel">
        <div class="info-card">
          <div class="panel-header">
            <span class="panel-icon">⚡</span>
            <span>智能体状态</span>
          </div>
          <div class="state-list">
            <div v-for="s in stateList" :key="s.key" class="state-item">
              <span class="state-icon">{{ s.icon }}</span>
              <span class="state-name">{{ s.name }}</span>
              <span class="state-val" :class="s.status">{{ s.statusLabel }}</span>
            </div>
          </div>
        </div>

        <div class="info-card">
          <div class="panel-header">
            <span class="panel-icon">📊</span>
            <span>统计</span>
          </div>
          <div class="stats-grid">
            <div class="stat-item">
              <div class="stat-value">{{ stats.total }}</div>
              <div class="stat-label">总操作数</div>
            </div>
            <div class="stat-item">
              <div class="stat-value">{{ stats.marketing }}</div>
              <div class="stat-label">营销</div>
            </div>
            <div class="stat-item">
              <div class="stat-value">{{ stats.inventory }}</div>
              <div class="stat-label">进销存</div>
            </div>
            <div class="stat-item">
              <div class="stat-value">{{ stats.secretary }}</div>
              <div class="stat-label">秘书</div>
            </div>
          </div>
        </div>

        <div class="info-card">
          <div class="panel-header">
            <span class="panel-icon">💡</span>
            <span>使用说明</span>
          </div>
          <div class="help-text">
            <p>1. 在手机上打开 <code>/voice-mobile</code> 页面</p>
            <p>2. 按住麦克风说出指令</p>
            <p>3. 本页面将实时显示操作过程</p>
            <p>4. 操作均为模拟演示，非真实执行</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { getVoiceStatus } from '../api/voice'

const router = useRouter()

const sseConnected = ref(false)
const logs = ref([])
const currentFilter = ref('all')

const filters = [
  { key: 'all', label: '全部' },
  { key: 'marketing', label: '营销' },
  { key: 'inventory', label: '进销存' },
  { key: 'secretary', label: '秘书' },
]

const agentLabels = { marketing: '营销', inventory: '进销存', secretary: '秘书' }

const agentStates = ref({
  marketing: { status: 'idle', label: '空闲', accounts: 0 },
  inventory: { status: 'idle', label: '空闲', accounts: 0 },
  secretary: { status: 'ready', label: '就绪', accounts: 0 },
})

const agentCards = computed(() => [
  { key: 'marketing', icon: '🤖', name: '营销智能体', state: `已绑定 ${agentStates.value.marketing?.accounts || 0} 个`, status: agentStates.value.marketing?.status || 'idle', statusLabel: agentStates.value.marketing?.label || '空闲' },
  { key: 'inventory', icon: '📦', name: '进销存智能体', state: '库存管理', status: agentStates.value.inventory?.status || 'idle', statusLabel: agentStates.value.inventory?.label || '空闲' },
  { key: 'secretary', icon: '💬', name: '秘书智能体', state: '就绪', status: agentStates.value.secretary?.status || 'ready', statusLabel: agentStates.value.secretary?.label || '就绪' },
])

const stateList = computed(() => agentCards.value)

const filteredLogs = computed(() => {
  if (currentFilter.value === 'all') return logs.value
  return logs.value.filter(l => l.agent === currentFilter.value)
})

const stats = computed(() => ({
  total: logs.value.length,
  marketing: logs.value.filter(l => l.agent === 'marketing').length,
  inventory: logs.value.filter(l => l.agent === 'inventory').length,
  secretary: logs.value.filter(l => l.agent === 'secretary').length,
}))

function formatTime(ts) {
  return new Date(ts).toLocaleTimeString('zh-CN', { hour12: false })
}

function clearLogs() {
  logs.value = []
}

// 轮询状态（替代 SSE，PHP 内置服务器不支持长连接并发）
let pollTimer = null
const pollInterval = 2000

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
        const existingIds = new Set(logs.value.map(l => l.id))
        for (const log of data.logs) {
          if (log?.id && !existingIds.has(log.id)) {
            logs.value.unshift(log)
            existingIds.add(log.id)
          }
        }
        if (logs.value.length > 200) {
          logs.value.splice(200)
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
})

onUnmounted(() => {
  stopPolling()
})
</script>

<style scoped>
.monitor-page {
  min-height: 100vh;
  background: #0f0f14;
  color: #e8e8f0;
  padding: 24px;
  font-family: -apple-system, BlinkMacSystemFont, 'PingFang SC', sans-serif;
}

.monitor-header {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 20px; padding-bottom: 16px;
  border-bottom: 1px solid #2a2a3a;
}
.monitor-header h2 { margin: 0; font-size: 20px; font-weight: 700; }
.header-right { display: flex; align-items: center; gap: 16px; }

.connection-status { display: flex; align-items: center; gap: 6px; font-size: 13px; color: #ff7675; }
.connection-status.connected { color: #00cec9; }
.connection-status .dot { width: 7px; height: 7px; border-radius: 50%; background: currentColor; animation: pulse 2s infinite; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.4} }

/* agent cards */
.agent-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 20px; }
.agent-card {
  background: #1a1a24; border: 1px solid #2a2a3a; border-radius: 12px;
  padding: 16px; display: flex; align-items: center; gap: 12px; transition: all 0.3s;
}
.agent-card.running { border-color: #00cec9; box-shadow: 0 0 20px rgba(0,206,201,0.1); }
.agent-card-icon { font-size: 32px; }
.agent-card-body { flex: 1; }
.agent-card-name { font-size: 15px; font-weight: 600; }
.agent-card-state { font-size: 12px; color: #888; margin-top: 2px; }
.agent-card-badge { font-size: 11px; padding: 3px 10px; border-radius: 8px; }
.agent-card-badge.idle, .agent-card-badge.ready { background: rgba(136,136,160,0.15); color: #888; }
.agent-card-badge.running { background: rgba(0,206,201,0.15); color: #00cec9; }

/* layout */
.monitor-layout {
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: 20px;
  height: calc(100vh - 140px);
}

/* log panel */
.log-panel {
  background: #1a1a24;
  border: 1px solid #2a2a3a;
  border-radius: 12px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}
.panel-header {
  padding: 14px 16px;
  border-bottom: 1px solid #2a2a3a;
  display: flex; align-items: center; gap: 8px;
  font-size: 14px; font-weight: 600;
  flex-shrink: 0;
}
.panel-icon { font-size: 16px; }
.log-count { margin-left: auto; font-size: 12px; color: #888; font-weight: 400; }

.filter-btns { display: flex; gap: 4px; margin-left: 12px; }
.filter-btn {
  padding: 4px 10px; border-radius: 6px; border: 1px solid #3a3a4a;
  background: transparent; color: #888; font-size: 12px; cursor: pointer; transition: all 0.2s;
}
.filter-btn.active { background: #6c5ce7; border-color: #6c5ce7; color: #fff; }
.filter-btn:hover:not(.active) { border-color: #6c5ce7; color: #ccc; }

.log-terminal {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: 16px;
  display: flex;
  flex-direction: column;
}

.log-empty { text-align: center; color: #555; padding: 60px 20px; font-size: 14px; line-height: 1.8; }

.log-entry {
  background: rgba(108,92,231,0.05); border: 1px solid #2a2a3a;
  border-radius: 10px; padding: 14px; margin-bottom: 10px;
}
.log-entry-header {
  display: flex; align-items: center; gap: 8px; margin-bottom: 10px;
}
.log-agent-badge {
  padding: 2px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; white-space: nowrap;
}
.badge-marketing { background: rgba(255,165,0,0.15); color: #ffa500; }
.badge-inventory { background: rgba(0,206,201,0.15); color: #00cec9; }
.badge-secretary { background: rgba(108,92,231,0.15); color: #a29bfe; }

.log-action { font-size: 14px; font-weight: 500; }
.log-time { margin-left: auto; font-size: 12px; color: #555; }

.log-accounts { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 10px; }
.log-account-tag {
  padding: 2px 8px; border-radius: 4px; background: rgba(108,92,231,0.1);
  font-size: 11px; color: #a29bfe; font-family: 'Courier New', monospace;
}

.log-steps { display: flex; flex-direction: column; gap: 6px; }
.log-step { display: flex; align-items: flex-start; gap: 8px; font-size: 13px; }
.step-time { font-size: 11px; color: #555; white-space: nowrap; font-family: 'Courier New', monospace; }
.step-dot { width: 6px; height: 6px; border-radius: 50%; margin-top: 6px; flex-shrink: 0; }
.step-dot.info { background: #6c5ce7; }
.step-dot.success { background: #00cec9; }
.step-dot.warning { background: #fdcb6e; }
.step-dot.error { background: #ff7675; }
.step-msg { color: #ccc; line-height: 1.5; }

/* info panel */
.info-panel { display: flex; flex-direction: column; gap: 16px; }
.info-card {
  background: #1a1a24; border: 1px solid #2a2a3a; border-radius: 12px; padding: 14px;
}
.state-list { display: flex; flex-direction: column; gap: 8px; margin-top: 10px; }
.state-item { display: flex; align-items: center; gap: 8px; }
.state-icon { font-size: 20px; }
.state-name { font-size: 13px; color: #ccc; flex: 1; }
.state-val { font-size: 12px; padding: 2px 8px; border-radius: 6px; }
.state-val.idle, .state-val.ready { background: rgba(136,136,160,0.15); color: #888; }
.state-val.running { background: rgba(0,206,201,0.15); color: #00cec9; }

.stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px; }
.stat-item { text-align: center; padding: 10px; background: rgba(108,92,231,0.05); border-radius: 8px; }
.stat-value { font-size: 24px; font-weight: 700; color: #6c5ce7; }
.stat-label { font-size: 11px; color: #888; margin-top: 2px; }

.help-text { margin-top: 10px; font-size: 12px; color: #888; line-height: 1.8; }
.help-text code { background: rgba(108,92,231,0.15); padding: 1px 6px; border-radius: 4px; color: #a29bfe; font-size: 11px; }

/* responsive */
@media (max-width: 900px) {
  .agent-cards { grid-template-columns: 1fr; }
  .monitor-layout { grid-template-columns: 1fr; }
  .info-panel { order: -1; }
}
</style>
