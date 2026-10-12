(function($) {
    'use strict';

    $(document).ready(function() {

        /* ---------- 设置页：API Key 显示/隐藏 ---------- */
        var $keyInput = $('#aia-api-key');
        var $keyToggle = $('#aia-toggle-key');
        if ($keyInput.length && $keyToggle.length) {
            $keyToggle.on('click', function() {
                var isPassword = $keyInput.attr('type') === 'password';
                $keyInput.attr('type', isPassword ? 'text' : 'password');
                $keyToggle.text(isPassword ? '隐藏' : '显示');
            });
        }

        /* ---------- 设置页：服务提供方快捷选择 ---------- */
        var $provider = $('#aia-provider');
        if ($provider.length && typeof aiabstract_providers !== 'undefined') {
            $provider.on('change', function() {
                var key = $(this).val();
                var p = aiabstract_providers[key];
                if (!p) return;

                if (p.url) {
                    $('#aia-api-url').val(p.url);
                }
                if (p.model) {
                    $('#aia-api-model').val(p.model);
                }

                setStatus($testStatus, '', '');
                if (key !== 'custom') {
                    setStatus($testStatus, '已选择「' + p.name + '」，请填写 API Key 后点击「🔍 测试连接」', 'ok');
                    // 尝试自动拉取该提供方的模型列表
                    loadModels(true);
                } else {
                    $('#aia-model-picker').find('option:not(:first)').remove();
                    setStatus($modelStatus, '', '');
                }
            });
        }

        /* ---------- 设置页：API 连接测试 + 拉取模型 ---------- */
        var $testBtn = $('#aia-test-conn');
        var $testStatus = $('#aia-test-status');
        var $loadBtn = $('#aia-load-models');
        var $modelStatus = $('#aia-model-status');

        function readApiForm() {
            return {
                api_url: $('#aia-api-url').val().trim(),
                api_key: $('#aia-api-key').val().trim(),
                model: $('#aia-api-model').val().trim() || 'deepseek-chat'
            };
        }

        function setStatus($el, text, type) {
            $el.removeClass('ok err').text(text || '');
            if (type) $el.addClass(type);
        }

        function loadModels(quiet) {
            var creds = readApiForm();
            if (!creds.api_url || !creds.api_key) {
                setStatus($modelStatus, '请先填写 API 地址与 Key', 'err');
                return;
            }
            if ($loadBtn.length) {
                $loadBtn.prop('disabled', true).text('拉取中…');
            }
            setStatus($modelStatus, '请求模型列表…');

            $.post(aiabstract_ajax.ajax_url, {
                action: 'aiabstract_fetch_models',
                nonce: aiabstract_ajax.nonce,
                api_url: creds.api_url,
                api_key: creds.api_key
            }, function(response) {
                if (response.success) {
                    var models = response.data.models || [];
                    var $input = $('#aia-api-model');
                    var $picker = $('#aia-model-picker');
                    var current = $input.val().trim();

                    // 填充下拉
                    $picker.find('option:not(:first)').remove();
                    models.forEach(function(m) {
                        $picker.append($('<option>').attr('value', m).text(m));
                    });

                    // 当前值为空或为兜底默认值时，自动选中第一个模型
                    if (!current || current === 'deepseek-chat') {
                        if (models.length) {
                            $input.val(models[0]);
                        }
                    }
                    setStatus($modelStatus, '✅ 已载入 ' + models.length + ' 个模型，请从下拉选择或手动输入', 'ok');
                } else {
                    setStatus($modelStatus, '❌ ' + (response.data || '拉取失败'), 'err');
                }
            }).fail(function() {
                setStatus($modelStatus, '❌ 请求失败，请检查网络', 'err');
            }).always(function() {
                if ($loadBtn.length) {
                    $loadBtn.prop('disabled', false).text('📋 拉取模型列表');
                }
            });
        }

        if ($testBtn.length && $testStatus.length) {
            $testBtn.on('click', function() {
                var creds = readApiForm();
                if (!creds.api_url || !creds.api_key) {
                    setStatus($testStatus, '请先填写 API 地址与 Key', 'err');
                    return;
                }
                $testBtn.prop('disabled', true).text('测试中…');
                setStatus($testStatus, '正在请求 API…');

                $.post(aiabstract_ajax.ajax_url, {
                    action: 'aiabstract_test_api',
                    nonce: aiabstract_ajax.nonce,
                    api_url: creds.api_url,
                    api_key: creds.api_key,
                    model: creds.model
                }, function(response) {
                    if (response.success) {
                        setStatus($testStatus, '✅ ' + response.data, 'ok');
                        // 测试成功后自动拉取模型列表
                        loadModels(true);
                    } else {
                        setStatus($testStatus, '❌ ' + response.data, 'err');
                    }
                }).fail(function() {
                    setStatus($testStatus, '❌ 请求失败，请检查网络连接', 'err');
                }).always(function() {
                    $testBtn.prop('disabled', false).text('🔍 测试连接');
                });
            });
        }

        // 下拉选择模型 → 同步到输入框
        $('#aia-model-picker').on('change', function() {
            var v = $(this).val();
            if (v) {
                $('#aia-api-model').val(v);
            }
        });

        if ($loadBtn.length) {
            $loadBtn.on('click', function() {
                loadModels(false);
            });
        }

        /* ---------- 设置页：字数滑块联动 ---------- */
        var $range = $('#aia-length-range');
        var $num = $('#aia-length');
        var $val = $('#aia-length-val');
        if ($range.length && $num.length) {
            var syncRange = function() {
                var v = parseInt($num.val(), 10);
                if (!isNaN(v)) {
                    $range.val(Math.min(Math.max(v, 20), 500));
                    if ($val.length) $val.text(v);
                }
            };
            $range.on('input', function() {
                var v = parseInt($(this).val(), 10);
                $num.val(v);
                if ($val.length) $val.text(v);
            });
            $num.on('input', syncRange);
        }

        /* ---------- 设置页：提示词字符统计 ---------- */
        var $prompt = $('#aia-prompt');
        var $count = $('#aia-prompt-count');
        if ($prompt.length && $count.length) {
            var countPrompt = function() {
                var len = $prompt.val().length;
                $count.html('当前 <b>' + len + '</b> 字符 · 占位符：<b>{abstractLength}</b> <b>{title}</b> <b>{content}</b>');
            };
            $prompt.on('input', countPrompt);
            countPrompt();
        }

        /* ---------- 设置页：模板卡片选中态 ---------- */
        $('.aia-template-item input[type="radio"]').on('change', function() {
            $('.aia-template-item').removeClass('selected');
            $(this).closest('.aia-template-item').addClass('selected');
        });

        /* ---------- 设置页：胶囊单选选中态 ---------- */
        $('.aia-check input[type="radio"]').on('change', function() {
            var $check = $(this).closest('.aia-check');
            $check.find('label').removeClass('on');
            $(this).closest('label').addClass('on');
        }).each(function() {
            if (this.checked) {
                $(this).closest('label').addClass('on');
            }
        });

        /* ---------- 管理页 ---------- */
        if ($('body').hasClass('tools_page_aiabstract-manage')) {
            console.log('AIAbstract 管理页面已加载');
        }
    });

})(jQuery);