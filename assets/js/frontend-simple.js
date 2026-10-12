(function($) {
    'use strict';
    
    // 随机文案列表
    const slogans = [
        "减法去冗余，乘法增价值",
        "智能摘要，一览无余",
        "穿越文字森林，抵达核心花园",
        "故事的灵魂，在此刻显形",
        "本文核心，尽在掌握",
        "精华萃取，一秒洞悉",
        "精彩内容，快速预览"
    ];

    // 简单初始化：只设置标语，确保内容可见
    function initSimple() {
        // 设置随机标语
        const sloganElement = document.getElementById('ai-slogan');
        if (sloganElement) {
            const randomIndex = Math.floor(Math.random() * slogans.length);
            sloganElement.textContent = slogans[randomIndex];
        }

        // 确保所有摘要内容可见（data-aiabstract-abstract 由 PHP 端统一标记）
        const spans = document.querySelectorAll('[data-aiabstract-abstract]');
        spans.forEach(span => {
            span.style.display = 'inline';
            span.style.opacity = '1';
            span.style.visibility = 'visible';
        });

        // 确保容器可见
        const containers = document.querySelectorAll('.ai-summary-gufeng-container, .ai-summary-dynamic-container, .ai-summary-glowing-container');
        containers.forEach(container => {
            container.style.display = 'block';
            container.style.opacity = '1';
            container.style.visibility = 'visible';
        });
    }

    // 文档加载完成后初始化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSimple);
    } else {
        initSimple();
    }

})(jQuery);