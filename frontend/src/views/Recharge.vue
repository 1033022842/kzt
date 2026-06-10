<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getBalance, getTrc20Address, createRechargeOrder, getRechargeRecords, purchase, getPurchaseRecords } from '../api/payment'

const router = useRouter()

const balance = ref(0)
const agentQuota = ref(0)
const agentUsed = ref(0)
const agentAvailable = ref(0)
const trc20Address = ref('')
const minAmount = ref(50)

const rechargeAmount = ref('')
const recharging = ref(false)
const payWindow = ref(null)
const pollTimer = ref(null)

const rechargeRecords = ref([])
const purchaseRecords = ref([])

const packages = [
  { key: 'single',  label: '1个智能体', price: 799,  agents: 1, icon: '🤖' },
  { key: 'double',  label: '2个智能体', price: 1399, agents: 2, icon: '🤖🤖', popular: true },
  { key: 'triple',  label: '3个智能体', price: 1999, agents: 3, icon: '🤖🤖🤖' },
  { key: 'add_one', label: '加购1个',   price: 799,  agents: 1, icon: '➕' },
]

async function fetchData() {
  try {
    const [b, a, r, p] = await Promise.all([
      getBalance(),
      getTrc20Address(),
      getRechargeRecords(),
      getPurchaseRecords()
    ])
    balance.value = b.data?.balance ?? 0
    agentQuota.value = b.data?.agent_quota ?? 0
    agentUsed.value = b.data?.agent_used ?? 0
    agentAvailable.value = b.data?.agent_available ?? 0
    trc20Address.value = a.data?.address ?? ''
    minAmount.value = a.data?.min_amount ?? 50
    rechargeRecords.value = r.data?.list ?? []
    purchaseRecords.value = p.data?.list ?? []
  } catch {
    // handled by interceptor
  }
}

async function handleRecharge() {
  const amount = parseFloat(rechargeAmount.value)
  if (!amount || amount <= 0) {
    ElMessage.warning('请输入有效充值金额')
    return
  }
  if (amount < minAmount.value) {
    ElMessage.warning(`最低充值金额为 $${minAmount.value}`)
    return
  }
  recharging.value = true
  try {
    const res = await createRechargeOrder(amount)
    const payUrl = res.data?.pay_url
    if (!payUrl) {
      ElMessage.error('创建支付订单失败')
      return
    }
    // 新窗口打开 tronusdt 支付页面
    payWindow.value = window.open(payUrl, '_blank')
    ElMessage.success('已打开支付页面，请在弹出窗口中扫码支付')
    // 开始轮询充值状态
    startPolling()
  } catch {
    // handled by interceptor
  } finally {
    recharging.value = false
  }
}

function startPolling() {
  stopPolling()
  let count = 0
  pollTimer.value = setInterval(async () => {
    count++
    try {
      const res = await getBalance()
      const newBalance = res.data?.balance ?? 0
      if (newBalance > balance.value) {
        stopPolling()
        ElMessage.success('充值到账！')
        await fetchData()
        rechargeAmount.value = ''
      }
    } catch {
      // ignore
    }
    if (count >= 60) stopPolling() // 最多轮询5分钟
  }, 5000)
}

function stopPolling() {
  if (pollTimer.value) {
    clearInterval(pollTimer.value)
    pollTimer.value = null
  }
}

function copyAddress() {
  if (!trc20Address.value) return
  navigator.clipboard.writeText(trc20Address.value).then(() => {
    ElMessage.success('地址已复制')
  })
}

async function handlePurchase(pkg) {
  if (balance.value < pkg.price) {
    ElMessage.warning(`余额不足，需要 $${pkg.price}，当前余额 $${balance.value}`)
    return
  }
  try {
    await ElMessageBox.confirm(
      `确认购买「${pkg.label}」套餐？将支付 $${pkg.price}，获得 ${pkg.agents} 个智能体。`,
      '确认购买',
      { confirmButtonText: '确认支付', cancelButtonText: '取消', type: 'warning' }
    )
  } catch {
    return
  }
  try {
    await purchase({ package_type: pkg.key })
    ElMessage.success(`购买成功！获得 ${pkg.agents} 个智能体`)
    await fetchData()
  } catch {
    // handled by interceptor
  }
}

function statusLabel(status) {
  if (status === 'pending') return '待审核'
  if (status === 'confirmed') return '已到账'
  if (status === 'rejected') return '已驳回'
  return status
}

function statusType(status) {
  if (status === 'pending') return 'warning'
  if (status === 'confirmed') return 'success'
  if (status === 'rejected') return 'danger'
  return 'info'
}

onMounted(() => { fetchData() })
</script>

<template>
  <div class="recharge-page">
    <div class="container">
      <div class="page-top">
        <el-button text @click="router.push('/')" style="margin-bottom:8px;">← 返回主面板</el-button>
        <h2>💰 充值中心</h2>
      </div>

      <div class="stats-row">
        <div class="stat-card">
          <div class="stat-value">${{ balance }}</div>
          <div class="stat-label">账户余额</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">{{ agentUsed }} / {{ agentQuota }}</div>
          <div class="stat-label">智能体使用量</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">{{ agentAvailable }}</div>
          <div class="stat-label">可用配额</div>
        </div>
      </div>

      <el-card style="margin-bottom:20px;">
        <template #header>
          <span style="font-weight:600;">📦 智能体套餐</span>
        </template>
        <div class="package-row">
          <div
            v-for="pkg in packages"
            :key="pkg.key"
            class="package-card"
            :class="{ popular: pkg.popular }"
            @click="handlePurchase(pkg)"
          >
            <div class="package-icon">{{ pkg.icon }}</div>
            <div class="package-name">{{ pkg.label }}</div>
            <div class="package-price">${{ pkg.price }}</div>
            <div v-if="pkg.popular" class="package-badge">🔥 热门</div>
          </div>
        </div>
      </el-card>

      <div class="recharge-layout">
        <el-card style="flex:1;">
          <template #header>
            <span style="font-weight:600;">🏦 TRC20-USDT 充值</span>
          </template>
          <div class="trc20-section">
            <div class="trc20-label">平台收款地址</div>
            <div class="trc20-address-box" @click="copyAddress">
              <code>{{ trc20Address || '请等待管理员设置收款地址' }}</code>
              <el-button v-if="trc20Address" size="small" text type="primary">📋 复制</el-button>
            </div>
            <div style="font-size:12px;color:var(--text-secondary);margin:8px 0 16px;">
              最低充值 ${{ minAmount }} · 仅支持 TRC20-USDT · 支付后自动到账
            </div>

            <el-form-item label="充值金额(USD)">
              <el-input-number v-model="rechargeAmount" :min="minAmount" :precision="0" style="width:100%;" placeholder="输入充值金额" />
            </el-form-item>
            <el-button type="primary" :loading="recharging" style="width:100%;" @click="handleRecharge">
              🚀 生成支付二维码
            </el-button>
            <div v-if="pollTimer" style="text-align:center;margin-top:12px;color:var(--accent);font-size:13px;">
              ⏳ 等待支付到账中... 支付完成后自动确认
            </div>
          </div>
        </el-card>

        <el-card style="flex:1;">
          <template #header>
            <span style="font-weight:600;">📋 充值记录</span>
          </template>
          <div v-if="!rechargeRecords.length" style="text-align:center;padding:30px;color:var(--text-secondary);">暂无充值记录</div>
          <div v-for="r in rechargeRecords" :key="r.id" class="record-item">
            <div>
              <span style="font-weight:600;">${{ r.amount }}</span>
              <span style="font-size:12px;color:var(--text-secondary);margin-left:8px;">{{ r.create_time || r.createTime }}</span>
            </div>
            <div>
              <el-tag :type="statusType(r.status)" size="small">{{ statusLabel(r.status) }}</el-tag>
              <span v-if="r.remark" style="font-size:11px;color:var(--text-secondary);margin-left:8px;">{{ r.remark }}</span>
            </div>
          </div>
        </el-card>
      </div>

      <el-card style="margin-top:20px;" v-if="purchaseRecords.length">
        <template #header>
          <span style="font-weight:600;">🛒 购买记录</span>
        </template>
        <div v-for="r in purchaseRecords" :key="r.id" class="record-item">
          <span style="font-weight:600;">{{ r.package_name }}</span>
          <span style="font-size:12px;color:var(--text-secondary);">+{{ r.agent_count }} 智能体 · ${{ r.amount }} · {{ r.create_time || r.createTime }}</span>
        </div>
      </el-card>
    </div>
  </div>
</template>

<style scoped>
.recharge-page {
  min-height: 100vh;
  background: var(--bg);
}

.container {
  max-width: 900px;
  margin: 0 auto;
  padding: 24px 32px;
}

.page-top h2 {
  margin: 0 0 16px;
  font-size: 22px;
}

.stats-row {
  display: flex;
  gap: 12px;
  margin-bottom: 20px;
}

.stat-card {
  flex: 1;
  background: var(--bg-card);
  border: 1px solid var(--card-border);
  border-radius: 8px;
  padding: 14px 18px;
  text-align: center;
}

.stat-value {
  font-size: 22px;
  font-weight: 700;
  color: var(--accent);
}

.stat-label {
  font-size: 12px;
  color: var(--text-secondary);
  margin-top: 4px;
}

.package-row {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.package-card {
  flex: 1;
  min-width: 140px;
  background: var(--bg-card);
  border: 1px solid var(--card-border);
  border-radius: 8px;
  padding: 16px;
  text-align: center;
  cursor: pointer;
  transition: all 0.2s;
  position: relative;
}

.package-card:hover {
  border-color: var(--accent);
  transform: translateY(-2px);
}

.package-card.popular {
  border-color: var(--accent);
  background: rgba(108, 92, 231, 0.06);
}

.package-icon {
  font-size: 24px;
  margin-bottom: 6px;
}

.package-name {
  font-size: 13px;
  font-weight: 600;
}

.package-price {
  font-size: 18px;
  font-weight: 700;
  color: var(--accent);
  margin-top: 4px;
}

.package-badge {
  position: absolute;
  top: -8px;
  right: -8px;
  background: var(--accent);
  color: #fff;
  font-size: 10px;
  padding: 2px 8px;
  border-radius: 10px;
}

.recharge-layout {
  display: flex;
  gap: 16px;
}

.trc20-section {
  padding: 4px 0;
}

.trc20-address-box {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #0a0a12;
  border: 1px solid var(--card-border);
  border-radius: 6px;
  padding: 10px 14px;
  cursor: pointer;
  word-break: break-all;
}

.trc20-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--text-secondary);
  margin-bottom: 6px;
}

.record-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 0;
  border-bottom: 1px solid var(--card-border);
  font-size: 13px;
}

.record-item:last-child {
  border-bottom: none;
}

@media (max-width: 700px) {
  .recharge-layout {
    flex-direction: column;
  }
  .package-card {
    min-width: 100%;
  }
}
</style>
