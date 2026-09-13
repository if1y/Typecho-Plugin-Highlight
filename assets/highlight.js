/**
 * Highlight 插件 JavaScript
 * 参考简洁优雅的实现方式
 */

(function() {
    'use strict';

    // 配置（从 PHP 传递）
    const CONFIG = window.HIGHLIGHT_CONFIG || {
        showCopyButton: true
    };

    /**
     * 包装代码块：创建不滚动的 wrapper，迁移语法标签并添加复制按钮
     *
     * 注意：pre 自身是横向滚动容器（overflow-x: auto），其内部的绝对定位元素
     * 会随内容一起滚动。因此需把语法标签从 pre 中移出，挂到不滚动的 wrapper 上，
     * 才能使其固定停留在代码块左上角。
     */
    function addCopyButton(preElement) {
        if (preElement.parentElement.classList.contains('code-block-wrapper')) return;

        // 语法名称标签（SSR 注入在 pre 内，需迁移到不滚动的 wrapper）
        const langName = preElement.querySelector('.code-block-extension-lang-name');

        // 既无标签又无需复制按钮时，不做包装，保持 DOM 简洁
        if (!langName && !CONFIG.showCopyButton) return;

        // 创建包装容器：自身不滚动，作为标签与按钮共同的绝对定位基准
        const wrapper = document.createElement('div');
        wrapper.className = 'code-block-wrapper';

        // 将 pre 元素包装起来
        preElement.parentNode.insertBefore(wrapper, preElement);
        wrapper.appendChild(preElement);

        // 将语法标签从滚动的 pre 中移出，改挂到 wrapper 下。
        // wrapper 不滚动且与 pre 几何一致，标签绝对定位后即固定于代码块左上角。
        if (langName) {
            wrapper.insertBefore(langName, preElement);
        }

        // 复制按钮可选
        if (!CONFIG.showCopyButton) return;

        // 创建复制按钮
        const button = document.createElement('button');
        button.className = 'copy-button';
        button.innerText = '复制';
        button.setAttribute('aria-label', '复制代码');

        button.addEventListener('click', function(event) {
            event.stopPropagation();
            event.preventDefault();
            handleCodeCopy(this);
        });

        wrapper.appendChild(button);
    }

    /**
     * 复制按钮点击处理
     */
    async function handleCodeCopy(button) {
        const codeBlock = button.parentElement.querySelector('code');
        if (!codeBlock) return;

        // 获取代码文本
        const code = codeBlock.textContent;

        // 保存当前滚动位置
        const scrollY = window.scrollY;

        try {
            let success = false;

            // 优先使用 Clipboard API
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(code);
                success = true;
            } else {
                // 使用 execCommand 作为后备（HTTP 环境）
                const textArea = document.createElement('textarea');
                textArea.value = code;
                textArea.style.position = 'fixed';
                textArea.style.top = '0';
                textArea.style.left = '-9999px';
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();

                try {
                    document.execCommand('copy');
                    success = true;
                } catch (err) {
                    console.error('复制文本失败:', err);
                }

                document.body.removeChild(textArea);
            }

            if (success) {
                const originalText = button.innerText;
                button.innerText = '已复制!';
                button.classList.add('copied');

                setTimeout(() => {
                    button.innerText = originalText;
                    button.classList.remove('copied');
                }, 2000);
            } else {
                alert('复制失败，请重试');
            }
        } catch (err) {
            console.error('复制文本失败:', err);
            alert('复制文本失败，请重试');
        }

        // 恢复滚动位置
        window.scrollTo(0, scrollY);
    }

    /**
     * 处理单个代码块
     */
    function processCodeBlock(preElement) {
        if (!preElement || preElement.dataset.processed === 'yes') return;

        const codeBlock = preElement.querySelector('code');
        if (!codeBlock) return;

        try {
            // 添加复制按钮（行号已由后端处理）
            addCopyButton(preElement);

            // 标记为已处理
            preElement.dataset.processed = 'yes';
        } catch (e) {
            console.error('[Highlight] 处理代码块失败:', e);
        }
    }

    /**
     * 处理所有代码块
     */
    function processAllCodeBlocks() {
        const codeBlocks = document.querySelectorAll('pre code');
        if (codeBlocks.length === 0) return;

        codeBlocks.forEach(function(codeBlock) {
            if (codeBlock.isConnected) {
                const preElement = codeBlock.parentElement;
                if (preElement && preElement.tagName === 'PRE') {
                    processCodeBlock(preElement);
                }
            }
        });
    }

    /**
     * 初始化
     */
    function init() {
        processAllCodeBlocks();
    }

    /**
     * 重新初始化（用于 PJAX/Swup 页面切换）
     */
    function reinit() {
        processAllCodeBlocks();
    }

    // 导出到全局
    window.Highlight = {
        init,
        reinit,
        config: CONFIG
    };

    // 初始化 MutationObserver
    function initObserver() {
        const observer = new MutationObserver(function(mutations) {
            let shouldProcess = false;

            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length > 0) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) {
                            if (node.tagName === 'PRE' && node.querySelector('code')) {
                                shouldProcess = true;
                            } else if (node.querySelectorAll) {
                                const pres = node.querySelectorAll('pre code');
                                if (pres.length > 0) {
                                    shouldProcess = true;
                                }
                            }
                        }
                    });
                }
            });

            if (shouldProcess) {
                processAllCodeBlocks();
            }
        });

        // 开始观察
        if (document.body) {
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
    }

    // 页面加载完成后初始化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            init();
            initObserver();
        });
    } else {
        init();
        initObserver();
    }

    // 监听 Swup 页面切换事件
    document.addEventListener('swup:contentReplaced', reinit);

    // 监听 PJAX 完成事件（如果使用 PJAX）
    document.addEventListener('pjax:complete', reinit);

})();
