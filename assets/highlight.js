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

        // 是否渲染行号（后端以 class 标记）
        const hasLineNumbers = !!preElement.querySelector('code.code-block-extension-code-show-num');

        // 无标签、无需复制按钮且无行号时，不做包装，保持 DOM 简洁
        if (!langName && !CONFIG.showCopyButton && !hasLineNumbers) return;

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

        // 构建独立的行号列（位于 pre 之外，固定在代码块左侧，不随横向滚动位移）
        buildLineNumbers(preElement);

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
     * 构建独立行号列，使各行行号作为一个整体固定在代码块左侧
     *
     * pre 自身是横向滚动容器，其内部的行号会随代码左右移动。这里把行号
     * 抽取到 pre 之外的 .code-block-linenums 列中，交由不滚动的 wrapper
     * 承载；pre 只负责代码区的横向滚动，行号因而始终固定于左侧。
     *
     * 本函数要求调用前处于「未构建」状态：视口跨断点重排时须先经
     * teardownLineNumbers 还原内联样式，再回到这里按新的几何重新计算。
     */
    function buildLineNumbers(preElement) {
        const code = preElement.querySelector('code');
        if (!code || !code.classList.contains('code-block-extension-code-show-num')) return;

        const wrapper = preElement.parentElement;
        if (!wrapper || !wrapper.classList.contains('code-block-wrapper')) return;

        // 兜底：若已存在上一轮构建的行号列则先移除，保证每次都从干净的 DOM 开始
        const stale = wrapper.querySelector('.code-block-linenums');
        if (stale) stale.remove();

        const lines = code.querySelectorAll('.code-block-extension-code-line');
        if (lines.length === 0) return;

        const column = document.createElement('div');
        column.className = 'code-block-linenums';
        column.setAttribute('aria-hidden', 'true');

        // 行号栏宽度由后端内联在 code 上，列并非 code 后代、无法继承该变量，需复制过来
        const width = code.style.getPropertyValue('--highlight-line-num-width');
        if (width) {
            column.style.setProperty('--highlight-line-num-width', width.trim());
        }

        /*
         * 背景与圆角统一交给 wrapper：代码块背景原本渲染在 pre（或 code）上，
         * 而位于 pre 外部的行号列没有圆角，两者交界会出现生硬接缝。
         * 这里把主题背景色提取到 wrapper，再把 pre/code 背景置为透明，
         * 使行号列与代码区共用一个由 wrapper 绘制的连续圆角矩形。
         * 插件无从预知主题色值，故运行时读取实际计算值。
         */
        const isOpaqueColor = function (color) {
            return color && color !== 'transparent' && color !== 'rgba(0, 0, 0, 0)';
        };
        const preBackground = getComputedStyle(preElement).backgroundColor;
        const codeBackground = getComputedStyle(code).backgroundColor;
        if (isOpaqueColor(preBackground)) {
            wrapper.style.backgroundColor = preBackground;
        } else if (isOpaqueColor(codeBackground)) {
            wrapper.style.backgroundColor = codeBackground;
        }
        // 内联置透明，确保覆盖主题的 pre / pre code.hljs 背景规则
        preElement.style.background = 'transparent';
        code.style.background = 'transparent';

        // 边框同样上提：若主题给 pre 画了可见边框，留在 pre 上会是方角，
        // 与 wrapper 的圆角不吻合；转移到 wrapper 后圆角与边框自然贴合。
        const preBorderStyle = getComputedStyle(preElement);
        if ((parseFloat(preBorderStyle.borderTopWidth) || 0) > 0) {
            wrapper.style.border =
                preBorderStyle.borderTopWidth + ' ' +
                preBorderStyle.borderTopStyle + ' ' +
                preBorderStyle.borderTopColor;
            preElement.style.border = 'none';
        }

        /*
         * 字号、字体、行高一律取 pre 与代码行的实际计算值，不写死像素。
         * 行号列是 wrapper 的子元素，其 em 相对页面正文字号，且主题可能改动 pre 的
         * 字号或字体；若用固定值，行号行盒高度会与代码行不等，出现整体或逐行错位。
         * 行高取代码行的计算像素值（如 25.2px）设为绝对长度，子元素继承该长度时
         * 不会按自身较小字号重算，因此每个行号行盒与代码行高度完全相等。
         */
        const preStyle = getComputedStyle(preElement);
        const lineStyle = getComputedStyle(lines[0]);
        column.style.fontFamily = preStyle.fontFamily;
        column.style.fontSize = preStyle.fontSize;
        if (lineStyle.lineHeight && lineStyle.lineHeight !== 'normal') {
            column.style.lineHeight = lineStyle.lineHeight;
        }

        /*
         * 首行行号必须与首行代码顶点齐平。代码可能同时受两层内边距向下挤压：
         * pre 自身的内边距，以及 code 的内边距（例如 hljs 主题的
         * pre code.hljs{padding:1em} 优先级高于插件样式，实际覆盖生效）。
         * 若只复制 pre 的内边距，会漏掉 code 的内边距，行号便整体偏高约半行。
         * 因此直接按实际几何计算：列的上内边距 = 代码内容顶边相对 wrapper 的偏移。
         */
        const wrapperRect = wrapper.getBoundingClientRect();
        const codeRect = code.getBoundingClientRect();
        const codePaddingTop = parseFloat(getComputedStyle(code).paddingTop) || 0;
        /*
         * 基准必须是 wrapper 的内容区顶部，而不是外边框顶部：
         * 行号列是 wrapper 的子元素，其顶端从内容区起算，而 getBoundingClientRect()
         * 返回的是外边框顶部，二者相差 wrapper 自身的边框宽度。
         * 上面已把原本长在 pre 上的边框转移到 wrapper，若此处不扣除该边框，
         * 行号整体就会偏移约半行，表现为首行即与代码不齐。
         * 无边框（宽度为 0）时该修正项自然为 0，不影响原有对齐。
         */
        const wrapperBorderTop = parseFloat(getComputedStyle(wrapper).borderTopWidth) || 0;
        const offsetTop = codeRect.top - wrapperRect.top - wrapperBorderTop + codePaddingTop;
        if (offsetTop > 0) {
            column.style.paddingTop = offsetTop + 'px';
        }

        /*
         * 行号水平位置还原：行号原在 code 内部，其左侧叠有 pre 与 code 两级内边距，
         * 外置到 pre 之外的列后只保留了相当于 pre 那一层的缩进，漏掉 code 自身的
         * 左内边距，导致行号整体偏左约两个数字宽。这里补回第二级缩进，使行号水平
         * 位置与原实现一致；并把 code 的左内边距清零，避免它转而成为行号与代码之间
         * 的额外空隙。此处 pre 尚未添加 has-linenums 类（该标记在本函数末尾才加），
         * 故此刻读到的 paddingLeft 仍是原始值，可放心作为基准。
         */
        const prePaddingLeft = parseFloat(getComputedStyle(preElement).paddingLeft) || 0;
        const codePaddingLeft = parseFloat(getComputedStyle(code).paddingLeft) || 0;
        /*
         * 在还原出的缩进基础上整体左收一个字符宽（1ch）：行号列左边缘固定在
         * wrapper 左侧无法再左移，故通过缩小左缩进把列的右边界一并左收，
         * 使代码区多出一个字符的可用宽度，减少挤占；右内边距不变，
         * 行号与代码的间距观感维持原样。
         */
        const basePaddingLeft = prePaddingLeft + codePaddingLeft;
        column.style.paddingLeft = 'calc(' + basePaddingLeft + 'px - 1ch)';
        code.style.paddingLeft = '0';

        /*
         * 代码与容器右缘的间距由每一行的 padding-right 提供（见 CSS）。
         * 这里把 code 宽度收缩到实际内容宽度：满宽 block 会让行盒宽于内容，
         * 行内边距可能落在溢出内容左侧而失效；贴合内容后内边距必然跟在最长行之后。
         * 用内联样式设置，优先级高于主题的 pre code.hljs 规则。
         */
        code.style.width = 'max-content';

        lines.forEach(function (line) {
            const num = document.createElement('span');
            num.textContent = line.getAttribute('data-line-num') || '';
            column.appendChild(num);
        });

        wrapper.insertBefore(column, preElement);

        /*
         * 分割线只覆盖行号所在的高度：顶端自列顶下移首行之前的偏移，
         * 高度取全部行号行盒之和，使线底恰好落在末行行号底缘、贴近行号。
         * 不能用 bottom 相对列底定位：行号列会被拉伸到容器高度，列底往往远低于
         * 末行行号，以列底为基准会让线延伸进下方空白，显得离行号过远。
         */
        const lineHeightPx = parseFloat(lineStyle.lineHeight) || 0;
        column.style.setProperty('--linenum-line-top', (offsetTop > 0 ? offsetTop : 0) + 'px');
        if (lineHeightPx > 0) {
            column.style.setProperty('--linenum-line-height', (lineHeightPx * lines.length) + 'px');
        }

        /*
         * 标记：隐藏 code 内原有的 ::before 行号（has-linenums-col），
         * 并让 pre 让出左侧内边距给行号列（has-linenums）。
         * 这两个类同时作为「已构建」的判据，供 teardownLineNumbers 幂等移除。
         */
        code.classList.add('has-linenums-col');
        preElement.classList.add('has-linenums');
    }

    /**
     * 拆除行号列并还原被内联样式覆盖的几何，使其可被重新构建。
     *
     * 视口跨过响应式断点时，样式表会改变 pre 的内边距与字号，但行号列的关键
     * 几何（字号、行高、上下内边距、分割线位置）都是构建时按像素内联写入的，
     * 不会随样式表更新；而样式表对 pre 的内边距/字号又会覆盖构建值，二者叠加
     * 会让行号与代码逐步脱开。这里把内联样式交还给样式表、并移除行号列，
     * 再由 buildLineNumbers 按当前视口重新测量、重建。
     */
    function teardownLineNumbers(preElement) {
        const code = preElement.querySelector('code');
        const wrapper = preElement.parentElement;

        // 移除行号列，分割线随列（::after 伪元素）一并消失
        if (wrapper && wrapper.classList.contains('code-block-wrapper')) {
            const column = wrapper.querySelector('.code-block-linenums');
            if (column) column.remove();

            wrapper.style.backgroundColor = '';
            wrapper.style.border = '';
        }

        // 还原标记类：无 JS 回退行号复现；clear 后按样式表原值重新布局
        if (code) {
            code.classList.remove('has-linenums-col');
            code.style.background = '';
            code.style.width = '';
            code.style.paddingLeft = '';
        }

        preElement.classList.remove('has-linenums');
        preElement.style.background = '';
        preElement.style.border = '';
    }

    /**
     * 视口跨响应式断点时重排所有行号列。
     *
     * 行号列的对齐依赖构建时测得的几何，而断点会改变 pre 的字号与内边距；
     * 此处对已构建的代码块先拆除内联覆盖、再按新视口重建。仅作用于标记为
     * has-linenums 的 pre，避免触碰本就读不到行号的代码块。
     */
    function refreshLineNumberLayout() {
        document.querySelectorAll('pre.has-linenums').forEach(function(preElement) {
            if (!preElement.isConnected) return;
            teardownLineNumbers(preElement);
            buildLineNumbers(preElement);
        });
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

    /*
     * 视口跨过响应式断点时重排行号列。
     * 用 matchMedia 而非 resize：断点值需与样式表的 @media (max-width: 640px)
     * 保持一致，且只在真正跨越断点时触发一次，拖动窗口大小不会反复重排。
     * addEventListener('change') 在旧内核可能缺失，故用 addListener 兜底。
     */
    const narrowViewport = window.matchMedia('(max-width: 640px)');
    const onViewportBreakpointChange = function() {
        refreshLineNumberLayout();
    };
    if (typeof narrowViewport.addEventListener === 'function') {
        narrowViewport.addEventListener('change', onViewportBreakpointChange);
    } else if (typeof narrowViewport.addListener === 'function') {
        narrowViewport.addListener(onViewportBreakpointChange);
    }

    // 监听 Swup 页面切换事件
    document.addEventListener('swup:contentReplaced', reinit);

    // 监听 PJAX 完成事件（如果使用 PJAX）
    document.addEventListener('pjax:complete', reinit);

})();
