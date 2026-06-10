import { CapacitorConfig } from '@capacitor/cli'

const config: CapacitorConfig = {
  appId: 'com.agent.console',
  appName: '智能体控制台',
  webDir: 'dist',
  server: {
    // 开发时用 Vite dev server，打包时注释掉这行
    // url: 'http://192.168.1.x:5173',
    // cleartext: true
  }
}

export default config
