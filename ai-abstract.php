<?php
/**
 * Plugin Name: AIAbstract
 * Plugin URI: https://onewumi.top/
 * Description: AI 文章摘要生成插件，支持兼容 OpenAI 协议的自定义 API
 * Version: 1.8.0
 * Author: 雾迷修改于ilog
 * Author URI: https://onewumi.top/
 * License: GPL v2 or later
 * Text Domain: aiabstract
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 定义插件常量
define('AIABSTRACT_VERSION', '1.8.0');
define('AIABSTRACT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AIABSTRACT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('AIABSTRACT_CACHE_DIR', AIABSTRACT_PLUGIN_PATH . 'cache/');

// 自动加载类文件
spl_autoload_register(function ($class_name) {
    if (strpos($class_name, 'AIAbstract_') === 0) {
        $file = AIABSTRACT_PLUGIN_PATH . 'includes/' . 'class-' . strtolower(str_replace('_', '-', $class_name)) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

class AIAbstract_Plugin {
    
    private static $instance = null;
    private $core;
    private $admin;
    
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
        // 注册激活和停用钩子
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // 初始化核心功能和管理功能
        add_action('plugins_loaded', array($this, 'load_modules'));
        
        // 加载文本域
        add_action('init', array($this, 'load_textdomain'));
    }
    
    public function load_modules() {
        // 初始化核心功能
        $this->core = AIAbstract_Core::get_instance();
        
        // 初始化管理功能
        $this->admin = AIAbstract_Admin::get_instance();
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('aiabstract', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function activate() {
        // 确保缓存目录存在
        if (!file_exists(AIABSTRACT_CACHE_DIR)) {
            wp_mkdir_p(AIABSTRACT_CACHE_DIR);
        }
        
    // 设置默认选项
    $default_options = array(
        'custom_api_url' => 'https://api.deepseek.com/v1/chat/completions',
        'custom_api_password' => '',
        'custom_api_model' => 'deepseek-chat',
        'abstract_length' => '100',
        'custom_prompt' => '',
        'template_style' => 'default',
        'html_template' => '',
        'enable_typewriter' => '1',
        'debug_mode' => '0'
    );
    
    $current_options = get_option('aiabstract_options', array());
    update_option('aiabstract_options', array_merge($default_options, $current_options));
}
    
    public function deactivate() {
        // 清理工作
        wp_clear_scheduled_hook('aiabstract_daily_cleanup');
    }
}

// 初始化插件
AIAbstract_Plugin::get_instance();

// 包含其他必要文件
require_once AIABSTRACT_PLUGIN_PATH . 'includes/class-aiabstract-admin.php';
require_once AIABSTRACT_PLUGIN_PATH . 'admin/manage-abstracts.php';