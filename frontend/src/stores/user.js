import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { login as loginApi, register as registerApi } from '../api/auth'
import { setToken, getToken, removeToken } from '../utils/auth'

export const useUserStore = defineStore('user', () => {
  const storedToken = getToken()
  const validToken = (storedToken && storedToken !== 'undefined' && storedToken !== 'null' && storedToken.length > 20)
    ? storedToken : ''
  if (!validToken && storedToken) {
    removeToken()
  }

  const token = ref(validToken)
  const userInfo = ref({ id: null, username: '', nickname: '', role: '' })

  const isLoggedIn = computed(() => !!token.value)

  const isAdmin = computed(() => userInfo.value.role === 'admin')

  function setUserInfo(info) {
    userInfo.value = {
      id: info.id || info.user_id || null,
      username: info.username || '',
      nickname: info.nickname || '',
      role: info.role || 'user'
    }
  }

  async function login(username, password) {
    const res = await loginApi(username, password)
    const t = res.data?.access_token || res.access_token
    if (!t || typeof t !== 'string' || t.length <= 20) {
      throw new Error('登录失败，未获取到有效令牌')
    }

    token.value = t
    setToken(t)

    if (res.data?.user || res.user) {
      const user = res.data?.user || res.user
      setUserInfo(user)
    }
    return res
  }

  async function register(data) {
    const res = await registerApi(data)
    return res
  }

  function fetchUserInfo() {
    const stored = localStorage.getItem('user_info')
    if (stored) {
      try {
        setUserInfo(JSON.parse(stored))
      } catch (e) {
        /* ignore */
      }
    }
  }

  function logout() {
    token.value = ''
    userInfo.value = { id: null, username: '', nickname: '', role: '' }
    removeToken()
    localStorage.removeItem('user_info')
  }

  return {
    token,
    userInfo,
    isLoggedIn,
    isAdmin,
    setUserInfo,
    login,
    register,
    fetchUserInfo,
    logout
  }
})
