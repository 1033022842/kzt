<template>
  <div class="site">
    <!-- Nav -->
    <nav class="navbar">
      <div class="nav-inner">
        <div class="logo">🤖 AI Agent Hub</div>
        <div class="nav-links">
          <a href="#">首页</a>
          <a href="#features">功能</a>
          <a href="#demo">演示</a>
          <a href="#pricing">定价</a>
          <a href="#contact">联系</a>
        </div>
        <div class="nav-actions">
          <router-link to="/login" class="btn-outline">登录</router-link>
          <router-link to="/register" class="btn-primary">免费试用</router-link>
        </div>
      </div>
    </nav>

    <!-- Hero -->
    <section class="hero">
      <div class="hero-bg"></div>
      <div class="hero-content">
        <h1>一句话，操控你的<span class="highlight"> AI 团队</span></h1>
        <p class="hero-desc">
          用语音命令你的营销、进销存、秘书智能体。<br>
          一句话开始发广告、一句话购买分配账号、一句话查询数据报表。
        </p>
        <div class="hero-btns">
          <router-link to="/register" class="btn-primary-lg">立即开始 →</router-link>
          <a href="#demo" class="btn-outline-lg">观看演示</a>
        </div>
        <div class="hero-stats">
          <div class="stat"><span class="stat-num">3</span> 种智能体</div>
          <div class="stat"><span class="stat-num">语音</span> 操控</div>
          <div class="stat"><span class="stat-num">24/7</span> 自动运行</div>
        </div>
      </div>
    </section>

    <!-- Features -->
    <section id="features" class="features">
      <div class="section-header">
        <h2>三大核心功能</h2>
        <p>每一个智能体都可以用语音驱动</p>
      </div>
      <div class="feature-grid">
        <div class="feature-card">
          <div class="feature-icon">🤖</div>
          <h3>营销智能体</h3>
          <p>语音说"开始发广告"，自动在 WhatsApp、Telegram、Facebook 等多平台批量群发消息，模拟真实运营操作。</p>
          <div class="feature-tags">
            <span>多平台群发</span><span>自动运营</span><span>语音触发</span>
          </div>
        </div>
        <div class="feature-card">
          <div class="feature-icon">📦</div>
          <h3>进销存智能体</h3>
          <p>语音说"买点账号分配给营销"，自动完成账号购买入库并自动分配给营销智能体，全流程可视化。</p>
          <div class="feature-tags">
            <span>自动采购</span><span>智能分配</span><span>库存管理</span>
          </div>
        </div>
        <div class="feature-card">
          <div class="feature-icon">💬</div>
          <h3>秘书智能体</h3>
          <p>语音问"今天的结算数据"，即时回复所有智能体的运行状态、营收报表、库存情况，数据一目了然。</p>
          <div class="feature-tags">
            <span>数据查询</span><span>智能报表</span><span>语音交互</span>
          </div>
        </div>
      </div>
    </section>

    <!-- Demo Videos -->
    <section id="demo" class="demo-section">
      <div class="section-header">
        <h2>功能演示</h2>
        <p>看看语音操控的实际效果</p>
      </div>
      <div class="demo-grid">
        <div class="demo-card">
          <div class="video-placeholder">
            <div class="video-inner">
              <div class="play-icon">▶</div>
              <p>演示视频即将上线</p>
              <span>营销智能体 · 语音发广告</span>
            </div>
          </div>
          <h4>语音操控营销智能体</h4>
          <p>对 App 说"开始发广告"，浏览器端实时展示自动群发过程</p>
        </div>
        <div class="demo-card">
          <div class="video-placeholder">
            <div class="video-inner">
              <div class="play-icon">▶</div>
              <p>演示视频即将上线</p>
              <span>进销存智能体 · 购买分配账号</span>
            </div>
          </div>
          <h4>语音操控进销存智能体</h4>
          <p>语音指令购买账号并分配给营销智能体，全自动执行</p>
        </div>
        <div class="demo-card">
          <div class="video-placeholder">
            <div class="video-inner">
              <div class="play-icon">▶</div>
              <p>演示视频即将上线</p>
              <span>秘书智能体 · 智能数据问答</span>
            </div>
          </div>
          <h4>语音操控秘书智能体</h4>
          <p>用自然语言查询数据，AI 秘书智能回复各项运行数据</p>
        </div>
      </div>
    </section>

    <!-- Pricing -->
    <section id="pricing" class="pricing">
      <div class="section-header">
        <h2>定价方案</h2>
        <p>选择适合你的智能体套餐</p>
      </div>
      <div class="pricing-grid">
        <div class="pricing-card">
          <div class="pricing-header">
            <h3>基础版</h3>
            <div class="price">$799</div>
            <span class="price-desc">1 个智能体</span>
          </div>
          <ul>
            <li>1 个智能体创建配额</li>
            <li>语音操控支持</li>
            <li>操作监控面板</li>
            <li>基础数据报表</li>
          </ul>
          <router-link to="/register" class="btn-pricing">开始使用</router-link>
        </div>
        <div class="pricing-card featured">
          <div class="pricing-badge">推荐</div>
          <div class="pricing-header">
            <h3>进阶版</h3>
            <div class="price">$1,399</div>
            <span class="price-desc">2 个智能体</span>
          </div>
          <ul>
            <li>2 个智能体创建配额</li>
            <li>语音操控支持</li>
            <li>操作监控面板</li>
            <li>完整数据报表</li>
            <li>智能体间自动协作</li>
          </ul>
          <router-link to="/register" class="btn-pricing-primary">开始使用</router-link>
        </div>
        <div class="pricing-card">
          <div class="pricing-header">
            <h3>旗舰版</h3>
            <div class="price">$1,999</div>
            <span class="price-desc">3 个智能体</span>
          </div>
          <ul>
            <li>3 个智能体创建配额</li>
            <li>语音操控支持</li>
            <li>操作监控面板</li>
            <li>完整数据报表</li>
            <li>智能体间自动协作</li>
            <li>优先客服支持</li>
          </ul>
          <router-link to="/register" class="btn-pricing">开始使用</router-link>
        </div>
      </div>
    </section>

    <!-- Contact -->
    <section id="contact" class="contact">
      <div class="section-header">
        <h2>联系我们</h2>
        <p>有任何问题欢迎联系</p>
      </div>
      <div class="contact-content">
        <p>📧 support@aiagent-hub.com</p>
        <p>💬 在线客服：工作时间内即时回复</p>
      </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
      <p>© 2025 AI Agent Hub. All rights reserved.</p>
    </footer>
  </div>
</template>

<script setup>
</script>

<style scoped>
.site { font-family: -apple-system, BlinkMacSystemFont, 'PingFang SC', sans-serif; color: #e8e8f0; background: #0f0f14; }
a { text-decoration: none; }

.navbar {
  position: sticky; top: 0; z-index: 100; background: rgba(15,15,20,0.95);
  backdrop-filter: blur(10px); border-bottom: 1px solid #2a2a3a;
}
.nav-inner {
  max-width: 1200px; margin: 0 auto; padding: 14px 24px;
  display: flex; align-items: center; gap: 32px;
}
.logo { font-size: 18px; font-weight: 700; white-space: nowrap; }
.nav-links { display: flex; gap: 24px; flex: 1; }
.nav-links a { color: #888; font-size: 14px; transition: color 0.2s; }
.nav-links a:hover { color: #e8e8f0; }
.nav-actions { display: flex; gap: 12px; white-space: nowrap; }

.btn-outline {
  padding: 8px 20px; border: 1px solid #3a3a4a; border-radius: 8px;
  color: #e8e8f0; font-size: 14px; transition: all 0.2s;
}
.btn-outline:hover { border-color: #6c5ce7; }
.btn-primary {
  padding: 8px 20px; background: #6c5ce7; border-radius: 8px;
  color: #fff; font-size: 14px; transition: all 0.2s;
}
.btn-primary:hover { background: #5a4bd1; }

/* Hero */
.hero { position: relative; padding: 100px 24px 80px; text-align: center; overflow: hidden; }
.hero-bg {
  position: absolute; inset: 0; background: radial-gradient(ellipse at 50% 0%, rgba(108,92,231,0.15), transparent 70%);
}
.hero-content { position: relative; max-width: 700px; margin: 0 auto; }
.hero h1 { font-size: 48px; font-weight: 800; line-height: 1.2; margin-bottom: 20px; }
.highlight { background: linear-gradient(135deg, #6c5ce7, #a29bfe); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
.hero-desc { font-size: 18px; color: #888; line-height: 1.8; margin-bottom: 32px; }
.hero-btns { display: flex; gap: 16px; justify-content: center; margin-bottom: 48px; }
.btn-primary-lg {
  padding: 14px 36px; background: #6c5ce7; border-radius: 12px;
  color: #fff; font-size: 16px; font-weight: 600; transition: all 0.2s;
}
.btn-primary-lg:hover { background: #5a4bd1; transform: translateY(-1px); }
.btn-outline-lg {
  padding: 14px 36px; border: 1px solid #3a3a4a; border-radius: 12px;
  color: #e8e8f0; font-size: 16px; transition: all 0.2s;
}
.btn-outline-lg:hover { border-color: #6c5ce7; }

.hero-stats { display: flex; justify-content: center; gap: 40px; }
.stat { font-size: 14px; color: #888; }
.stat-num { display: block; font-size: 28px; font-weight: 700; color: #6c5ce7; margin-bottom: 4px; }

/* Section */
.section-header { text-align: center; margin-bottom: 48px; }
.section-header h2 { font-size: 32px; font-weight: 700; margin-bottom: 12px; }
.section-header p { font-size: 16px; color: #888; }
.features, .demo-section, .pricing, .contact { padding: 80px 24px; max-width: 1200px; margin: 0 auto; }

/* Features */
.feature-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }
.feature-card {
  background: #1a1a24; border: 1px solid #2a2a3a; border-radius: 16px;
  padding: 32px 24px; transition: all 0.3s;
}
.feature-card:hover { border-color: #6c5ce7; transform: translateY(-4px); }
.feature-icon { font-size: 48px; margin-bottom: 16px; }
.feature-card h3 { font-size: 20px; font-weight: 600; margin-bottom: 12px; }
.feature-card p { font-size: 14px; color: #888; line-height: 1.7; margin-bottom: 16px; }
.feature-tags { display: flex; flex-wrap: wrap; gap: 6px; }
.feature-tags span {
  padding: 3px 10px; border-radius: 6px; background: rgba(108,92,231,0.1);
  color: #a29bfe; font-size: 12px;
}

/* Demo */
.demo-section { background: rgba(108,92,231,0.02); max-width: none; }
.demo-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; max-width: 1200px; margin: 0 auto; }
.demo-card { text-align: center; }
.demo-card h4 { margin: 16px 0 8px; font-size: 16px; }
.demo-card p { font-size: 13px; color: #888; }

.video-placeholder {
  background: #1a1a24; border: 2px dashed #2a2a3a; border-radius: 16px;
  aspect-ratio: 16/9; display: flex; align-items: center; justify-content: center;
}
.video-inner { text-align: center; }
.play-icon {
  width: 56px; height: 56px; border-radius: 50%; background: rgba(108,92,231,0.2);
  display: flex; align-items: center; justify-content: center; margin: 0 auto 12px;
  font-size: 20px; color: #6c5ce7;
}
.video-inner p { font-size: 14px; color: #555; margin-bottom: 4px; }
.video-inner span { font-size: 12px; color: #444; }

/* Pricing */
.pricing-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; align-items: start; }
.pricing-card {
  background: #1a1a24; border: 1px solid #2a2a3a; border-radius: 16px;
  padding: 32px 24px; position: relative; transition: all 0.3s;
}
.pricing-card.featured { border-color: #6c5ce7; background: rgba(108,92,231,0.05); }
.pricing-badge {
  position: absolute; top: -12px; left: 50%; transform: translateX(-50%);
  padding: 4px 16px; background: #6c5ce7; border-radius: 20px; color: #fff; font-size: 12px; font-weight: 600;
}
.pricing-header { text-align: center; margin-bottom: 24px; }
.pricing-header h3 { font-size: 18px; margin-bottom: 12px; }
.price { font-size: 40px; font-weight: 800; color: #6c5ce7; }
.price-desc { font-size: 13px; color: #888; }
.pricing-card ul { list-style: none; padding: 0; margin-bottom: 24px; }
.pricing-card li { padding: 8px 0; font-size: 14px; color: #ccc; border-bottom: 1px solid #1f1f2a; }
.pricing-card li::before { content: '✓ '; color: #00cec9; }
.btn-pricing {
  display: block; text-align: center; padding: 12px; border: 1px solid #3a3a4a;
  border-radius: 10px; color: #e8e8f0; font-size: 15px; font-weight: 500; transition: all 0.2s;
}
.btn-pricing:hover { border-color: #6c5ce7; background: rgba(108,92,231,0.05); }
.btn-pricing-primary {
  display: block; text-align: center; padding: 12px; background: #6c5ce7;
  border-radius: 10px; color: #fff; font-size: 15px; font-weight: 500; transition: all 0.2s;
}
.btn-pricing-primary:hover { background: #5a4bd1; }

/* Contact */
.contact { border-top: 1px solid #2a2a3a; }
.contact-content { text-align: center; color: #888; font-size: 14px; line-height: 2.5; }

/* Footer */
.footer { text-align: center; padding: 24px; border-top: 1px solid #2a2a3a; color: #555; font-size: 13px; }

/* responsive */
@media (max-width: 768px) {
  .nav-links { display: none; }
  .hero h1 { font-size: 30px; }
  .feature-grid, .demo-grid, .pricing-grid { grid-template-columns: 1fr; }
  .hero-stats { gap: 20px; }
}
</style>
