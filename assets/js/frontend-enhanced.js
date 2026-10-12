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

    // 全局变量跟踪初始化状态
    let initialized = false;
    let fallbackTriggered = false;

    // 摘要文本的统一选择器：PHP 端渲染时带 data-aiabstract-abstract 标记，
    // 与具体模板结构无关，用户自定义 HTML 模板也能正常打字机显示。
    const ABSTRACT_SELECTOR = '[data-aiabstract-abstract]';

    // 更健壮的初始化方法
    function initializeAllEffects() {
        if (initialized) return;
        initialized = true;
        
        console.log('开始初始化AI摘要效果');
        
        try {
            // 设置随机标语
            initSlogan();
            
            // 初始化打字效果
            initTypewriterEffect();
            
            // 设置安全超时，确保内容最终显示
            setTimeout(ensureContentDisplay, 5000);
            
        } catch (error) {
            console.error('AI摘要效果初始化失败:', error);
            triggerFallback('初始化异常');
        }
    }

    function initSlogan() {
        try {
            const sloganElement = document.getElementById('ai-slogan');
            if (sloganElement) {
                const randomIndex = Math.floor(Math.random() * slogans.length);
                sloganElement.textContent = slogans[randomIndex];
            }
        } catch (error) {
            console.warn('标语设置失败:', error);
        }
    }

    function initTypewriterEffect() {
        // 查找所有可能的摘要元素（支持同页多个摘要容器）
        const targets = document.querySelectorAll(ABSTRACT_SELECTOR);
        if (!targets.length) {
            console.log('未找到有效的AI摘要元素');
            triggerFallback('未找到有效元素');
            return;
        }

        targets.forEach(function(span) {
            const fullText = span.textContent || span.innerText;
            if (!fullText || fullText.trim() === '') {
                // 空内容直接隐藏占位
                span.style.display = 'none';
                return;
            }

            console.log('找到摘要元素，开始打字效果');

            // 清除现有内容
            span.textContent = "";
            span.style.display = "inline";

            // 显示思考中状态
            let thinkingIndex = 0;
            const thinkingInterval = setInterval(function() {
                thinkingIndex = (thinkingIndex + 1) % 4;
                const dots = ".".repeat(thinkingIndex);
                span.textContent = "思考中" + dots;
            }, 500);

            // 设置打字效果开始的超时
            const typewriterTimeout = setTimeout(function() {
                clearInterval(thinkingInterval);
                startTypewriter(span, fullText);
            }, 1200);

            // 设置总超时，防止打字效果卡住
            setTimeout(function() {
                clearInterval(thinkingInterval);
                clearTimeout(typewriterTimeout);
                // 超时直接显示完整内容
                if (span.textContent.indexOf('思考中') === 0) {
                    span.textContent = fullText;
                }
                console.log('打字效果超时，直接显示内容');
            }, 10000); // 10秒总超时
        });
    }

    function startTypewriter(span, text) {
        let charIndex = 0;

        span.textContent = "";

        function typeNextChar() {
            if (charIndex < text.length) {
                span.textContent += text.charAt(charIndex);
                charIndex++;
                
                // 随机延迟，增加真实感
                const delay = Math.random() * 80 + 30;
                setTimeout(typeNextChar, delay);
            } else {
                console.log('打字效果完成');
                onTypewriterComplete();
            }
        }

        // 开始打字
        typeNextChar();
    }

    function onTypewriterComplete() {
        // 打字完成后的处理
        console.log('AI摘要打字效果完成');
    }

    function triggerFallback(reason) {
        if (fallbackTriggered) return;
        fallbackTriggered = true;
        
        console.log('触发回退显示，原因:', reason);
        
        // 直接显示所有隐藏的摘要内容
        const hiddenSpans = document.querySelectorAll(ABSTRACT_SELECTOR);
        hiddenSpans.forEach(span => {
            if (span.style.display === 'none' || span.textContent) {
                span.style.display = 'inline';
                span.style.opacity = '1';
                span.style.visibility = 'visible';
            }
        });
    }

    function ensureContentDisplay() {
        console.log('执行最终内容显示检查');
        
        // 检查是否有内容仍然隐藏
        const hiddenSpans = document.querySelectorAll(ABSTRACT_SELECTOR);
        let anyContentHidden = false;
        
        hiddenSpans.forEach(span => {
            const text = span.textContent || span.innerText;
            if (text && text.trim() !== '' && 
                (span.style.display === 'none' || span.offsetParent === null)) {
                anyContentHidden = true;
            }
        });

        if (anyContentHidden && !fallbackTriggered) {
            console.log('检测到隐藏内容，强制显示');
            triggerFallback('最终检查发现隐藏内容');
        }
    }

    // 浏览器兼容性检测
    function isBrowserCompatible() {
        try {
            // 检测基本功能
            if (typeof Promise === 'undefined') return false;
            if (typeof document.querySelector === 'undefined') return false;
            if (typeof document.addEventListener === 'undefined') return false;
            
            // 检测控制台（某些浏览器可能没有console）
            if (typeof console === 'undefined' || typeof console.log === 'undefined') {
                window.console = { log: function() {}, error: function() {}, warn: function() {} };
            }
            
            return true;
        } catch (e) {
            return false;
        }
    }

    // 主初始化函数
    function main() {
        if (!isBrowserCompatible()) {
            console.log('浏览器不兼容，使用降级显示');
            triggerFallback('浏览器不兼容');
            return;
        }

        // 多种初始化方式
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(initializeAllEffects, 100);
            });
        } else {
            setTimeout(initializeAllEffects, 100);
        }

        // 监听可能的动态加载
        $(document).on('aiabstract-content-loaded', function() {
            setTimeout(initializeAllEffects, 100);
        });

        // 全局错误处理
        window.addEventListener('error', function(e) {
            console.error('全局错误:', e.error);
            if (!fallbackTriggered) {
                triggerFallback('全局错误: ' + e.message);
            }
        });

        // 未处理的Promise rejection
        window.addEventListener('unhandledrejection', function(e) {
            console.error('未处理的Promise拒绝:', e.reason);
        });
    }

    // 启动
    main();

})(jQuery);