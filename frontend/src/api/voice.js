import request from '../utils/request'

export function sendCommand(message) {
  return request.post('/voice/command', { message })
}

export function getVoiceStatus() {
  return request.get('/voice/status')
}

export function getVoiceLogs() {
  return request.get('/voice/logs')
}

export function getXfyunConfig() {
  return request.get('/voice/xfyun-config')
}

// Deepgram 降级识别：上传音频文件
export function recognizeAudio(audioBlob) {
  const formData = new FormData()
  formData.append('audio', audioBlob, 'recording.webm')
  return request.post('/voice/recognize', formData, {
    headers: { 'Content-Type': 'multipart/form-data' }
  })
}
