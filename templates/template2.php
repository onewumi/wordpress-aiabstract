<?php
if (!defined('ABSPATH')) {
    exit;
}
$enable_typewriter = isset($enable_typewriter) ? $enable_typewriter : true;
?>
<div class="aiabs-theme-glass">
    <div class="aiabs-glass-bg"></div>
    <div class="aiabs-card-glass">
        <div class="aiabs-head-glass">
            <span class="aiabs-tag-glass">AI摘要</span>
            <span class="aiabs-slogan-glass" id="ai-slogan">流光溢彩，核心尽览</span>
        </div>
        <p class="aiabs-text-glass">
            <span data-aiabstract-abstract style="<?php echo $enable_typewriter ? 'display:none;' : 'display:inline;'; ?>"><?php echo esc_html($abstract); ?></span>
        </p>
    </div>
</div>