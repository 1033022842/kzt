import request from '../utils/request'

export function getUsersConfigs(params) {
  return request.get('/admin/users/configs', { params })
}

export function toggleTrainingStatus(data) {
  return request.put('/admin/training-status', data)
}

export function getUsers() {
  return request.get('/admin/users')
}

export function toggleUserStatus(id) {
  return request.put(`/admin/users/${id}/status`)
}

export function kickUser(id) {
  return request.put(`/admin/users/${id}/kick`)
}

export function setUserQuota(id, agentQuota) {
  return request.put(`/admin/users/${id}/quota`, { agent_quota: agentQuota })
}

export function assignConfig(id, userId) {
  return request.put(`/admin/configs/${id}/assign`, { user_id: userId })
}

export function getAdminRecharges() {
  return request.get('/admin/recharges')
}

export function confirmRecharge(id) {
  return request.put(`/admin/recharges/${id}/confirm`)
}

export function rejectRecharge(id, remark) {
  return request.put(`/admin/recharges/${id}/reject`, { remark })
}

export function updateTrc20Config(data) {
  return request.put('/admin/trc20-config', data)
}
