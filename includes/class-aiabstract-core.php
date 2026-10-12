<?php

class AIAbstract_Core {
    
    private static $instance = null;
    private $cache_file;
    private $log_file;
    private $processed_posts = [];
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->cache_file = AIABSTRACT_CACHE_DIR . 'abstracts.json';
        $this->log_file = AIABSTRACT_CACHE_DIR . 'plugin.log';
        
        $this->init();
    }
    
    private function init() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_filter('the_content', array($this, 'insert_abstract'), 10);
        add_action('wp_footer', array($this, 'debug_info'), 100);
        
        // 每日清理任务
        add_action('aiabstract_daily_cleanup', array($this, 'daily_cleanup'));
        if (!wp_next_scheduled('aiabstract_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'aiabstract_daily_cleanup');
        }
    }
    
    public function enqueue_scripts() {
        if (is_single()) {
        // 前端摘要样式
        wp_enqueue_style(
            'aiabstract-frontend',
            AIABSTRACT_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            AIABSTRACT_VERSION
        );
        
        $options = get_option('aiabstract_options');
        $enable_typewriter = isset($options['enable_typewriter']) ? $options['enable_typewriter'] : '1';
        
        // 根据设置决定加载哪个脚本
        if ($enable_typewriter === '1') {
            wp_enqueue_script(
                'aiabstract-frontend',
                AIABSTRACT_PLUGIN_URL . 'assets/js/frontend-enhanced.js',
                array(),
                AIABSTRACT_VERSION,
                true
            );
        } else {
            // 如果禁用打字效果，加载一个极简脚本只显示标语
            wp_enqueue_script(
                'aiabstract-frontend-simple',
                AIABSTRACT_PLUGIN_URL . 'assets/js/frontend-simple.js',
                array(),
                AIABSTRACT_VERSION,
                true
            );
        }
    }
}
    
    public function insert_abstract($content) {
        if (!is_single() || in_array(get_the_ID(), $this->processed_posts)) {
            return $content;
        }
        
        $this->processed_posts[] = get_the_ID();
        
        $abstract = $this->get_abstract_for_post(get_the_ID(), get_the_title(), $content);
        
        if ($abstract) {
            $wrapped_abstract = $this->wrap_abstract($abstract);
            return $wrapped_abstract . $content;
        }
        
        return $content;
    }
    
    public function get_abstract_for_post($post_id, $title, $content) {
        $this->force_log("开始获取文章摘要 - ID: {$post_id}");
        
        $cache_result = $this->get_from_cache($post_id);
        
        if ($cache_result === 'blocked') {
            $this->force_log("文章ID: {$post_id} 已被标记为阻止请求，不再调用API");
            return false;
        }
        if ($cache_result === 'hidden') {
            $this->force_log("文章ID: {$post_id} 摘要被用户设置为隐藏，跳过API请求。");
            return false;
        }
        if (is_array($cache_result) && !empty($cache_result)) {
            $this->force_log("从缓存获取摘要成功 - ID: {$post_id}, 缓存条数: " . count($cache_result));
            return $this->pick_random_abstract($cache_result);
        }
        
        $options = get_option('aiabstract_options');
        $post_date = get_the_date('Y-m-d H:i:s', $post_id);
        $three_days_ago = date('Y-m-d H:i:s', strtotime('-3 days'));
        $is_older_than_3_days = $post_date < $three_days_ago;
        
        $timeout = 30; // 增加超时时间
        $abstract_length = isset($options['abstract_length']) ? intval($options['abstract_length']) : 100;
        $abstracts = [];
        
        $processed_content = $this->sanitize_content($content, 1500); // 减少内容长度
        
        $this->force_log("准备调用AI服务 - 来源: 自定义API, 文章长度: " . strlen($processed_content));
        
        // 仅支持自定义API（兼容OpenAI协议）
        $custom_api_url = AIAbstract_API::normalize_chat_url(isset($options['custom_api_url']) ? $options['custom_api_url'] : '');
        $custom_api_password = isset($options['custom_api_password']) ? $options['custom_api_password'] : '';
        $custom_api_model = isset($options['custom_api_model']) ? $options['custom_api_model'] : 'deepseek-chat';
        $custom_prompt = isset($options['custom_prompt']) ? $options['custom_prompt'] : null;
        
        if (empty($custom_api_url) || empty($custom_api_password)) {
            $this->force_log("错误: 自定义API地址或密码不能为空");
            return false;
        }
        
        $this->force_log("调用自定义API - URL: {$custom_api_url}, 模型: {$custom_api_model}");
        $abstracts = $this->fetch_custom_abstract($custom_api_url, $custom_api_password, $custom_api_model, $title, $processed_content, $abstract_length, $timeout, $custom_prompt);
        
        $blocked = false;
        if (empty($abstracts) && $is_older_than_3_days) {
            $blocked = true;
            $this->force_log("文章ID: {$post_id} 超过3天且API返回空，标记为阻止再次请求");
        }
        
        if (!empty($abstracts)) {
            $this->force_log("成功生成摘要 - ID: {$post_id}, 条数: " . count($abstracts));
        } else {
            $this->force_log("摘要生成失败 - ID: {$post_id}");
        }
        
        if ($abstracts || $blocked) {
            $this->write_to_cache($post_id, $abstracts, $blocked);
        }
        
        return empty($abstracts) ? false : $this->pick_random_abstract($abstracts);
    }
    
    /**
     * 从多条缓存摘要中随机取一条，保证每次访问看到的摘要不同。
     */
    private function pick_random_abstract($abstracts) {
        if (!is_array($abstracts) || empty($abstracts)) {
            return false;
        }
        $abstracts = array_values($abstracts);
        return $abstracts[array_rand($abstracts)];
    }
    
    public function sanitize_content($content, $length) {
        // 移除HTML标签
        $content = strip_tags($content);
        
        // 移除Markdown语法
        $content = preg_replace('/^(\s*#+.*)|(^(\s*[-+*]\s+.*))|(\[!\[.*?\]\(.*?\)\]\(.*?\)\])|(\!\[.*?\]\(.*?\))|(\[.*?\]\(.*?\))|(\*\s.*)|(-\s.*)|(`[^`]+`)|(```[\s\S]*?```)/m', '', $content);
        
        // 合并多余空格
        $content = preg_replace('/\s+/', ' ', $content);
        
        // 截取指定长度
        $content = mb_substr($content, 0, $length, 'UTF-8');
        
        // 确保内容以完整句子结束
        $last_period = mb_strrpos($content, '。');
        $last_exclamation = mb_strrpos($content, '！');
        $last_question = mb_strrpos($content, '？');
        
        $last_punctuation = max($last_period, $last_exclamation, $last_question);
        if ($last_punctuation !== false && $last_punctuation > $length * 0.5) {
            $content = mb_substr($content, 0, $last_punctuation + 1, 'UTF-8');
        }
        
        return $content;
    }
    
    private function wrap_abstract($abstract) {
        $options = get_option('aiabstract_options');
        $template_style = isset($options['template_style']) ? $options['template_style'] : 'default';
        
        // 检测是否启用打字效果
        $enable_typewriter = isset($options['enable_typewriter']) ? $options['enable_typewriter'] : '1';
        if ($enable_typewriter === '0') {
            $enable_typewriter = false;
        } else {
            $enable_typewriter = true;
            
            // 用户代理检测（仅在启用时进行）
            if (isset($_SERVER['HTTP_USER_AGENT'])) {
                $user_agent = $_SERVER['HTTP_USER_AGENT'];
                
                $problematic_patterns = [
                    'MSIE', 'Trident',
                    'Edge',
                    'SamsungBrowser',
                    'UCBrowser',
                    'QQBrowser',
                    'MiuiBrowser',
                ];
                
                foreach ($problematic_patterns as $pattern) {
                    if (stripos($user_agent, $pattern) !== false) {
                        $enable_typewriter = false;
                        $this->force_log("检测到可能不兼容的浏览器: {$pattern}, 禁用打字效果");
                        break;
                    }
                }
            }
        }
        
        // 摘要文本统一带 data-aiabstract-abstract 标记，前端 JS 据此定位。
        // 打字效果开启时默认隐藏，由 JS 逐字打出；关闭时直接可见。
        // 这样无论用户如何使用自定义 HTML 模板，摘要都能正常显示。
        $span_style = $enable_typewriter ? 'style="display:none;"' : 'style="display:inline;"';
        $abstract_span = '<span data-aiabstract-abstract ' . $span_style . '>' . esc_html($abstract) . '</span>';
        
        if ($template_style === 'default') {
            $default_html = <<<HTML
<div class="aiabstract-default-modern">
  <div class="aiabs-card">
    <div class="aiabs-head">
      <span class="aiabs-badge">AI摘要</span>
      <span class="aiabs-slogan" id="ai-slogan">根据文章内容生成</span>
    </div>
    <div class="aiabs-body">
      <p class="aiabs-text">{abstract}</p>
    </div>
  </div>
</div>
HTML;
            $template_html = isset($options['html_template']) ? trim($options['html_template']) : '';
            if ($template_html === '' || strpos($template_html, '{abstract}') === false) {
                $this->force_log("警告: 自定义摘要HTML模板为空或未包含{abstract}占位符，使用内置默认模板");
                $template_html = $default_html;
            }
            
            // 兼容用户旧的自定义模板：其中可能带有 style="display:none;" 包裹 {abstract}，
            // 显示/隐藏现在统一由 data-aiabstract-abstract 标记的 span 控制，这里先中和旧样式。
            $template_html = str_replace('style="display:none;"', 'style="display:inline;"', $template_html);
            
            return str_replace('{abstract}', $abstract_span, $template_html);
        } else {
            $allowed_templates = ['template1', 'template2', 'template3'];
            
            if (!in_array($template_style, $allowed_templates)) {
                $this->force_log("警告: 请求的模板样式 '{$template_style}' 不在允许的白名单中。");
                return '';
            }
            
            $safe_template_style = basename($template_style);
            $template_file = AIABSTRACT_PLUGIN_PATH . 'templates' . DIRECTORY_SEPARATOR . $safe_template_style . '.php';
            
            if (file_exists($template_file)) {
                ob_start();
                // 传递是否启用打字效果的信息给模板
                $enable_typewriter = $enable_typewriter;
                include $template_file;
                $output = ob_get_clean();
                
                return $output;
            } else {
                $this->force_log("错误: 模板文件 {$template_file} 不存在!");
                return '';
            }
        }
    }
    
    private function get_from_cache($post_id) {
        if (!is_numeric($post_id) || intval($post_id) <= 0) {
            return false;
        }
        $post_id = (string)intval($post_id);
        
        if (!file_exists($this->cache_file)) {
            return false;
        }
        
        $content = @file_get_contents($this->cache_file);
        $cache_data = @json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($cache_data)) {
            $this->force_log("缓存解析错误: " . json_last_error_msg());
            return false;
        }
        
        if (!isset($cache_data[$post_id])) {
            return false;
        }
        if (!empty($cache_data[$post_id]['blocked'])) {
            return 'blocked';
        }
        
        $status = $cache_data[$post_id]['status'] ?? 'show';
        if ($status !== 'show') {
            return 'hidden';
        }
        
        // 多摘要格式（v1.6.0+）：abstracts 数组
        if (isset($cache_data[$post_id]['abstracts']) && is_array($cache_data[$post_id]['abstracts']) && !empty($cache_data[$post_id]['abstracts'])) {
            return array_values($cache_data[$post_id]['abstracts']);
        }
        // 兼容旧格式：单条 abstract 字符串
        if (isset($cache_data[$post_id]['abstract']) && !empty($cache_data[$post_id]['abstract'])) {
            return [ $cache_data[$post_id]['abstract'] ];
        }
        
        return false;
    }
    
    private function write_to_cache($post_id, $abstracts, $blocked = false) {
        if (!is_numeric($post_id) || intval($post_id) <= 0) {
            return false;
        }
        $post_id = (string)intval($post_id);
        
        $cache_data = [];
        if (file_exists($this->cache_file)) {
            $content = @file_get_contents($this->cache_file);
            $tmp = @json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
                $cache_data = $tmp;
            } else {
                @copy($this->cache_file, $this->cache_file . '.corrupt.bak');
                $this->force_log("缓存解析失败: " . json_last_error_msg() . "，已备份。");
            }
        }
        
        if (!is_array($abstracts)) {
            $abstracts = $abstracts ? [ $abstracts ] : [];
        }
        $new_entry = [
            'abstracts'  => array_values($abstracts),
            'timestamp' => time(),
            'blocked'   => (bool)$blocked,
            'status'    => 'show',
        ];
        
        if (isset($cache_data[$post_id]) && $cache_data[$post_id] === $new_entry) {
             return true;
        }
        $cache_data[$post_id] = $new_entry;
        
        $payload = json_encode($cache_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $tmp_file = $this->cache_file . '.tmp';
        if (@file_put_contents($tmp_file, $payload, LOCK_EX) === false) {
            $this->force_log("写入临时缓存失败: {$tmp_file}");
            return false;
        }
        if (!@rename($tmp_file, $this->cache_file)) {
            @unlink($tmp_file);
            if (@file_put_contents($this->cache_file, $payload, LOCK_EX) === false) {
                $this->force_log("写缓存失败: " . $this->cache_file);
                return false;
            }
        }
        
        $this->force_log("缓存写入成功 - ID: {$post_id}, 摘要条数: " . count($abstracts));
        return true;
    }
    
    private function fetch_custom_abstract($api_url, $api_password, $model, $title, $content, $abstract_length, $timeout, $custom_prompt = null) {
        if (!empty($custom_prompt)) {
            $prompt = str_replace(['{abstractLength}', '{title}', '{content}'], [$abstract_length, $title, $content], $custom_prompt);
        } else {
            $prompt = "请你作为专业的文章摘要生成器，阅读以下文章标题和正文，生成一段简洁明了的中文摘要。\n\n要求：\n1. 摘要字数控制在 {$abstract_length} 字以内\n2. 准确概括文章核心内容\n3. 语言流畅自然\n4. 避免使用\"本文\"、\"这篇文章\"等词语\n\n文章标题：{$title}\n\n文章正文：{$content}\n\n请直接生成摘要内容，不要添加额外说明：";
        }
        
        // 要求模型一次返回 3 条不同侧重点的摘要，用一行 --- 分隔，由下方 split_abstracts() 解析。
        $system_prompt = "你是一个专业的文章摘要生成器。请针对用户提供的文章，生成3条不同侧重点的中文摘要（例如分别侧重：核心观点概括、关键信息提炼、延伸启发思考）。每条字数控制在 {$abstract_length} 字以内，语言流畅自然，避免使用\"本文\"、\"这篇文章\"等词语。三条摘要之间用一行 --- 分隔，不要编号，不要添加任何额外说明。";
        
        // max_tokens 按字数上限动态计算（3 条摘要，每条约 abstractLength 字，中文约 1-2 token/字）
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
        
        $this->force_log("发送自定义API请求 - URL: {$api_url}, 模型: {$model}");
        $this->force_log("请求内容预览: " . substr($prompt, 0, 200) . "...");
        
        $response = wp_remote_post($api_url, [
            'body' => json_encode($request_data, JSON_UNESCAPED_UNICODE),
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_password
            ],
            'timeout' => $timeout
        ]);
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->force_log("自定义API请求失败: " . $error_message);
            return false;
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        $this->force_log("自定义API响应 - 状态码: {$http_code}, 响应长度: " . strlen($response_body));
        $this->force_log("响应内容预览: " . substr($response_body, 0, 500));
        
        if ($http_code != 200) {
            $this->force_log("自定义API错误: HTTP {$http_code}");
            
            // 尝试解析错误信息
            $error_result = @json_decode($response_body, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($error_result['error']['message'])) {
                $this->force_log("API错误信息: " . $error_result['error']['message']);
            }
            return false;
        }
        
        $result = @json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->force_log("自定义API解析错误: " . json_last_error_msg() . " - 响应: " . substr($response_body, 0, 200));
            return false;
        }
        
        if (isset($result['choices'][0]['message']['content'])) {
            $abstracts = $this->split_abstracts($result['choices'][0]['message']['content']);
            $this->force_log("自定义API成功返回摘要, 解析条数: " . count($abstracts));
            return $abstracts;
        }
        if (isset($result['result'])) {
            $abstracts = $this->split_abstracts($result['result']);
            $this->force_log("自定义API成功返回摘要, 解析条数: " . count($abstracts));
            return $abstracts;
        }
        
        $this->force_log("自定义API业务错误: " . ($result['error']['message'] ?? $result['error_msg'] ?? '未知错误'));
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
    
    public function debug_info() {
        // 仅管理员可见：日志包含文章内容与接口响应，禁止向访客输出
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $options = get_option('aiabstract_options');
        if (!isset($options['debug_mode']) || !$options['debug_mode'] || !is_single()) {
            return;
        }
        
        $log_content = '日志文件不存在或无法读取。';
        if (file_exists($this->log_file) && is_readable($this->log_file)) {
            $log_size = filesize($this->log_file);
            $log_content = $log_size > 0 ? @file_get_contents($this->log_file, false, null, max(0, $log_size - 2048), 2048) : '暂无日志。';
        }
        $log_content = esc_html($log_content);
        
        $html = "\n";
        $html .= "<div style='position:fixed;bottom:10px;right:10px;background:#fff;padding:10px;border:1px solid #ccc;z-index:9999;font-size:12px;max-width:300px;max-height:400px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,0.1);border-radius:5px;'>\n";
        $html .= "    <strong>AIAbstract Debug Information</strong><br>\n";
        $html .= "    Cache Directory: " . (is_dir(AIABSTRACT_CACHE_DIR) ? (is_writable(AIABSTRACT_CACHE_DIR) ? 'Writable' : 'Not Writable') : 'Not Found') . "<br>\n";
        $html .= "    Log File: " . (file_exists($this->log_file) ? (is_writable($this->log_file) ? 'Writable' : 'Not Writable') : 'Not Found') . "<br>\n";
        $html .= "    <strong style='margin-top:5px;display:block;'>Recent Log:</strong><br><pre style='white-space:pre-wrap;word-wrap:break-word;background:#f8f8f8;padding:5px;border:1px solid #eee;border-radius:3px;'>{$log_content}</pre>\n";
        $html .= "</div>";
        echo $html;
    }
    
    private function force_log($message) {
        // 仅调试模式或 WP_DEBUG 下写日志，避免每次页面访问都产生磁盘 I/O（旧版日志曾积累到近 1MB）
        if (!$this->is_debug_enabled()) {
            return;
        }
        try {
            $log_line = "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
            if (@file_put_contents($this->log_file, $log_line, FILE_APPEND) === false) {
                error_log("[AIAbstract Log Error] " . $message);
            }
        } catch (\Throwable $e) {
            error_log("[AIAbstract Logging Exception] " . $e->getMessage());
        }
    }
    
    private $debug_enabled = null;
    
    private function is_debug_enabled() {
        if ($this->debug_enabled === null) {
            $options = get_option('aiabstract_options');
            $this->debug_enabled = (defined('WP_DEBUG') && WP_DEBUG) || !empty($options['debug_mode']);
        }
        return $this->debug_enabled;
    }
    
    public function daily_cleanup() {
        // 清理过期的缓存和日志
        $max_log_size = 10 * 1024 * 1024; // 10MB
        if (file_exists($this->log_file) && filesize($this->log_file) > $max_log_size) {
            file_put_contents($this->log_file, "[" . date('Y-m-d H:i:s') . "] 日志文件清理\n");
        }
    }
}