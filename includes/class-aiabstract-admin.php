<?php

class AIAbstract_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init();
    }
    
    private function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_aiabstract_fetch_models', array($this, 'ajax_fetch_models'));
    }
    
    public function ajax_fetch_models() {
        check_ajax_referer('aiabstract_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('权限不足。', 'aiabstract'));
        }
        
        $api_url = esc_url_raw($_POST['api_url'] ?? '');
        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        
        $api = AIAbstract_API::get_instance();
        $result = $api->fetch_model_list($api_url, $api_key);
        
        if ($result['success']) {
            wp_send_json_success(['models' => $result['models']]);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    public function add_admin_menu() {
        // 添加设置页面到"设置"菜单
        add_options_page(
            __('AI摘要设置', 'aiabstract'),
            __('AI摘要', 'aiabstract'),
            'manage_options',
            'aiabstract-settings',
            array($this, 'settings_page')
        );
        
        // 添加管理页面到"工具"菜单
        add_management_page(
            __('AI摘要管理', 'aiabstract'),
            __('AI摘要管理', 'aiabstract'),
            'manage_options',
            'aiabstract-manage',
            array($this, 'manage_page')
        );
    }
    
    public function enqueue_admin_scripts($hook) {
        // 只在插件页面加载脚本和样式
        if ('settings_page_aiabstract-settings' === $hook || 'tools_page_aiabstract-manage' === $hook) {
            wp_enqueue_style('aiabstract-admin', AIABSTRACT_PLUGIN_URL . 'assets/css/admin.css', array(), AIABSTRACT_VERSION);
            wp_enqueue_script('aiabstract-admin', AIABSTRACT_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), AIABSTRACT_VERSION, true);
            
            // 本地化脚本
            wp_localize_script('aiabstract-admin', 'aiabstract_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aiabstract_ajax_nonce'),
                'testing_text' => __('测试中...', 'aiabstract'),
                'success_text' => __('连接成功！', 'aiabstract'),
                'error_text' => __('连接失败', 'aiabstract')
            ));
            
            // 主流服务提供方（设置页快捷选择）
            wp_localize_script('aiabstract-admin', 'aiabstract_providers', $this->get_providers());
        }
    }
    
    /**
     * 主流 OpenAI 兼容服务提供方列表
     */
    private function get_providers() {
        return [
            'custom' => [
                'name' => __('自定义（手动填写）', 'aiabstract'),
                'url'  => '',
                'model'=> ''
            ],
            'deepseek' => [
                'name' => 'DeepSeek 深度求索',
                'url'  => 'https://api.deepseek.com/v1/chat/completions',
                'model'=> 'deepseek-chat'
            ],
            'opencode_zen' => [
                'name' => 'OpenCode Zen（全量计划）',
                'url'  => 'https://opencode.ai/zen/v1/chat/completions',
                'model'=> 'deepseek-v4-pro'
            ],
            'opencode_go' => [
                'name' => 'OpenCode Go（低价订阅）',
                'url'  => 'https://opencode.ai/zen/go/v1/chat/completions',
                'model'=> 'deepseek-v4-flash'
            ],
            'openai' => [
                'name' => 'OpenAI',
                'url'  => 'https://api.openai.com/v1/chat/completions',
                'model'=> 'gpt-4o-mini'
            ],
            'moonshot' => [
                'name' => 'Kimi 月之暗面',
                'url'  => 'https://api.moonshot.cn/v1/chat/completions',
                'model'=> 'kimi-latest'
            ],
            'qwen' => [
                'name' => '通义千问',
                'url'  => 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions',
                'model'=> 'qwen-plus'
            ],
            'zhipu' => [
                'name' => '智谱 GLM',
                'url'  => 'https://open.bigmodel.cn/api/paas/v4/chat/completions',
                'model'=> 'glm-4-flash'
            ],
            'xfyun' => [
                'name' => '讯飞星火',
                'url'  => 'https://spark-api-open.xf-yun.com/v2/chat/completions',
                'model'=> 'x1'
            ],
            'qianfan' => [
                'name' => '百度千帆',
                'url'  => 'https://qianfan.baidubce.com/v2/chat/completions',
                'model'=> 'ernie-speed-8k'
            ],
            'hunyuan' => [
                'name' => '腾讯混元',
                'url'  => 'https://api.hunyuan.cloud.tencent.com/v1/chat/completions',
                'model'=> 'hunyuan-turbo'
            ],
            'doubao' => [
                'name' => '豆包 · 火山方舟',
                'url'  => 'https://ark.cn-beijing.volces.com/api/v3/chat/completions',
                'model'=> ''
            ],
            'siliconflow' => [
                'name' => '硅基流动 SiliconFlow',
                'url'  => 'https://api.siliconflow.cn/v1/chat/completions',
                'model'=> 'deepseek-ai/DeepSeek-V3'
            ],
            'openrouter' => [
                'name' => 'OpenRouter',
                'url'  => 'https://openrouter.ai/api/v1/chat/completions',
                'model'=> 'openrouter/auto'
            ],
            'groq' => [
                'name' => 'Groq',
                'url'  => 'https://api.groq.com/openai/v1/chat/completions',
                'model'=> 'llama-3.3-70b-versatile'
            ],
        ];
    }
    
    public function register_settings() {
        register_setting('aiabstract_settings', 'aiabstract_options', array($this, 'sanitize_options'));
    }
    
    public function sanitize_options($input) {
        $sanitized = array();
        
        $sanitized['custom_api_url'] = AIAbstract_API::normalize_chat_url(esc_url_raw($input['custom_api_url']));
        $sanitized['custom_api_password'] = sanitize_text_field($input['custom_api_password']);
        $sanitized['custom_api_model'] = sanitize_text_field($input['custom_api_model']);
        $sanitized['abstract_length'] = absint($input['abstract_length']);
        $sanitized['custom_prompt'] = wp_kses_post($input['custom_prompt']);
        $sanitized['template_style'] = sanitize_text_field($input['template_style']);
        $sanitized['html_template'] = wp_kses_post($input['html_template']);
        $sanitized['debug_mode'] = sanitize_text_field($input['debug_mode']);
        $sanitized['enable_typewriter'] = sanitize_text_field($input['enable_typewriter']);
        
        return $sanitized;
    }
    
    public function settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $options = get_option('aiabstract_options');
        $default_url = 'https://api.deepseek.com/v1/chat/completions';
        $default_model = 'deepseek-chat';
        $default_prompt = "请你作为专业的文章摘要生成器，阅读以下文章标题和正文，生成一段简洁明了的中文摘要。\n\n要求：\n1. 摘要字数控制在 {abstractLength} 字以内\n2. 准确概括文章核心内容\n3. 语言流畅自然\n4. 避免使用\"本文\"、\"这篇文章\"等词语\n\n文章标题：{title}\n\n文章正文：{content}\n\n请直接生成摘要内容，不要添加额外说明：";
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
        
        $api_url = isset($options['custom_api_url']) ? $options['custom_api_url'] : $default_url;
        $api_password = isset($options['custom_api_password']) ? $options['custom_api_password'] : '';
        $api_model = isset($options['custom_api_model']) ? $options['custom_api_model'] : $default_model;
        $abstract_length = isset($options['abstract_length']) ? $options['abstract_length'] : '100';
        $custom_prompt = !empty($options['custom_prompt']) ? $options['custom_prompt'] : $default_prompt;
        $template_style = isset($options['template_style']) ? $options['template_style'] : 'default';
        $html_template = !empty($options['html_template']) ? $options['html_template'] : $default_html;
        $enable_typewriter = isset($options['enable_typewriter']) ? $options['enable_typewriter'] : '1';
        $debug_mode = isset($options['debug_mode']) ? $options['debug_mode'] : '0';
        
        $templates = [
            'default'   => ['name' => __('现代卡片', 'aiabstract'), 'desc' => __('默认 · 简洁清爽', 'aiabstract'), 'swatch' => 'modern'],
            'template1' => ['name' => __('纸墨古风', 'aiabstract'), 'desc' => __('朱印纸张质感', 'aiabstract'), 'swatch' => 'ink'],
            'template2' => ['name' => __('流光玻璃', 'aiabstract'), 'desc' => __('动态渐变背景', 'aiabstract'), 'swatch' => 'glass'],
            'template3' => ['name' => __('霓虹光晕', 'aiabstract'), 'desc' => __('跑灯渐变边框', 'aiabstract'), 'swatch' => 'neon'],
        ];
        
        // 当前地址匹配的提供方（用于下拉默认选中）
        $current_provider = 'custom';
        $normalized_url = AIAbstract_API::normalize_chat_url($api_url);
        foreach ($this->get_providers() as $pkey => $pval) {
            if (!empty($pval['url']) && AIAbstract_API::normalize_chat_url($pval['url']) === $normalized_url) {
                $current_provider = $pkey;
                break;
            }
        }
        ?>
        <div class="wrap aia-wrap">
            <div class="aia-header">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                <span class="aia-badge">v<?php echo esc_html(AIABSTRACT_VERSION); ?></span>
            </div>
            
            <form action="options.php" method="post">
                <?php settings_fields('aiabstract_settings'); ?>
                
                <div class="aia-settings">
                    
                    <!-- ===== API 配置 ===== -->
                    <div class="aia-card">
                        <div class="aia-card-head">
                            <span class="aia-card-icon">🔌</span>
                            <h2 class="aia-card-title"><?php _e('API 服务配置', 'aiabstract'); ?></h2>
                            <span class="aia-card-sub"><?php _e('兼容 OpenAI 协议', 'aiabstract'); ?></span>
                        </div>
                        <div class="aia-card-body">
                            <div class="aia-field">
                                <label class="aia-field-label" for="aia-provider"><?php _e('服务提供方', 'aiabstract'); ?> <span class="aia-required">*</span></label>
                                <select id="aia-provider" class="aia-input" style="max-width:360px;">
                                    <?php foreach ($this->get_providers() as $pkey => $pval): ?>
                                        <option value="<?php echo esc_attr($pkey); ?>" <?php selected($current_provider, $pkey); ?>><?php echo esc_html($pval['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="aia-field-desc"><?php _e('选择主流服务商自动填入 API 地址与模型建议（模型可在下方「拉取模型列表」获取实际 ID），也可选「自定义」手动填写任意 OpenAI 兼容地址。地址支持 base 形式（如 https://opencode.ai/zen/go/v1），保存或请求时会自动补全 /chat/completions。', 'aiabstract'); ?></p>
                            </div>
                            <div class="aia-field">
                                <label class="aia-field-label" for="aia-api-url"><?php _e('API 地址', 'aiabstract'); ?></label>
                                <input type="url" id="aia-api-url" class="regular-text aia-input mono" name="aiabstract_options[custom_api_url]" value="<?php echo esc_attr($api_url); ?>" placeholder="https://api.deepseek.com/v1/chat/completions" />
                                <p class="aia-field-desc">
                                    <?php _e('例如：', 'aiabstract'); ?> <code>https://api.deepseek.com/v1/chat/completions</code>（DeepSeek） · <code>https://spark-api-open.xf-yun.com/v2/chat/completions</code>（讯飞星火） · <code>https://qianfan.baidubce.com/v2/chat/completions</code>（百度千帆）
                                </p>
                                <div class="aia-test-row" style="margin-top:8px;">
                                    <button type="button" class="button button-primary" id="aia-test-conn">🔍 <?php _e('测试连接', 'aiabstract'); ?></button>
                                    <span class="aia-test-status" id="aia-test-status"></span>
                                </div>
                            </div>
                            <div class="aia-field">
                                <label class="aia-field-label" for="aia-api-key"><?php _e('API Key', 'aiabstract'); ?></label>
                                <div style="display:flex;gap:8px;align-items:center;max-width:640px;">
                                    <input type="password" id="aia-api-key" class="regular-text aia-input mono" name="aiabstract_options[custom_api_password]" value="<?php echo esc_attr($api_password); ?>" style="flex:1;" autocomplete="off" />
                                    <button type="button" class="button" id="aia-toggle-key"><?php _e('显示', 'aiabstract'); ?></button>
                                </div>
                                <p class="aia-field-desc"><?php _e('例如 DeepSeek 的 API Key、讯飞星火的 APIPassword、百度千帆 bce-v3 开头的完整密钥。', 'aiabstract'); ?></p>
                            </div>
                            <div class="aia-field">
                                <label class="aia-field-label" for="aia-api-model"><?php _e('模型名称', 'aiabstract'); ?></label>
                                <div style="display:flex;gap:8px;align-items:center;max-width:640px;flex-wrap:wrap;">
                                    <select id="aia-model-picker" class="aia-input" style="max-width:240px;flex:1;">
                                        <option value=""><?php _e('从下方列表选择…', 'aiabstract'); ?></option>
                                    </select>
                                    <input type="text" id="aia-api-model" class="regular-text aia-input mono" name="aiabstract_options[custom_api_model]" value="<?php echo esc_attr($api_model); ?>" placeholder="deepseek-chat" style="flex:2;min-width:160px;" />
                                    <button type="button" class="button" id="aia-load-models">📋 <?php _e('拉取模型列表', 'aiabstract'); ?></button>
                                </div>
                                <p class="aia-field-desc">
                                    <?php _e('点击「测试连接」成功后会自动拉取该 API 的模型列表，也可在下方手动输入任意模型名；留空则使用 deepseek-chat。', 'aiabstract'); ?>
                                    <span id="aia-model-status" class="aia-test-status"></span>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- ===== 生成设置 ===== -->
                    <div class="aia-card">
                        <div class="aia-card-head">
                            <span class="aia-card-icon">⚙️</span>
                            <h2 class="aia-card-title"><?php _e('生成设置', 'aiabstract'); ?></h2>
                            <span class="aia-card-sub"><?php _e('单次生成 3 条不同角度摘要', 'aiabstract'); ?></span>
                        </div>
                        <div class="aia-card-body">
                            <div class="aia-field">
                                <label class="aia-field-label" for="aia-length"><?php _e('摘要字数上限', 'aiabstract'); ?>：<b id="aia-length-val"><?php echo esc_html($abstract_length); ?></b> <?php _e('字', 'aiabstract'); ?></label>
                                <div style="display:flex;gap:12px;align-items:center;max-width:640px;">
                                    <input type="range" id="aia-length-range" min="20" max="500" step="10" value="<?php echo esc_attr($abstract_length); ?>" style="flex:1;" />
                                    <input type="number" id="aia-length" class="small-text tight" name="aiabstract_options[abstract_length]" min="20" max="1000" value="<?php echo esc_attr($abstract_length); ?>" />
                                </div>
                                <p class="aia-field-desc"><?php _e('此字数将作为 AI 生成每条摘要的参考上限（每条独立控制，共生成 3 条）。', 'aiabstract'); ?></p>
                            </div>
                            <div class="aia-field">
                                <label class="aia-field-label" for="aia-prompt"><?php _e('自定义 AI 提示词模板', 'aiabstract'); ?></label>
                                <div class="aia-textarea-wrap">
                                    <textarea id="aia-prompt" class="large-text aia-textarea" name="aiabstract_options[custom_prompt]" rows="9"><?php echo esc_textarea($custom_prompt); ?></textarea>
                                </div>
                                <p class="aia-char-count" id="aia-prompt-count"></p>
                                <p class="aia-field-desc">
                                    <?php _e('可用占位符：', 'aiabstract'); ?> <code>{abstractLength}</code> - <?php _e('字数上限', 'aiabstract'); ?> · <code>{title}</code> - <?php _e('文章标题', 'aiabstract'); ?> · <code>{content}</code> - <?php _e('文章正文', 'aiabstract'); ?><br>
                                    <?php _e('插件会自动附加指令，要求模型一次生成 3 条不同角度的摘要并缓存，页面每次访问随机展示其中一条。', 'aiabstract'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- ===== 显示样式 ===== -->
                    <div class="aia-card">
                        <div class="aia-card-head">
                            <span class="aia-card-icon">🎨</span>
                            <h2 class="aia-card-title"><?php _e('显示样式', 'aiabstract'); ?></h2>
                            <span class="aia-card-sub"><?php _e('文章页摘要外观', 'aiabstract'); ?></span>
                        </div>
                        <div class="aia-card-body">
                            <div class="aia-field">
                                <span class="aia-field-label"><?php _e('模板样式', 'aiabstract'); ?></span>
                                <div class="aia-template-picker">
                                    <?php foreach ($templates as $key => $tpl): ?>
                                    <label class="aia-template-item<?php echo $template_style === $key ? ' selected' : ''; ?>">
                                        <input type="radio" name="aiabstract_options[template_style]" value="<?php echo esc_attr($key); ?>" <?php checked($template_style, $key); ?> />
                                        <span class="aia-tpl-swatch <?php echo esc_attr($tpl['swatch']); ?>"></span>
                                        <span class="aia-tpl-name"><?php echo esc_html($tpl['name']); ?></span>
                                        <span class="aia-tpl-desc"><?php echo esc_html($tpl['desc']); ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="aia-field" id="aia-custom-html-field">
                                <label class="aia-field-label" for="aia-html"><?php _e('自定义摘要 HTML 模板', 'aiabstract'); ?></label>
                                <textarea id="aia-html" class="large-text aia-textarea" name="aiabstract_options[html_template]" rows="8"><?php echo esc_textarea($html_template); ?></textarea>
                                <p class="aia-field-desc">
                                    <?php _e('选择「现代卡片」时生效。必须保留', 'aiabstract'); ?> <code>{abstract}</code> <?php _e('占位符；显示/隐藏与打字机效果由插件自动处理，无需自行添加隐藏样式。', 'aiabstract'); ?>
                                </p>
                            </div>
                            <div class="aia-field">
                                <span class="aia-field-label"><?php _e('打字效果', 'aiabstract'); ?></span>
                                <div class="aia-check" data-check="typewriter">
                                    <label><input type="radio" name="aiabstract_options[enable_typewriter]" value="1" <?php checked($enable_typewriter, '1'); ?> /> <?php _e('启用', 'aiabstract'); ?></label>
                                    <label><input type="radio" name="aiabstract_options[enable_typewriter]" value="0" <?php checked($enable_typewriter, '0'); ?> /> <?php _e('禁用', 'aiabstract'); ?></label>
                                </div>
                                <p class="aia-field-desc"><?php _e('启用后摘要以打字机效果逐字显示；遇到显示问题可禁用。', 'aiabstract'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- ===== 调试 ===== -->
                    <div class="aia-card">
                        <div class="aia-card-head">
                            <span class="aia-card-icon">🛠️</span>
                            <h2 class="aia-card-title"><?php _e('调试', 'aiabstract'); ?></h2>
                        </div>
                        <div class="aia-card-body">
                            <div class="aia-field">
                                <span class="aia-field-label"><?php _e('调试模式', 'aiabstract'); ?></span>
                                <div class="aia-check" data-check="debug">
                                    <label><input type="radio" name="aiabstract_options[debug_mode]" value="1" <?php checked($debug_mode, '1'); ?> /> <?php _e('启用', 'aiabstract'); ?></label>
                                    <label><input type="radio" name="aiabstract_options[debug_mode]" value="0" <?php checked($debug_mode, '0'); ?> /> <?php _e('禁用', 'aiabstract'); ?></label>
                                </div>
                                <p class="aia-field-desc"><?php _e('启用后在文章页右下角显示缓存状态与最近日志，排查问题后建议关闭。', 'aiabstract'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                </div>
                
                <div class="aia-save-bar">
                    <?php submit_button(__('保存设置', 'aiabstract'), 'primary large', 'submit', false); ?>
                    <span class="aia-save-tip"><?php _e('保存后旧文章需在「AI摘要管理」中重新生成，才会产生 3 条新摘要。', 'aiabstract'); ?></span>
                </div>
            </form>
        </div>
        <?php
    }
    
    public function manage_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('您没有权限访问此页面。', 'aiabstract'));
        }
        
        // 这里可以调用管理页面类
        $manage_page = AIAbstract_Manage_Page::get_instance();
        $manage_page->render_page();
    }
}