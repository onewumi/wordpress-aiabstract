<?php
/**
 * AIAbstract 插件卸载脚本
 *
 * 此文件在插件删除时执行，用于清理插件数据
 *
 * @package AIAbstract
 * @author 我的日志 & 李的日志
 * @version 1.8.0
 */

// 防止直接访问
if (!defined('ABSPATH') || !defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// 检查是否设置了完全删除选项
$options = get_option('aiabstract_options');
$delete_data = isset($options['delete_data_on_uninstall']) ? $options['delete_data_on_uninstall'] : false;

// 如果设置了完全删除，或者选项不存在（首次安装后未设置），则清理数据
if ($delete_data || $options === false) {
    // 删除插件选项
    delete_option('aiabstract_options');
    delete_option('aiabstract_blog_id');
    delete_option('aiabstract_reported_v120');

    // 删除用户元数据（如果有的话）
    // delete_metadata('user', 0, 'aiabstract_', '', true);

    // 清理定时任务
    $timestamp = wp_next_scheduled('aiabstract_daily_cleanup');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'aiabstract_daily_cleanup');
    }

    // 删除缓存文件
    $plugin_dir = plugin_dir_path(__FILE__);
    $cache_files = [
        $plugin_dir . 'cache/abstracts.json',
        $plugin_dir . 'cache/abstracts.json.corrupt.bak',
        $plugin_dir . 'cache/plugin.log',
        $plugin_dir . 'cache/user_count.json',
        $plugin_dir . 'cache/abstracts.json.tmp',
        $plugin_dir . 'cache/index.php',
        $plugin_dir . 'cache/.htaccess'
    ];

    foreach ($cache_files as $file) {
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    // 尝试删除缓存目录（如果为空）
    $cache_dir = $plugin_dir . 'cache/';
    if (is_dir($cache_dir)) {
        // 检查目录是否为空
        $files = scandir($cache_dir);
        if (count($files) <= 2) { // 只有 . 和 ..
            @rmdir($cache_dir);
        }
    }

    // 记录卸载日志
    if (function_exists('wp_log')) {
        wp_log('AIAbstract 插件已卸载，数据已清理', 'info');
    }
} else {
    // 只删除必要的选项，保留缓存数据以便重新安装时使用
    delete_option('aiabstract_reported_v120');

    if (function_exists('wp_log')) {
        wp_log('AIAbstract 插件已卸载，数据保留', 'info');
    }
}

// 清理转义数据（如果有）
// global $wpdb;
// $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE 'aiabstract\_%'");

// 显示卸载完成消息（在插件管理页面）
add_action('admin_notices', function() {
    if (isset($_GET['deleted']) && $_GET['deleted'] === 'aiabstract') {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p>' . __('AIAbstract 插件已成功卸载。', 'aiabstract') . '</p>';
        echo '</div>';
    }
});

// 记录到错误日志
error_log('AIAbstract WordPress Plugin uninstalled on ' . date('Y-m-d H:i:s'));