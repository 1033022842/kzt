import request from '../utils/request'

export function getMyConfigs(params) {
  return request.get('/streamer/configs', { params })
}

export function getConfigDetail(id) {
  return request.get(`/streamer/configs/${id}`)
}

export function createConfig(data) {
  return request.post('/streamer/configs', data)
}

export function updateConfig(data) {
  return request.put('/streamer/configs', data)
}

export function uploadAvatar(data) {
  return request.post('/streamer/upload-avatar', data)
}

export function deleteConfig(id) {
  return request.delete(`/streamer/configs/${id}`)
}
