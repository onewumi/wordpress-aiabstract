<?php

/**
 * AIAbstract API 类
 *
 * 仅支持自定义 API（兼容 OpenAI 协议），已移除"我的日志"iLogs 接口与原作者统计上报。
 */

class AIAbstract_API {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // 初始化
    }

    /**
     * 归一化 OpenAI 兼容 API 地址
     *
     * 用户可能填写 base 地址（如 https://opencode.ai/zen/go/v1），
     * 对话接口必须打到 /chat/completions 子路径，这里自动补全。
     */
    public static function normalize_chat_url($url) {
        $url = trim((string)$url);
        if ($url === '') {
            return '';
        }
        // 已是 chat/completions 结尾（允许带尾斜杠）直接返回
        if (preg_match('#/chat/completions/?$#i', $url)) {
            return $url;
        }
        // 其余一律视为 base 地址，补 /chat/completions
        return rtrim($url, '/') . '/chat/completions';
    }

    /**
     * 测试API连接（仅自定义 API）
     */
    public function test_api_connection($api_type = 'custom', $credentials = []) {
        return $this->test_custom_api_connection($credentials);
    }

    /**
     * 测试自定义 OpenAI 兼容 API 连接
     */
    private function test_custom_api_connection($credentials) {
        $api_url = self::normalize_chat_url($credentials['api_url'] ?? '');
        $api_key = $credentials['api_key'] ?? '';
        $model = $credentials['model'] ?? '';

        if (empty($api_url) || empty($api_key)) {
            return [
                'success' => false,
                'message' => __('API地址和API Key不能为空', 'aiabstract')
            ];
        }
        if (empty($model)) {
            $model = 'deepseek-chat';
        }

        $request_data = [
            'model' => $model,
            'messages' => [[
                'role' => 'user',
                'content' => '请回复"连接成功"来测试API连接。'
            ]],
            'max_tokens' => 10
        ];

        $response = wp_remote_post($api_url, [
            'body' => json_encode($request_data, JSON_UNESCAPED_UNICODE),
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ],
            'timeout' => 15
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => sprintf(__('连接失败: %s', 'aiabstract'), $response->get_error_message())
            ];
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($http_code !== 200) {
            $error_info = '';
            if ($http_code === 401) {
                $error_info = __(' - API Key 无效', 'aiabstract');
            } elseif ($http_code === 429) {
                $error_info = __(' - 请求频率超限', 'aiabstract');
            }
            return [
                'success' => false,
                'message' => sprintf(__('HTTP错误: %s%s', 'aiabstract'), $http_code, $error_info)
            ];
        }

        $result = @json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'message' => __('响应解析失败', 'aiabstract')
            ];
        }

        // OpenAI 兼容返回格式检查
        if (isset($result['choices'][0]['message']['content'])) {
            return [
                'success' => true,
                'message' => sprintf(__('API 连接成功！(模型: %s)', 'aiabstract'), $model)
            ];
        }

        $error_msg = $result['error']['message'] ?? __('未知错误', 'aiabstract');
        return [
            'success' => false,
            'message' => sprintf(__('API返回错误: %s', 'aiabstract'), $error_msg)
        ];
    }

    /**
     * 拉取 OpenAI 兼容接口的模型列表
     *
     * 从 chat/completions 地址推导 /models 端点：
     *   https://api.deepseek.com/v1/chat/completions  →  https://api.deepseek.com/v1/models
     */
    public function fetch_model_list($api_url, $api_key) {
        if (empty($api_url) || empty($api_key)) {
            return [
                'success' => false,
                'message' => __('API地址和API Key不能为空', 'aiabstract')
            ];
        }

        // 统一归一化后再推导 /models（base 与 /chat/completions 两种写法都支持）
        $base = preg_replace('#/chat/completions/?$#i', '', self::normalize_chat_url($api_url));
        $models_url = rtrim($base, '/') . '/models';

        $response = wp_remote_get($models_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key
            ],
            'timeout' => 15,
            'user-agent' => 'WordPress-AIAbstract/' . AIABSTRACT_VERSION
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => sprintf(__('请求失败: %s', 'aiabstract'), $response->get_error_message())
            ];
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($http_code !== 200) {
            $error_info = '';
            if ($http_code === 401) {
                $error_info = __(' - API Key 无效', 'aiabstract');
            } elseif ($http_code === 404) {
                $error_info = __(' - 该服务不支持 /models 端点，请手动填写模型名', 'aiabstract');
            } elseif ($http_code === 429) {
                $error_info = __(' - 请求频率超限', 'aiabstract');
            }
            return [
                'success' => false,
                'message' => sprintf(__('HTTP错误: %s%s', 'aiabstract'), $http_code, $error_info)
            ];
        }

        $result = @json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'message' => __('响应解析失败', 'aiabstract')
            ];
        }

        $models = [];
        // OpenAI 标准格式：{ "data": [ { "id": "..." } ] }
        if (isset($result['data']) && is_array($result['data'])) {
            foreach ($result['data'] as $item) {
                if (is_array($item) && !empty($item['id'])) {
                    $models[] = $item['id'];
                } elseif (is_object($item) && !empty($item->id)) {
                    $models[] = $item->id;
                }
            }
        }
        // 兼容格式：{ "models": [ "a", "b" ] } 或 ["a","b"]
        if (empty($models) && isset($result['models']) && is_array($result['models'])) {
            foreach ($result['models'] as $item) {
                if (is_string($item) && $item !== '') {
                    $models[] = $item;
                }
            }
        }

        $models = array_values(array_unique($models));
        sort($models);

        if (empty($models)) {
            return [
                'success' => false,
                'message' => __('响应中未找到模型列表，该服务可能不支持此接口，请手动填写模型名。', 'aiabstract')
            ];
        }

        return [
            'success' => true,
            'models' => $models
        ];
    }

    /**
     * 批量生成摘要
     */
    public function batch_generate_abstracts($post_ids, $options) {
        $results = [
            'success' => 0,
            'failed' => 0,
            'total' => count($post_ids),
            'details' => []
        ];

        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            if (!$post) {
                $results['failed']++;
                $results['details'][] = [
                    'post_id' => $post_id,
                    'success' => false,
                    'message' => __('文章不存在', 'aiabstract')
                ];
                continue;
            }

            $abstracts = $this->generate_single_abstract($post->post_title, $post->post_content, $options);

            if (!empty($abstracts)) {
                $results['success']++;
                $results['details'][] = [
                    'post_id' => $post_id,
                    'success' => true,
                    'message' => sprintf(__('摘要生成成功（%d 条）', 'aiabstract'), count($abstracts)),
                    'abstract' => $abstracts[0]
                ];

                // 保存到缓存
                $this->save_abstract_to_cache($post_id, $abstracts);
            } else {
                $results['failed']++;
                $results['details'][] = [
                    'post_id' => $post_id,
                    'success' => false,
                    'message' => __('摘要生成失败', 'aiabstract')
                ];
            }

            // 避免请求过于频繁
            usleep(500000); // 0.5秒
        }

        return $results;
    }

    /**
     * 生成单个摘要（仅自定义 API）
     */
    private function generate_single_abstract($title, $content, $options) {
        $timeout = 15;
        $abstract_length = isset($options['abstract_length']) ? intval($options['abstract_length']) : 100;

        // 清理内容
        $core = AIAbstract_Core::get_instance();
        $processed_content = $core->sanitize_content($content, 2000);

        $custom_api_url = self::normalize_chat_url($options['custom_api_url'] ?? '');
        $custom_api_password = $options['custom_api_password'] ?? '';
        $custom_api_model = $options['custom_api_model'] ?? 'deepseek-chat';
        $custom_prompt = $options['custom_prompt'] ?? null;

        if (empty($custom_api_url) || empty($custom_api_password)) {
            return false;
        }
        return $this->fetch_custom_abstract($custom_api_url, $custom_api_password, $custom_api_model, $title, $processed_content, $abstract_length, $timeout, $custom_prompt);
    }

    /**
     * 保存摘要到缓存（多摘要数组格式，兼容）
     */
    private function save_abstract_to_cache($post_id, $abstracts) {
        $cache_file = AIABSTRACT_CACHE_DIR . 'abstracts.json';
        $cache_data = [];

        if (file_exists($cache_file)) {
            $content = @file_get_contents($cache_file);
            $tmp = @json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
                $cache_data = $tmp;
            }
        }

        if (!is_array($abstracts)) {
            $abstracts = $abstracts ? [ $abstracts ] : [];
        }
        $cache_data[$post_id] = [
            'abstracts' => array_values($abstracts),
            'timestamp' => time(),
            'blocked' => false,
            'status' => 'show'
        ];

        $payload = json_encode($cache_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        return @file_put_contents($cache_file, $payload, LOCK_EX);
    }

    /**
     * 从自定义API获取摘要（返回多条摘要数组，最多 3 条）
     */
    private function fetch_custom_abstract($api_url, $api_password, $model, $title, $content, $abstract_length, $timeout, $custom_prompt = null) {
        if (!empty($custom_prompt)) {
            $prompt = str_replace(
                ['{abstractLength}', '{title}', '{content}'],
                [$abstract_length, $title, $content],
                $custom_prompt
            );
        } else {
            $prompt = "请你阅读以下文章标题和正文，写一句话进行文章摘要，摘要字数包括标点符号在内可以控制在 {$abstract_length} 字以内。标题：{$title}。正文：{$content}";
        }

        // 要求模型一次返回 3 条不同侧重点的摘要，用一行 --- 分隔（与 core 端保持一致）
        $system_prompt = "你是一个专业的文章摘要生成器。请针对用户提供的文章，生成3条不同侧重点的中文摘要（例如分别侧重：核心观点概括、关键信息提炼、延伸启发思考）。每条字数控制在 {$abstract_length} 字以内，语言流畅自然，避免使用\"本文\"、\"这篇文章\"等词语。三条摘要之间用一行 --- 分隔，不要编号，不要添加任何额外说明。";

        // max_tokens 按字数上限动态计算（3 条摘要）
        $max_tokens = max(500, intval($abstract_length * 9) + 300);
        $request_data = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => $max_tokens,
            'temperature' => 0.9
        ];

        $response = wp_remote_post($api_url, [
            'body' => json_encode($request_data, JSON_UNESCAPED_UNICODE),
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_password
            ],
            'timeout' => $timeout
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($http_code != 200) {
            return false;
        }

        $result = @json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        if (isset($result['choices'][0]['message']['content'])) {
            return $this->split_abstracts($result['choices'][0]['message']['content']);
        }
        if (isset($result['result'])) {
            return $this->split_abstracts($result['result']);
        }

        return false;
    }

    /**
     * 将 API 返回内容按 --- 分隔符拆成多条摘要（最多 3 条）。
     * 模型未按格式输出时退化为单条，不影响正常使用。
     */
    private function split_abstracts($content) {
        $content = trim($content);
        if ($content === '') {
            return [];
        }
        $parts = preg_split('/\s*---\s*/', $content);
        $abstracts = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $abstracts[] = $part;
            if (count($abstracts) >= 3) {
                break;
            }
        }
        return $abstracts;
    }
}