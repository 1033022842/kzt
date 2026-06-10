import axios from 'axios'
import { getToken, removeToken } from './auth'
import { ElMessage } from 'element-plus'

const SUCCESS_CODE = '00000'

const request = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api/v1',
  timeout: 15000
})

request.interceptors.request.use(
  (config) => {
    const token = getToken()
    if (token && token !== 'undefined' && token !== 'null') {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

request.interceptors.response.use(
  (response) => {
    const result = response.data
    if (result?.code && result.code !== SUCCESS_CODE) {
      if (result.code === 'A0002') {
        removeToken()
        ElMessage.error(result.msg || '登录已过期，请重新登录')
        window.location.href = '/#/login'
      } else {
        ElMessage.error(result.msg || '请求失败')
      }
      return Promise.reject(new Error(result.msg || '请求失败'))
    }
    return result
  },
  (error) => {
    if (error.response) {
      const { status, data } = error.response
      if (status === 401) {
        removeToken()
        ElMessage.error('登录已过期，请重新登录')
        window.location.href = '/#/login'
      } else {
        ElMessage.error(data?.msg || data?.message || '请求失败')
      }
    } else {
      const detail = error.message || String(error)
      console.error('[Request] 网络错误:', detail, error)
      ElMessage.error('网络错误: ' + detail.substring(0, 50))
    }
    return Promise.reject(error)
  }
)

export default request
