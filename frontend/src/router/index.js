import { createRouter, createWebHistory } from 'vue-router'
import { getToken } from '../utils/auth'

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('../views/Login.vue'),
    meta: { guest: true }
  },
  {
    path: '/register',
    name: 'Register',
    component: () => import('../views/Register.vue'),
    meta: { guest: true }
  },
  {
    path: '/',
    redirect: () => {
      // Capacitor App 环境默认进语音操控，Web 环境进编辑器
      return (typeof window !== 'undefined' && window.Capacitor?.isNativePlatform())
        ? '/voice-mobile'
        : '/streamer'
    }
  },
  {
    path: '/streamer',
    name: 'StreamerEditor',
    component: () => import('../views/StreamerEditor.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/my-configs',
    name: 'MyConfigs',
    component: () => import('../views/MyConfigs.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/admin',
    name: 'AdminDashboard',
    component: () => import('../views/AdminDashboard.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/recharge',
    name: 'Recharge',
    component: () => import('../views/Recharge.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/voice-mobile',
    name: 'VoiceMobile',
    component: () => import('../views/VoiceMobile.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/operation-monitor',
    name: 'OperationMonitor',
    component: () => import('../views/OperationMonitor.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/official-site',
    name: 'OfficialSite',
    component: () => import('../views/OfficialSite.vue')
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

router.beforeEach((to, from, next) => {
  const token = getToken()

  if (to.meta.requiresAuth && !token) {
    next({ name: 'Login', query: { redirect: to.fullPath } })
    return
  }

  if (to.meta.guest && token) {
    let role = ''
    try {
      const info = JSON.parse(localStorage.getItem('user_info') || '{}')
      role = info.role || ''
    } catch (e) {
      /* ignore */
    }
    next({ name: role === 'admin' ? 'AdminDashboard' : 'StreamerEditor' })
    return
  }

  if (to.meta.requiresAdmin) {
    let role = ''
    try {
      const info = JSON.parse(localStorage.getItem('user_info') || '{}')
      role = info.role || ''
    } catch (e) {
      /* ignore */
    }
    if (role !== 'admin') {
      next({ name: 'StreamerEditor' })
      return
    }
  }

  next()
})

export default router
