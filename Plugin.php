<?php

namespace TypechoPlugin\Highlight;

use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Select;
use Typecho\Widget\Helper\Form\Element\Checkbox;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * Typecho 服务器端代码高亮插件，采用后端渲染的方案，前端友好
 *
 * @package Highlight
 * @author WannαFly
 * @version 1.0.0
 * @link https://github.com/if1y/Typecho-Plugin-Highlight
 */
class Plugin implements PluginInterface
{
    /**
     * 激活插件方法
     */
    public static function activate()
    {
        // 文章内容高亮（文章页、独立页等）
        \Typecho\Plugin::factory('Widget_Abstract_Contents')->contentEx = __CLASS__ . '::highlight';
        // 评论内容高亮
        \Typecho\Plugin::factory('Widget_Abstract_Comments')->contentEx = __CLASS__ . '::highlight';

        // 自动输出 CSS 到 <head>
        \Typecho\Plugin::factory('Widget_Archive')->header = __CLASS__ . '::header';

        $message = '插件已激活，代码高亮功能已启用';

        return $message;
    }

    /**
     * 禁用插件方法
     */
    public static function deactivate()
    {
        // 引擎会自动清理
    }

    /**
     * 获取插件配置面板
     * @param Form $form
     */
    public static function config(Form $form)
    {
        // highlight.php 主题选择
        $hljsThemes = [
            'github' => 'GitHub',
            'github-dark' => 'GitHub Dark',
            'monokai' => 'Monokai',
            'dracula' => 'Dracula',
            'nord' => 'Nord',
            'atom-one-dark' => 'Atom One Dark',
            'atom-one-light' => 'Atom One Light',
            'vs' => 'VS Code',
            'vs2015' => 'VS Code 2015',
            'xcode' => 'Xcode',
            'solarized-light' => 'Solarized Light',
            'solarized-dark' => 'Solarized Dark',
            'papersu-code' => 'PaperSu'
        ];

        $hljsTheme = new Select(
            'hljsTheme',
            $hljsThemes,
            'github',
            'highlight.js 主题',
            '主题 CSS 会自动注入到页面 <head> 中'
        );
        $form->addInput($hljsTheme);

        // 行号显示
        $showLineNumbers = new Checkbox(
            'showLineNumbers',
            ['1' => '显示代码行号'],
            ['1'],
            '显示行号',
            '在代码块左侧显示行号'
        );
        $form->addInput($showLineNumbers);

        // 复制按钮
        $showCopyButton = new Checkbox(
            'showCopyButton',
            ['1' => '显示复制按钮'],
            ['1'],
            '显示复制按钮',
            '在代码块右上角添加复制按钮'
        );
        $form->addInput($showCopyButton);
    }

    /**
     * 个人用户的配置面板
     * @param Form $form
     */
    public static function personalConfig(Form $form)
    {
        // 当前不需要个人配置
    }

    /**
     * 代码高亮处理入口
     */
    public static function highlight($text, $widget, $lastResult)
    {
        $content = empty($lastResult) ? $text : $lastResult;

        // 快速失败：无 pre 标签
        if (strpos($content, '<pre') === false) {
            return $content;
        }

        // 加载引擎
        $engine = self::getEngine();
        return self::processContent($content, $engine);
    }

    /**
     * 获取当前主题的 CSS 文件 URL
     * @return string CSS 文件 URL
     */
    public static function getStylesheetUrl()
    {
        $options = Options::alloc();
        $pluginConfig = $options->plugin('Highlight');

        // 获取当前主题
        $theme = isset($pluginConfig->hljsTheme) ? $pluginConfig->hljsTheme : 'github';

        // 返回 CSS 文件 URL
        $options = Options::alloc();
        $pluginUrl = $options->pluginUrl . '/Highlight/vendor/themes/' . $theme . '.css';

        return $pluginUrl;
    }

    /**
     * 输出 CSS 链接标签到 <head>
     * 此方法通过钩子自动调用，无需手动添加
     */
    public static function header()
    {
        $options = Options::alloc();
        $pluginUrl = $options->pluginUrl . '/Highlight';
        $pluginConfig = $options->plugin('Highlight');

        // 输出插件样式
        echo '<link rel="stylesheet" href="' . htmlspecialchars($pluginUrl) . '/assets/highlight.css">' . "\n";

        // 输出高亮引擎样式（仅 highlight.php 需要）
        $cssUrl = self::getStylesheetUrl();
        if ($cssUrl) {
            echo '<link rel="stylesheet" href="' . htmlspecialchars($cssUrl) . '">' . "\n";
        }

        // 输出配置到 JavaScript
        $showCopyButton = isset($pluginConfig->showCopyButton) && in_array('1', (array)$pluginConfig->showCopyButton) ? 'true' : 'false';

        echo '<script>window.HIGHLIGHT_CONFIG = {showCopyButton:' . $showCopyButton . '};</script>' . "\n";

        // 输出 JavaScript 文件
        echo '<script src="' . htmlspecialchars($pluginUrl) . '/assets/highlight.js"></script>' . "\n";
    }

    /**
     * 获取引擎实例
     */
    private static function getEngine()
    {
        require_once __DIR__ . '/Engine.php';

        $engine = Engine::getInstance();
        $engine->init();

        return $engine;
    }

    /**
     * 处理内容，高亮所有代码块
     */
    private static function processContent($content, $engine)
    {
        // 使用正则提取并替换每个 <pre> 代码块，避免处理整个文档导致 HTML 实体被双重编码
        $content = preg_replace_callback(
            '/<pre(\s[^>]*)?>.*?<\/pre>/is',
            function($matches) use ($engine) {
                $preTag = $matches[0];

                // 使用 DOMDocument 处理单个 pre 块
                libxml_use_internal_errors(true);
                $dom = new \DOMDocument();
                $dom->loadHTML('<?xml encoding="UTF-8">' . $preTag, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                libxml_clear_errors();

                $pres = $dom->getElementsByTagName('pre');
                if ($pres->length === 0) {
                    return $preTag;
                }

                $pre = $pres->item(0);
                $code = self::findCodeElement($pre);
                if ($code === null) {
                    return $preTag;
                }

                // 幂等性：已高亮则跳过
                $classAttr = $code->getAttribute('class');
                if ($engine->isHighlighted($classAttr) ||
                    strpos($classAttr, 'hljs') !== false) {
                    return $preTag;
                }

                // 提取语言和代码
                $language = self::extractLanguage($code);
                $codeText = $code->textContent;

                // 调用引擎高亮
                $highlightedHtml = $engine->highlight($codeText, $language);

                // 获取插件配置
                $options = Options::alloc();
                $pluginConfig = $options->plugin('Highlight');
                $showLineNumbers = isset($pluginConfig->showLineNumbers) && in_array('1', (array)$pluginConfig->showLineNumbers);

                // highlight.php 只返回 code 内容，需要构建结构
                $newPre = $dom->createElement('pre');
                $newCode = $dom->createElement('code');
                $classes = $engine->getCodeClass($language);
                if ($showLineNumbers) {
                    $classes = trim($classes . ' code-block-extension-code-show-num');
                }
                $newCode->setAttribute('class', $classes);

                // 添加行号处理
                if ($showLineNumbers) {
                    [$processedHtml, $lineNumWidth] = self::addLineNumbersToHtmlForHighlight($highlightedHtml);
                    // 按最大行号位数固定行号栏宽度，避免多位数行号导致代码位移
                    $newCode->setAttribute('style', self::setLineNumberWidthStyle($lineNumWidth));
                } else {
                    $processedHtml = $highlightedHtml;
                }

                $codeFragment = $dom->createDocumentFragment();
                $codeFragment->appendXML($processedHtml);
                $newCode->appendChild($codeFragment);
                $newPre->appendChild($newCode);

                $result = $dom->saveHTML($newPre);

                // 清理 XML 声明
                return self::cleanHtml($result);
            },
            $content
        );

        return $content;
    }

    /**
     * 为 highlight.php 引擎的高亮 HTML 添加行号
     * @param string $html highlight.php 返回的代码 HTML
     * @return array [处理后的 HTML, 行号最大位数]
     */
    private static function addLineNumbersToHtmlForHighlight($html)
    {
        // highlight.php 引擎：按换行符分割并添加行号
        $lines = explode("\n", $html);
        // 过滤掉最后的空行
        $lines = array_filter($lines, function($line, $index) use ($lines) {
            if ($line === '') {
                return $index < count($lines) - 1;
            }
            return true;
        }, ARRAY_FILTER_USE_BOTH);

        $result = '';
        $lineNum = 1;
        foreach ($lines as $line) {
            $result .= '<span class="code-block-extension-code-line" data-line-num="' . $lineNum . '">' . $line . "</span>\n";
            $lineNum++;
        }

        return [rtrim($result), strlen((string)max(1, $lineNum - 1))];
    }

    /**
     * 生成行号栏宽度的内联样式
     *
     * 宽度按最大行号位数动态计算（2 位 -> 2ch，3 位 -> 3ch，4 位 -> 4ch …），
     * 最小宽度固定为 2ch：绝大多数代码块不超过 99 行，统一取下限可让
     * 各代码块行号栏宽度尽量一致、观感整齐；超过 99 行时按实际位数加宽，
     * 因此无论多少行都不会出现行号被截断。又因为宽度固定，行号由
     * 9 变 10、99 变 100 时代码左边缘不会发生位移。
     *
     * @param int $digits 该代码块行号的最大位数
     * @return string 形如 "--highlight-line-num-width:2ch"
     */
    private static function setLineNumberWidthStyle($digits)
    {
        return '--highlight-line-num-width:' . max(2, (int) $digits) . 'ch';
    }

    /**
     * 查找 pre 下的 code 元素
     */
    private static function findCodeElement(\DOMElement $pre)
    {
        foreach ($pre->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->tagName === 'code') {
                return $child;
            }
        }
        return null;
    }

    /**
     * 从 class 中提取语言
     */
    private static function extractLanguage(\DOMElement $code)
    {
        $class = $code->getAttribute('class');

        // 匹配 language-xxx 或 lang-xxx
        if (preg_match('/\b(?:language|lang)-([a-z0-9_+#-]+)\b/i', $class, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * 清理 DOMDocument 添加的多余内容
     */
    private static function cleanHtml($html)
    {
        // 移除 XML 声明（使用 str_replace 更可靠）
        $html = str_replace('<?xml encoding="UTF-8">', '', $html);
        $html = str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $html);

        // 移除可能的 DOCTYPE
        $html = preg_replace('/<!DOCTYPE[^>]*>/i', '', $html);

        // 移除 html 和 body 标签
        $html = preg_replace('/<\/?html[^>]*>/i', '', $html);
        $html = preg_replace('/<\/?body[^>]*>/i', '', $html);

        // 移除 head 标签（如果存在）
        $html = preg_replace('/<head[^>]*>.*?<\/head>/is', '', $html);

        return trim($html);
    }
}
