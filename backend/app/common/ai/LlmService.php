<?php

declare(strict_types=1);

namespace app\common\ai;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;

/**
 * LLM 服务 — 支持 DeepSeek / OpenAI / 本地模型，通过 .env 切换
 *
 * .env 配置示例：
 *   LLM_PROVIDER=deepseek
 *   LLM_API_KEY=sk-xxx
 *   LLM_BASE_URL=https://api.deepseek.com/v1
 *   LLM_MODEL=deepseek-chat
 *
 * 切换为 OpenAI：
 *   LLM_PROVIDER=openai
 *   LLM_API_KEY=sk-xxx
 *   LLM_BASE_URL=https://api.openai.com/v1
 *   LLM_MODEL=gpt-4o-mini
 *
 * 切换为本地模型（Ollama 等）：
 *   LLM_PROVIDER=local
 *   LLM_BASE_URL=http://localhost:11434/v1
 *   LLM_MODEL=qwen2.5:7b
 */
final class LlmService
{
    private string $provider;
    private string $apiKey;
    private string $baseUrl;
    private string $model;
    private int $timeout;

    public function __construct()
    {
        $this->provider = env('LLM_PROVIDER', 'deepseek');
        $this->apiKey   = env('LLM_API_KEY', '');
        $this->baseUrl  = env('LLM_BASE_URL', 'https://api.deepseek.com/v1');
        $this->model    = env('LLM_MODEL', 'deepseek-chat');
        $this->timeout  = (int)env('LLM_TIMEOUT', 30);
    }

    /**
     * 获取当前使用的模型信息
     */
    public function getInfo(): array
    {
        return [
            'provider' => $this->provider,
            'model'    => $this->model,
            'base_url' => $this->baseUrl,
            'has_key'  => !empty($this->apiKey),
        ];
    }

    /**
     * 意图解析：将用户自然语言指令解析为结构化意图
     *
     * @return array { agent, action, params }
     */
    public function parseIntent(string $userMessage): array
    {
        $systemPrompt = <<<PROMPT
你是一个智能体调度系统。用户的语音/文本指令会被发送给你，你需要解析用户的意图，返回 JSON。

可调度的智能体类型及其能力：
1. marketing (营销智能体) — 负责发送广告、推广、在 WhatsApp/Telegram/Facebook 等平台群发消息
2. inventory (进销存智能体) — 负责购买账号、管理库存、分配账号给营销智能体
3. secretary (秘书智能体) — 负责查询数据、生成报表、汇报智能体运行状态、结算数据、库存情况

返回格式（纯 JSON，不要 markdown 代码块）：
{
  "agent": "marketing|inventory|secretary",
  "action": "简短描述要执行的操作",
  "params": {}
}

示例：
- 用户说"开始发广告" → {"agent":"marketing","action":"开始批量发送广告","params":{"platform":"whatsapp"}}
- 用户说"买点账号分配给营销" → {"agent":"inventory","action":"购买账号并自动分配给营销智能体","params":{"count":10}}
- 用户说"今天的结算数据怎么样" → {"agent":"secretary","action":"查询今日结算数据并生成报告","params":{"scope":"daily"}}
- 用户说"智能体运行状态" → {"agent":"secretary","action":"查询所有智能体运行状态","params":{}}
PROMPT;

        return $this->chatJson($systemPrompt, $userMessage);
    }

    /**
     * 秘书智能体对话：根据上下文数据回答问题
     */
    public function secretaryChat(string $contextData, string $userMessage): string
    {
        $systemPrompt = <<<PROMPT
你是一个 AI 数据秘书。根据提供的智能体运行数据，回答用户的问题。回答要自然、专业、简洁。

可用数据：
{$contextData}

请用中文回答，给出具体数字。如果数据不足以回答问题，诚实说明。
PROMPT;

        return $this->chat($systemPrompt, $userMessage);
    }

    // ─── 内部方法 ────────────────────────────────────────────

    private function chatJson(string $systemPrompt, string $userMessage): array
    {
        $response = $this->chat($systemPrompt, $userMessage);
        return $this->extractJson($response);
    }

    private function chat(string $systemPrompt, string $userMessage): string
    {
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userMessage],
        ];

        $body = [
            'model'       => $this->model,
            'messages'    => $messages,
            'temperature' => 0.1,
            'max_tokens'  => 500,
        ];

        $url = rtrim($this->baseUrl, '/') . '/chat/completions';

        $headers = [
            'Content-Type: application/json',
        ];
        if (!empty($this->apiKey)) {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new BusinessException(ResultCode::SYSTEM_ERROR, 'LLM 请求失败: ' . $error);
        }

        if ($httpCode !== 200) {
            $errBody = json_decode($raw, true);
            $errMsg = $errBody['error']['message'] ?? "HTTP {$httpCode}";
            throw new BusinessException(ResultCode::SYSTEM_ERROR, 'LLM 返回错误: ' . $errMsg);
        }

        $data = json_decode($raw, true);
        return trim($data['choices'][0]['message']['content'] ?? '');
    }

    private function extractJson(string $text): array
    {
        // 尝试直接解析
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // 尝试从 markdown 代码块中提取
        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?```/s', $text, $m)) {
            $decoded = json_decode($m[1], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // 尝试提取第一个 JSON 对象
        if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $text, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return ['agent' => 'secretary', 'action' => $text, 'params' => []];
    }
}
