<?php
if (!defined('ABSPATH')) {
    exit;
}
$enable_typewriter = isset($enable_typewriter) ? $enable_typewriter : true;
?>
<div class="aiabs-theme-neon">
    <div class="aiabs-card-neon">
        <div class="aiabs-head-neon">
            <span class="aiabs-tag-neon">AI摘要</span>
            <span class="aiabs-slogan-neon" id="ai-slogan">霓虹之下，洞见全文</span>
        </div>
        <p class="aiabs-text-neon">
            <span data-aiabstract-abstract style="<?php echo $enable_typewriter ? 'display:none;' : 'display:inline;'; ?>"><?php echo esc_html($abstract); ?></span>
        </p>
    </div>
</div>