<?php
if (!defined('ABSPATH')) {
    exit;
}
$enable_typewriter = isset($enable_typewriter) ? $enable_typewriter : true;
?>
<div class="aiabs-theme-ink">
    <div class="aiabs-card-ink">
        <div class="aiabs-head-ink">
            <span class="aiabs-stamp">AI摘要</span>
            <span class="aiabs-slogan-ink" id="ai-slogan">字里行间，一览要义</span>
        </div>
        <p class="aiabs-text-ink">
            <span data-aiabstract-abstract style="<?php echo $enable_typewriter ? 'display:none;' : 'display:inline;'; ?>"><?php echo esc_html($abstract); ?></span>
        </p>
    </div>
</div>