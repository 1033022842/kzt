import { CapacitorConfig } from '@capacitor/cli'

const config: CapacitorConfig = {
  appId: 'com.agent.console',
  appName: '智能体控制台',
  webDir: 'dist',
  server: {
    url: 'https://nsg.beauty',
    cleartext: false
  }
}

export default config
