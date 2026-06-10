<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\common\ai\LlmService;
use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\model\StreamerConfig;

/**
 * 语音操控智能体控制器
 *  - POST /voice/command   : 接收语音/文字指令，调用 LLM 解析意图，生成模拟操作
 *  - GET  /voice/status    : 获取当前所有智能体的运行状态（轮询用）
 *  - GET  /voice/logs      : 获取操作日志
 *
 * 状态和日志存储在 runtime 目录下的 JSON 文件中，保证跨请求数据共享。
 */
final class VoiceAgentController extends BaseController
{
    protected bool $requireAuth = false;

    /**
     * 数据文件路径
     */
    private static function dataFile(): string
    {
        return app()->getRuntimePath() . 'voice_agent_data.json';
    }

    private static function defaultState(): array
    {
        return [
            'states' => [
                'marketing'  => ['status' => 'idle', 'label' => '空闲', 'accounts' => 0, 'action' => ''],
                'inventory'  => ['status' => 'idle', 'label' => '空闲', 'accounts' => 0, 'action' => ''],
                'secretary'  => ['status' => 'ready', 'label' => '就绪', 'accounts' => 0, 'action' => ''],
            ],
            'logs' => [],
        ];
    }

    /**
     * 从文件读取数据（加共享锁，不阻塞读）
     */
    private static function loadData(): array
    {
        $file = self::dataFile();
        if (!file_exists($file)) {
            return self::defaultState();
        }
        // 用共享锁读取，允许并发读
        $fp = fopen($file, 'r');
        if (!$fp) return self::defaultState();
        flock($fp, LOCK_SH);
        $raw = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        if ($raw === false || $raw === '') {
            return self::defaultState();
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return self::defaultState();
        }
        return array_merge(self::defaultState(), $data);
    }

    /**
     * 保存数据到文件（排他锁，防止并发写覆盖）
     */
    private static function saveData(array $data): void
    {
        $file = self::dataFile();
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $tmp = $file . '.tmp';
        $fp = fopen($tmp, 'c+');
        if (!$fp) return;
        flock($fp, LOCK_EX);
        ftruncate($fp, 0);
        fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        rename($tmp, $file);
    }

    /**
     * POST /api/v1/voice/command
     * 接收语音/文字指令，解析意图，生成模拟操作
     */
    public function command()
    {
        $message = $this->request->post('message', '');
        $message = trim($message);

        if (empty($message)) {
            return $this->fail(ResultCode::PARAM_ERROR, '指令内容不能为空');
        }

        // 1. 调用 LLM 解析意图
        $intent = null;
        $llmError = null;

        try {
            $llm = new LlmService();
            $intent = $llm->parseIntent($message);
        } catch (\Throwable $e) {
            $llmError = $e->getMessage();
            $intent = $this->keywordFallback($message);
        }

        $agent = $intent['agent'] ?? 'secretary';
        $action = $intent['action'] ?? $message;

        // 2. 生成模拟操作日志
        $opLog = $this->generateMockOperation($agent, $action);

        // 3. 加载并更新持久化数据
        $data = self::loadData();

        // 更新智能体状态
        $data['states'][$agent] = [
            'status'   => 'running',
            'label'    => '运行中',
            'accounts' => $data['states'][$agent]['accounts'] ?? 0,
            'action'   => $action,
            '_resetAt' => time() + 5,   // 5 秒后前台恢复空闲
        ];

        // 添加日志
        $data['logs'][] = $opLog;
        if (count($data['logs']) > 200) {
            $data['logs'] = array_slice($data['logs'], -200);
        }

        self::saveData($data);

        return $this->success([
            'intent'     => $intent,
            'agent'      => $agent,
            'action'     => $action,
            'llm_error'  => $llmError,
            'states'     => $data['states'],
        ]);
    }

    /**
     * GET /api/v1/voice/status
     * 获取当前智能体状态（轮询用）
     */
    public function status()
    {
        $data = self::loadData();
        $states = $data['states'];

        // 自动恢复过期状态
        foreach ($states as $key => &$s) {
            if ($s['status'] === 'running' && isset($s['_resetAt']) && time() > $s['_resetAt']) {
                $s['status'] = $key === 'secretary' ? 'ready' : 'idle';
                $s['label'] = $key === 'secretary' ? '就绪' : '空闲';
                $s['action'] = '';
                unset($s['_resetAt']);
            }
        }
        unset($s);

        // 查询用户的智能体数量（可选）
        try {
            $userId = $this->getAuthUserId();
            if ($userId > 0) {
                $marketingCount = StreamerConfig::where('user_id', $userId)
                    ->whereRaw("JSON_EXTRACT(config_data, '$.agentType') = 'marketing'")
                    ->count();
                $inventoryCount = StreamerConfig::where('user_id', $userId)
                    ->whereRaw("JSON_EXTRACT(config_data, '$.agentType') = 'inventory'")
                    ->count();
                $secretaryCount = StreamerConfig::where('user_id', $userId)
                    ->whereRaw("JSON_EXTRACT(config_data, '$.agentType') = 'secretary'")
                    ->count();
                $states['marketing']['accounts'] = $marketingCount;
                $states['inventory']['accounts'] = $inventoryCount;
                $states['secretary']['accounts'] = $secretaryCount;
            }
        } catch (\Throwable $e) {
            // 忽略
        }

        // 如果有自动恢复变更，保存
        if ($states !== $data['states']) {
            $data['states'] = $states;
            self::saveData($data);
        }

        return $this->success([
            'states' => $states,
            'logs'   => array_slice($data['logs'], -30),
        ]);
    }

    /**
     * GET /api/v1/voice/logs
     * 获取操作日志
     */
    public function logs()
    {
        $data = self::loadData();
        return $this->success([
            'logs' => array_slice($data['logs'], -50),
        ]);
    }

    /**
     * GET /api/v1/voice/xfyun-config
     * 返回讯飞语音识别配置（APPID/APIKey/APISecret）
     */
    public function xfyunConfig()
    {
        $appId = env('XFYUN_APP_ID', '');
        $apiKey = env('XFYUN_API_KEY', '');
        $apiSecret = env('XFYUN_API_SECRET', '');

        if (empty($appId) || empty($apiKey) || empty($apiSecret)) {
            return $this->fail(ResultCode::SYSTEM_ERROR, '讯飞语音识别未配置');
        }

        return $this->success([
            'app_id'     => $appId,
            'api_key'    => $apiKey,
            'api_secret' => $apiSecret,
        ]);
    }

    /**
     * POST /api/v1/voice/recognize
     * Deepgram 语音识别降级通道。接收 WebM/WAV 音频文件，返回文字。
     */
    public function recognize()
    {
        $file = $this->request->file('audio');
        if (!$file) {
            return $this->fail(ResultCode::PARAM_ERROR, '请上传音频文件');
        }

        $apiKey = env('DEEPGRAM_API_KEY', '');
        if (empty($apiKey)) {
            return $this->fail(ResultCode::SYSTEM_ERROR, 'Deepgram 未配置');
        }

        $tmpPath = $file->getPathname();
        $audioData = file_get_contents($tmpPath);

        if ($audioData === false || strlen($audioData) === 0) {
            return $this->fail(ResultCode::PARAM_ERROR, '音频文件为空');
        }

        $ch = curl_init('https://api.deepgram.com/v1/listen?model=nova-2&language=zh-CN&smart_format=true&detect_language=false');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $audioData,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Token ' . $apiKey,
                'Content-Type: audio/webm',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return $this->fail(ResultCode::SYSTEM_ERROR, '语音识别请求失败: ' . $error);
        }

        if ($httpCode !== 200) {
            $errBody = json_decode($raw, true);
            $errMsg = $errBody['error']['message'] ?? "HTTP {$httpCode}";
            return $this->fail(ResultCode::SYSTEM_ERROR, '语音识别失败: ' . $errMsg);
        }

        $data = json_decode($raw, true);
        $text = $data['results']['channels'][0]['alternatives'][0]['transcript'] ?? '';

        if (empty(trim($text))) {
            return $this->fail(ResultCode::PARAM_ERROR, '未识别到语音内容');
        }

        return $this->success([
            'text' => trim($text),
        ]);
    }

    // ─── 私有方法 ────────────────────────────────────────────

    /**
     * 当 LLM 不可用时，使用关键词回退
     */
    private function keywordFallback(string $message): array
    {
        $t = mb_strtolower($message);
        if (str_contains($t, '广告') || str_contains($t, '营销') || str_contains($t, '推广') || str_contains($t, '发')) {
            return ['agent' => 'marketing', 'action' => '开始批量发送广告', 'params' => ['platform' => 'whatsapp']];
        }
        if ((str_contains($t, '买') && (str_contains($t, '账号') || str_contains($t, '号'))) || str_contains($t, '库存') || str_contains($t, '分配') || str_contains($t, '进销存') || str_contains($t, '进货')) {
            return ['agent' => 'inventory', 'action' => '购买账号并自动分配', 'params' => []];
        }
        return ['agent' => 'secretary', 'action' => '查询智能体运行数据', 'params' => []];
    }

    /**
     * 生成模拟操作日志
     */
    private function generateMockOperation(string $agent, string $action): array
    {
        $now = time() * 1000;
        $id = uniqid('op_', true);

        $steps = [];
        $accounts = [];

        switch ($agent) {
            case 'marketing':
                $platforms = ['WhatsApp', 'Telegram', 'Facebook', 'Instagram'];
                $count = rand(3, 8);
                for ($i = 0; $i < $count; $i++) {
                    $platform = $platforms[array_rand($platforms)];
                    $accId = strtolower($platform) . '_ad_' . rand(1000, 9999);
                    $accounts[] = ['platform' => $platform, 'account' => $accId];
                    $steps[] = [
                        'time' => $now + $i * 2000,
                        'type' => $i === 0 ? 'info' : 'success',
                        'msg'  => $i === 0
                            ? "[系统] 已连接至 {$platform} 广告平台，准备群发"
                            : "[发送] ✅ 已向 {$accId} 发送广告消息，状态：已送达",
                    ];
                }
                $steps[] = [
                    'time' => $now + $count * 2000 + 1000,
                    'type' => 'success',
                    'msg'  => "[完成] 广告已群发至 {$count} 个账号，完成时间 " . date('H:i:s'),
                ];
                break;

            case 'inventory':
                $buyCount = rand(5, 15);
                $assignedCount = rand(2, min($buyCount, 8));
                $steps = [
                    ['time' => $now + 500, 'type' => 'info', 'msg' => '[系统] 已连接至账号批发平台'],
                    ['time' => $now + 1500, 'type' => 'info', 'msg' => '[检索] 正在扫描可购买账号列表...'],
                    ['time' => $now + 2500, 'type' => 'success', 'msg' => "[检索] 发现 {$buyCount} 个可用账号，总价 \$" . ($buyCount * rand(15, 45))],
                    ['time' => $now + 3500, 'type' => 'success', 'msg' => "[购买] ✅ 成功购入 {$buyCount} 个账号，已加入库存"],
                    ['time' => $now + 5000, 'type' => 'info', 'msg' => '[分配] 正在自动分配给营销智能体...'],
                    ['time' => $now + 6000, 'type' => 'success', 'msg' => "[分配] ✅ 已将 {$assignedCount} 个账号分配给营销智能体"],
                    ['time' => $now + 7000, 'type' => 'info', 'msg' => "[库存] 剩余 " . ($buyCount - $assignedCount) . ' 个账号待分配'],
                ];
                break;

            case 'secretary':
                $steps = [
                    ['time' => $now + 500, 'type' => 'info', 'msg' => '[秘书] 正在查询智能体运行数据...'],
                    ['time' => $now + 1500, 'type' => 'info', 'msg' => '[秘书] 营销智能体：2 个运行中，今日发送 127 条消息'],
                    ['time' => $now + 2500, 'type' => 'info', 'msg' => '[秘书] 进销存智能体：库存 45 个账号，今日购买 12 个，分配 8 个'],
                    ['time' => $now + 3500, 'type' => 'success', 'msg' => '[秘书] 结算数据：今日收入 \$1,230.50，已生成报表'],
                ];
                break;
        }

        return [
            'id'       => $id,
            'agent'    => $agent,
            'action'   => $action,
            'time'     => $now,
            'accounts' => $accounts,
            'steps'    => $steps,
        ];
    }
}
