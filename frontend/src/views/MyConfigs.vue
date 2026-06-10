<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { getMyConfigs, deleteConfig } from '../api/streamer'
import { ElMessageBox } from 'element-plus'
import { Delete, Edit, Plus } from '@element-plus/icons-vue'

const router = useRouter()
const configs = ref([])
const loading = ref(false)

async function fetchConfigs() {
  loading.value = true
  try {
    const res = await getMyConfigs()
    configs.value = res.data?.list || res.data || []
  } catch (error) {
    // handled in interceptor
  } finally {
    loading.value = false
  }
}

function goEditor(config) {
  if (config) {
    router.push({ path: '/', query: { load: config.id || config._id } })
  } else {
    router.push('/')
  }
}

async function handleDelete(config) {
  try {
    await ElMessageBox.confirm(`确认删除配置「${config.name}」？此操作不可恢复。`, '确认删除', {
      confirmButtonText: '删除',
      cancelButtonText: '取消',
      type: 'warning'
    })
    await deleteConfig(config.id || config._id)
    fetchConfigs()
  } catch (error) {
    if (error !== 'cancel') {
      // handled in interceptor
    }
  }
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
        <div class="logo-icon">🎙️</div>
        <div>
          <h1>我的数字人配置</h1>
          <div class="subtitle">管理已保存的主播设定</div>
        </div>
      </div>
      <div style="display:flex;gap:8px;">
        <el-button plain size="small" @click="router.push('/')">
          返回编辑器
        </el-button>
        <el-button type="primary" size="small" @click="goEditor()">
          <el-icon><Plus /></el-icon>
          新建配置
        </el-button>
      </div>
    </div>

    <div class="container">
      <el-card v-loading="loading">
        <el-table :data="configs" style="width:100%;">
          <el-table-column prop="name" label="配置名称" min-width="200" />
          <el-table-column label="创建时间" width="200">
            <template #default="{ row }">
              {{ formatTime(row.created_at || row.create_time) }}
            </template>
          </el-table-column>
          <el-table-column label="更新时间" width="200">
            <template #default="{ row }">
              {{ formatTime(row.updated_at || row.update_time) }}
            </template>
          </el-table-column>
          <el-table-column label="操作" width="180">
            <template #default="{ row }">
              <el-button type="primary" link size="small" @click="goEditor(row)">
                <el-icon><Edit /></el-icon>
                编辑
              </el-button>
              <el-button type="danger" link size="small" @click="handleDelete(row)">
                <el-icon><Delete /></el-icon>
                删除
              </el-button>
            </template>
          </el-table-column>
          <template #empty>
            <div style="padding:40px;text-align:center;color:var(--text-secondary);">
              <p style="font-size:32px;margin-bottom:12px;">📭</p>
              <p>还没有保存任何配置</p>
              <el-button type="primary" style="margin-top:12px;" @click="goEditor()">
                去创建第一个配置
              </el-button>
            </div>
          </template>
        </el-table>
      </el-card>
    </div>
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
  max-width: 1000px;
  margin: 0 auto;
  padding: 24px 32px;
}
</style>
