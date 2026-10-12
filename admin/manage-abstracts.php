<?php
/**
 * AIAbstract 管理页面
 *
 * 此文件用于在 WordPress 后台显示和管理 AI 摘要数据
 *
 * @package AIAbstract
 * @author 我的日志 & 李的日志
 * @version 1.8.0
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 确保核心类已加载
if (!class_exists('AIAbstract_Core')) {
    wp_die(__('AIAbstract 核心类未找到，请重新激活插件。', 'aiabstract'));
}

class AIAbstract_Manage_Page {

    private static $instance = null;
    private $cache_file;
    private $log_file;
    private $per_page = 20;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->cache_file = AIABSTRACT_CACHE_DIR . 'abstracts.json';
        $this->log_file = AIABSTRACT_CACHE_DIR . 'plugin.log';

        add_action('wp_ajax_aiabstract_test_api', array($this, 'ajax_test_api'));
        add_action('wp_ajax_aiabstract_clear_cache', array($this, 'ajax_clear_cache'));
    }

    public function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('您没有权限访问此页面。', 'aiabstract'));
        }

        $this->handle_actions();
        $this->display_page();
    }

    private function handle_actions() {
        if (!isset($_GET['action'])) {
            return;
        }

        // 验证 nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'aiabstract_manage_action')) {
            wp_die(__('安全验证失败，请重试。', 'aiabstract'));
        }

        $action = sanitize_text_field($_GET['action']);
        $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;

        switch ($action) {
            case 'delete_abstract':
                $this->delete_abstract($post_id);
                break;

            case 'toggle_abstract':
                $this->toggle_abstract($post_id);
                break;

            case 'regenerate_abstract':
                $this->regenerate_abstract($post_id);
                break;

            case 'delete_all_cache':
                $this->delete_all_cache();
                break;

            case 'export_abstracts':
                $this->export_abstracts();
                break;
        }
    }

    private function delete_abstract($post_id) {
        $abstracts = $this->get_abstracts();

        if (isset($abstracts[$post_id])) {
            unset($abstracts[$post_id]);
            if ($this->save_abstracts($abstracts)) {
                $this->add_message(__('摘要删除成功！', 'aiabstract'), 'success');
            } else {
                $this->add_message(__('删除失败，请检查文件权限。', 'aiabstract'), 'error');
            }
        } else {
            $this->add_message(__('未找到指定的摘要。', 'aiabstract'), 'warning');
        }

        wp_redirect(admin_url('tools.php?page=aiabstract-manage'));
        exit;
    }

    private function toggle_abstract($post_id) {
        $abstracts = $this->get_abstracts();

        if (isset($abstracts[$post_id])) {
            $current_status = $abstracts[$post_id]['status'] ?? 'show';
            $abstracts[$post_id]['status'] = ($current_status === 'show') ? 'hide' : 'show';

            if ($this->save_abstracts($abstracts)) {
                $this->add_message(__('摘要状态更新成功！', 'aiabstract'), 'success');
            } else {
                $this->add_message(__('状态更新失败，请检查文件权限。', 'aiabstract'), 'error');
            }
        }

        wp_redirect(admin_url('tools.php?page=aiabstract-manage'));
        exit;
    }

    private function regenerate_abstract($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            $this->add_message(__('文章不存在。', 'aiabstract'), 'error');
            wp_redirect(admin_url('tools.php?page=aiabstract-manage'));
            exit;
        }

        $core = AIAbstract_Core::get_instance();

        $abstract = $core->get_abstract_for_post($post_id, $post->post_title, $post->post_content);

        if ($abstract) {
            $this->add_message(__('摘要重新生成成功！', 'aiabstract'), 'success');
        } else {
            $this->add_message(__('摘要生成失败，请检查API配置。', 'aiabstract'), 'error');
        }

        wp_redirect(admin_url('tools.php?page=aiabstract-manage'));
        exit;
    }

    private function delete_all_cache() {
        $cache_files = [
            $this->cache_file,
            $this->cache_file . '.corrupt.bak',
            $this->cache_file . '.tmp',
            $this->log_file,
            AIABSTRACT_CACHE_DIR . 'user_count.json'
        ];

        $deleted = 0;
        foreach ($cache_files as $file) {
            if (file_exists($file) && unlink($file)) {
                $deleted++;
            }
        }

        if ($deleted > 0) {
            $this->add_message(sprintf(__('成功删除 %d 个缓存文件。', 'aiabstract'), $deleted), 'success');
        } else {
            $this->add_message(__('没有找到可删除的缓存文件。', 'aiabstract'), 'info');
        }

        wp_redirect(admin_url('tools.php?page=aiabstract-manage'));
        exit;
    }

    private function export_abstracts() {
        $abstracts = $this->get_abstracts();
        $export_data = [];

        foreach ($abstracts as $post_id => $data) {
            $post = get_post($post_id);
            if ($post) {
                $abstract_list = $data['abstracts'] ?? (isset($data['abstract']) ? [ $data['abstract'] ] : []);
                $export_data[] = [
                    'post_id' => $post_id,
                    'post_title' => $post->post_title,
                    'post_url' => get_permalink($post_id),
                    'abstract' => implode("\n---\n", $abstract_list),
                    'abstract_count' => count($abstract_list),
                    'status' => $data['status'] ?? 'show',
                    'generated_time' => isset($data['timestamp']) ? date('Y-m-d H:i:s', $data['timestamp']) : '',
                    'blocked' => $data['blocked'] ?? false
                ];
            }
        }

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="aiabstract-export-' . date('Y-m-d') . '.json"');
        echo json_encode($export_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public function ajax_test_api() {
        check_ajax_referer('aiabstract_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('权限不足。', 'aiabstract'));
        }

        // 仅支持自定义 API（兼容 OpenAI 协议）
        $credentials = [
            'api_url' => esc_url_raw($_POST['api_url'] ?? ''),
            'api_key' => sanitize_text_field($_POST['api_key'] ?? ''),
            'model' => sanitize_text_field($_POST['model'] ?? '')
        ];

        $api = AIAbstract_API::get_instance();
        $result = $api->test_api_connection('custom', $credentials);

        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    public function ajax_clear_cache() {
        check_ajax_referer('aiabstract_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('权限不足。', 'aiabstract'));
        }

        $cache_type = sanitize_text_field($_POST['cache_type'] ?? 'all');
        $result = $this->clear_specific_cache($cache_type);

        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    private function clear_specific_cache($cache_type) {
        switch ($cache_type) {
            case 'abstracts':
                if (file_exists($this->cache_file) && unlink($this->cache_file)) {
                    return [
                        'success' => true,
                        'message' => __('摘要缓存清理成功！', 'aiabstract')
                    ];
                }
                break;

            case 'logs':
                if (file_exists($this->log_file) && unlink($this->log_file)) {
                    return [
                        'success' => true,
                        'message' => __('日志文件清理成功！', 'aiabstract')
                    ];
                }
                break;

            case 'stats':
                $stats_file = AIABSTRACT_CACHE_DIR . 'user_count.json';
                if (file_exists($stats_file) && unlink($stats_file)) {
                    return [
                        'success' => true,
                        'message' => __('统计缓存清理成功！', 'aiabstract')
                    ];
                }
                break;

            case 'all':
                $deleted = 0;
                $files = [
                    $this->cache_file,
                    $this->log_file,
                    AIABSTRACT_CACHE_DIR . 'user_count.json'
                ];

                foreach ($files as $file) {
                    if (file_exists($file) && unlink($file)) {
                        $deleted++;
                    }
                }

                if ($deleted > 0) {
                    return [
                        'success' => true,
                        'message' => sprintf(__('成功清理 %d 个缓存文件！', 'aiabstract'), $deleted)
                    ];
                }
                break;
        }

        return [
            'success' => false,
            'message' => __('缓存清理失败或没有可清理的缓存。', 'aiabstract')
        ];
    }

    private function get_abstracts($search_term = '') {
        if (!file_exists($this->cache_file)) {
            return [];
        }

        $content = @file_get_contents($this->cache_file);
        $abstracts = @json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($abstracts)) {
            return [];
        }

        // 如果有搜索词，过滤结果
        if (!empty($search_term)) {
            $filtered_abstracts = [];
            foreach ($abstracts as $post_id => $data) {
                $post = get_post($post_id);
                if ($post && stripos($post->post_title, $search_term) !== false) {
                    $filtered_abstracts[$post_id] = $data;
                }
            }
            return $filtered_abstracts;
        }

        return $abstracts;
    }

    private function save_abstracts($abstracts) {
        $payload = json_encode($abstracts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        return @file_put_contents($this->cache_file, $payload, LOCK_EX);
    }

    private function add_message($message, $type = 'info') {
        $transient_key = 'aiabstract_admin_message_' . get_current_user_id();
        set_transient($transient_key, [
            'message' => $message,
            'type' => $type
        ], 30);
    }

    private function display_messages() {
        $transient_key = 'aiabstract_admin_message_' . get_current_user_id();
        $message_data = get_transient($transient_key);

        if ($message_data) {
            $class = 'notice-' . $message_data['type'];
            echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($message_data['message']) . '</p></div>';
            delete_transient($transient_key);
        }
    }

    private function display_page() {
        $search_term = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;

        $abstracts = $this->get_abstracts($search_term);
        $total_abstracts = count($abstracts);
        $total_pages = ceil($total_abstracts / $this->per_page);
        $offset = ($current_page - 1) * $this->per_page;
        $paged_abstracts = array_slice($abstracts, $offset, $this->per_page, true);

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('AI 摘要管理', 'aiabstract'); ?></h1>

            <a href="<?php echo wp_nonce_url(admin_url('tools.php?page=aiabstract-manage&action=export_abstracts'), 'aiabstract_manage_action'); ?>" class="page-title-action">
                <?php _e('导出摘要', 'aiabstract'); ?>
            </a>

            <a href="<?php echo wp_nonce_url(admin_url('tools.php?page=aiabstract-manage&action=delete_all_cache'), 'aiabstract_manage_action'); ?>" class="page-title-action" onclick="return confirm('<?php _e('确定要删除所有缓存吗？此操作不可恢复！', 'aiabstract'); ?>');">
                <?php _e('清理缓存', 'aiabstract'); ?>
            </a>

            <hr class="wp-header-end">

            <?php $this->display_messages(); ?>

            <div class="aiabstract-admin-container">
                <!-- 统计卡片 -->
                <div class="aia-stats">
                    <div class="aia-stat aia-stat-total">
                        <div class="aia-stat-num"><?php echo $total_abstracts; ?></div>
                        <div class="aia-stat-label">📄 <?php _e('总摘要数', 'aiabstract'); ?></div>
                    </div>
                    <div class="aia-stat aia-stat-visible">
                        <div class="aia-stat-num"><?php echo $this->count_visible_abstracts($abstracts); ?></div>
                        <div class="aia-stat-label">👁️ <?php _e('显示中', 'aiabstract'); ?></div>
                    </div>
                    <div class="aia-stat aia-stat-recent">
                        <div class="aia-stat-num"><?php echo $this->count_recent_abstracts($abstracts); ?></div>
                        <div class="aia-stat-label">🕐 <?php _e('最近7天', 'aiabstract'); ?></div>
                    </div>
                </div>

                <!-- 搜索和批量操作 -->
                <div class="aia-toolbar">
                    <form method="get" class="aia-search">
                        <input type="hidden" name="page" value="aiabstract-manage">
                        <input type="search" name="s" value="<?php echo esc_attr($search_term); ?>" placeholder="<?php _e('搜索文章标题...', 'aiabstract'); ?>" class="aiabstract-search-input">
                        <button type="submit" class="button"><?php _e('搜索', 'aiabstract'); ?></button>
                        <?php if ($search_term): ?>
                            <a href="<?php echo admin_url('tools.php?page=aiabstract-manage'); ?>" class="button"><?php _e('清除搜索', 'aiabstract'); ?></a>
                        <?php endif; ?>
                    </form>

                    <div class="aiabstract-bulk-actions">
                        <button type="button" class="button" id="aiabstract-test-api"><?php _e('测试 API 连接', 'aiabstract'); ?></button>
                        <button type="button" class="button" id="aiabstract-clear-cache"><?php _e('清理缓存', 'aiabstract'); ?></button>
                    </div>
                </div>

                <!-- 摘要列表 -->
                <div class="aiabstract-panel">
                    <div class="aiabstract-panel-heading">
                        <h3 class="aiabstract-panel-title"><?php _e('摘要列表', 'aiabstract'); ?></h3>
                    </div>

                    <div class="aiabstract-panel-body">
                        <?php if (empty($paged_abstracts)): ?>
                            <div class="aiabstract-empty-state">
                                <div class="aia-empty-icon">🗒️</div>
                                <p><?php _e('暂无摘要数据。', 'aiabstract'); ?></p>
                                <?php if ($search_term): ?>
                                    <p><?php printf(__('没有找到包含"%s"的摘要。', 'aiabstract'), esc_html($search_term)); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th><?php _e('文章', 'aiabstract'); ?></th>
                                        <th><?php _e('摘要内容', 'aiabstract'); ?></th>
                                        <th><?php _e('生成时间', 'aiabstract'); ?></th>
                                        <th><?php _e('状态', 'aiabstract'); ?></th>
                                        <th><?php _e('操作', 'aiabstract'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($paged_abstracts as $post_id => $data): ?>
                                        <?php
                                        $post = get_post($post_id);
                                        if (!$post) continue;

                                        $abstract_list = $data['abstracts'] ?? (isset($data['abstract']) ? [ $data['abstract'] ] : []);
                                        $abstract_content = implode("\n\n---\n\n", $abstract_list);
                                        $abstract_count = count($abstract_list);
                                        $timestamp = isset($data['timestamp']) ? date('Y-m-d H:i:s', $data['timestamp']) : '';
                                        $status = $data['status'] ?? 'show';
                                        $is_blocked = $data['blocked'] ?? false;
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><a href="<?php echo get_edit_post_link($post_id); ?>" target="_blank"><?php echo esc_html($post->post_title); ?></a></strong>
                                                <div class="row-actions">
                                                    <span class="id">ID: <?php echo $post_id; ?></span> |
                                                    <span class="view"><a href="<?php echo get_permalink($post_id); ?>" target="_blank"><?php _e('查看', 'aiabstract'); ?></a></span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="aiabstract-abstract-preview" style="white-space:pre-wrap;">
                                                    <?php echo esc_html($abstract_content); ?>
                                                    <?php if ($abstract_count > 1): ?>
                                                        <div class="aiabstract-status-badge aiabstract-status-show" style="margin-top:6px;"><?php echo sprintf(__('共 %d 条摘要，访问时随机展示', 'aiabstract'), $abstract_count); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td><?php echo esc_html($timestamp); ?></td>
                                            <td>
                                                <span class="aiabstract-status-badge aiabstract-status-<?php echo $status; ?>">
                                                    <?php echo $status === 'show' ? __('显示', 'aiabstract') : __('隐藏', 'aiabstract'); ?>
                                                </span>
                                                <?php if ($is_blocked): ?>
                                                    <span class="aiabstract-status-badge aiabstract-status-blocked"><?php _e('已阻止', 'aiabstract'); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="aiabstract-action-buttons">
                                                    <a href="<?php echo wp_nonce_url(admin_url('tools.php?page=aiabstract-manage&action=toggle_abstract&post_id=' . $post_id), 'aiabstract_manage_action'); ?>" class="button button-small">
                                                        <?php echo $status === 'show' ? __('隐藏', 'aiabstract') : __('显示', 'aiabstract'); ?>
                                                    </a>
                                                    <a href="<?php echo wp_nonce_url(admin_url('tools.php?page=aiabstract-manage&action=regenerate_abstract&post_id=' . $post_id), 'aiabstract_manage_action'); ?>" class="button button-small">
                                                        <?php _e('重新生成', 'aiabstract'); ?>
                                                    </a>
                                                    <a href="<?php echo wp_nonce_url(admin_url('tools.php?page=aiabstract-manage&action=delete_abstract&post_id=' . $post_id), 'aiabstract_manage_action'); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php _e('确定要删除这个摘要吗？', 'aiabstract'); ?>');">
                                                        <?php _e('删除', 'aiabstract'); ?>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <!-- 分页 -->
                            <?php if ($total_pages > 1): ?>
                                <div class="aiabstract-pagination">
                                    <?php
                                    echo paginate_links(array(
                                        'base' => add_query_arg('paged', '%#%'),
                                        'format' => '',
                                        'prev_text' => __('&laquo; 上一页'),
                                        'next_text' => __('下一页 &raquo;'),
                                        'total' => $total_pages,
                                        'current' => $current_page,
                                        'add_args' => $search_term ? array('s' => $search_term) : array()
                                    ));
                                    ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- API 测试模态框 -->
        <div id="aiabstract-api-test-modal" style="display: none;">
            <div class="aiabstract-modal-content">
                <h3><?php _e('测试 API 连接', 'aiabstract'); ?></h3>
                <form id="aiabstract-api-test-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('API 地址', 'aiabstract'); ?></th>
                            <td>
                                <input type="url" name="api_url" class="regular-text mono" placeholder="<?php _e('https://api.example.com/v1/chat/completions', 'aiabstract'); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('API Key', 'aiabstract'); ?></th>
                            <td>
                                <input type="password" name="api_key" class="regular-text" placeholder="<?php _e('输入 API Key', 'aiabstract'); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('模型名称', 'aiabstract'); ?></th>
                            <td>
                                <input type="text" name="model" class="regular-text" value="deepseek-chat" placeholder="<?php _e('例如: deepseek-chat, ernie-speed-8k', 'aiabstract'); ?>">
                            </td>
                        </tr>
                    </table>
                    <div class="aia-modal-actions">
                        <button type="submit" class="button button-primary"><?php _e('开始测试', 'aiabstract'); ?></button>
                        <button type="button" class="button aiabstract-modal-close"><?php _e('取消', 'aiabstract'); ?></button>
                    </div>
                    <div id="aiabstract-test-result" class="aia-test-result" style="display: none;"></div>
                </form>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var $modal = $('#aiabstract-api-test-modal');

            function openModal() {
                $modal.css('display', 'flex');
            }

            function closeModal() {
                $modal.css('display', 'none');
            }

            // 打开 API 测试模态框
            $('#aiabstract-test-api').on('click', openModal);

            // 关闭模态框
            $('.aiabstract-modal-close').on('click', closeModal);

            // 点击遮罩关闭
            $modal.on('click', function(e) {
                if (e.target === this) {
                    closeModal();
                }
            });

            // ESC 关闭
            $(document).on('keyup', function(e) {
                if (e.keyCode === 27 && $modal.is(':visible')) {
                    closeModal();
                }
            });

            // API 测试表单提交
            $('#aiabstract-api-test-form').on('submit', function(e) {
                e.preventDefault();

                var $form = $(this);
                var $result = $('#aiabstract-test-result');
                var $submit = $form.find('button[type="submit"]');

                $result.hide().removeClass('aia-success aia-error');
                $submit.prop('disabled', true).text(aiabstract_ajax.testing_text);

                $.post(aiabstract_ajax.ajax_url, {
                    action: 'aiabstract_test_api',
                    nonce: aiabstract_ajax.nonce,
                    api_url: $form.find('input[name="api_url"]').val(),
                    api_key: $form.find('input[name="api_key"]').val(),
                    model: $form.find('input[name="model"]').val()
                }, function(response) {
                    if (response.success) {
                        $result.addClass('aia-success').text('✅ ' + response.data).show();
                    } else {
                        $result.addClass('aia-error').text('❌ ' + response.data).show();
                    }
                }).fail(function() {
                    $result.addClass('aia-error').text('❌ <?php _e('请求失败，请检查网络连接。', 'aiabstract'); ?>').show();
                }).always(function() {
                    $submit.prop('disabled', false).text('<?php _e('开始测试', 'aiabstract'); ?>');
                });
            });

            // 清理缓存
            $('#aiabstract-clear-cache').on('click', function() {
                if (!confirm('<?php _e('确定要清理缓存吗？', 'aiabstract'); ?>')) {
                    return;
                }

                var $button = $(this);
                $button.prop('disabled', true).text('<?php _e('清理中...', 'aiabstract'); ?>');

                $.post(aiabstract_ajax.ajax_url, {
                    action: 'aiabstract_clear_cache',
                    nonce: aiabstract_ajax.nonce,
                    cache_type: 'all'
                }, function(response) {
                    if (response.success) {
                        alert(response.data);
                        location.reload();
                    } else {
                        alert(response.data);
                    }
                }).fail(function() {
                    alert('<?php _e('请求失败，请检查网络连接。', 'aiabstract'); ?>');
                }).always(function() {
                    $button.prop('disabled', false).text('<?php _e('清理缓存', 'aiabstract'); ?>');
                });
            });
        });
        </script>
        <?php
    }

    private function count_visible_abstracts($abstracts) {
        $count = 0;
        foreach ($abstracts as $data) {
            if (($data['status'] ?? 'show') === 'show') {
                $count++;
            }
        }
        return $count;
    }

    private function count_recent_abstracts($abstracts) {
        $count = 0;
        $one_week_ago = time() - (7 * 24 * 60 * 60);

        foreach ($abstracts as $data) {
            if (isset($data['timestamp']) && $data['timestamp'] > $one_week_ago) {
                $count++;
            }
        }
        return $count;
    }
}

// 初始化管理页面（单例，注册 AJAX 处理）
AIAbstract_Manage_Page::get_instance();