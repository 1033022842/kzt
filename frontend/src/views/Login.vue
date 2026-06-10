<script setup>
import { ref, reactive } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useUserStore } from '../stores/user'
import { ElMessage } from 'element-plus'

const router = useRouter()
const route = useRoute()
const userStore = useUserStore()

const loginFormRef = ref(null)
const loading = ref(false)

const loginForm = reactive({
  username: '',
  password: ''
})

const rules = {
  username: [{ required: true, message: '请输入用户名', trigger: 'blur' }],
  password: [{ required: true, message: '请输入密码', trigger: 'blur' }]
}

async function handleLogin() {
  if (!loginFormRef.value) return
  await loginFormRef.value.validate(async (valid) => {
    if (!valid) return
    loading.value = true
    try {
      const res = await userStore.login(loginForm.username, loginForm.password)
      const user = res.data?.user || res.user
      if (user) {
        localStorage.setItem('user_info', JSON.stringify(user))
        userStore.setUserInfo(user)
      }
      ElMessage.success('登录成功')
      const redirect = route.query.redirect || (user?.role === 'admin' ? '/admin' : '/')
      router.push(redirect)
    } catch (error) {
      // error handled in interceptor
    } finally {
      loading.value = false
    }
  })
}
</script>

<template>
  <div class="auth-page">
    <el-card class="auth-card" shadow="never">
      <template #header>
        <div style="text-align:center;">
          <div style="font-size:32px;margin-bottom:8px;">🎙️</div>
          <h2 style="margin:0;font-size:20px;font-weight:600;">智能体控制台</h2>
          <p style="margin:4px 0 0;font-size:13px;color:var(--text-secondary);">登录你的账号</p>
        </div>
      </template>
      <el-form
        ref="loginFormRef"
        :model="loginForm"
        :rules="rules"
        label-position="top"
        size="large"
        @keyup.enter="handleLogin"
      >
        <el-form-item label="用户名" prop="username">
          <el-input v-model="loginForm.username" placeholder="请输入用户名" />
        </el-form-item>
        <el-form-item label="密码" prop="password">
          <el-input
            v-model="loginForm.password"
            type="password"
            placeholder="请输入密码"
            show-password
          />
        </el-form-item>
        <el-form-item>
          <el-button
            type="primary"
            :loading="loading"
            style="width:100%;"
            @click="handleLogin"
          >
            登 录
          </el-button>
        </el-form-item>
      </el-form>
      <div style="text-align:center;font-size:13px;">
        <span style="color:var(--text-secondary);">没有账号？</span>
        <router-link to="/register" style="color:var(--accent);text-decoration:none;">
          去注册
        </router-link>
      </div>
    </el-card>
  </div>
</template>
