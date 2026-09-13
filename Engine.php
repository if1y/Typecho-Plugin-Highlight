<?php

namespace TypechoPlugin\Highlight;

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * highlight.php 代码高亮引擎（单例）
 *
 * 插件仅使用 highlight.php 一种引擎，因此不再拆分工厂与接口，
 * 统一在此类中完成初始化、高亮与 class 生成。
 */
class Engine
{
    /**
     * @var Engine|null 单例实例
     */
    private static $instance = null;

    /**
     * @var bool 是否已完成底层库初始化
     */
    private static $initialized = false;

    private function __construct()
    {
    }

    /**
     * 获取单例实例
     * @return Engine
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 初始化底层 highlight.php 库
     */
    public function init()
    {
        if (!self::$initialized) {
            require_once __DIR__ . '/vendor/Autoloader.php';
            spl_autoload_register('\\Highlight\\Autoloader::load');
            \Highlight\Highlighter::registerAllLanguages();
            self::$initialized = true;
        }
    }

    /**
     * 检查 code 元素是否已高亮
     * @param string $classAttr code 元素的 class 属性
     * @return bool
     */
    public function isHighlighted($classAttr)
    {
        return strpos($classAttr, 'hljs') !== false;
    }

    /**
     * 执行代码高亮
     * @param string $code 代码内容
     * @param string|null $language 语言标识
     * @return array [高亮后的 HTML（仅包含 code 内部）, 实际生效的语言标识]
     */
    public function highlight($code, $language)
    {
        $highlighter = new \Highlight\Highlighter(false);
        $highlighter->setClassPrefix('hljs-');
        $highlighter->setTabReplace('    ');

        try {
            if ($language) {
                // 转为小写以提高兼容性（如 C -> c, PHP -> php）
                $result = $highlighter->highlight(strtolower($language), $code);
            } else {
                $result = $highlighter->highlightAuto($code);
            }
        } catch (\DomainException $e) {
            // 语言不支持时回退到自动检测
            $result = $highlighter->highlightAuto($code);
        }

        // 自动检测时 language 为识别出的语言标识（可能是别名），供展示语法名称使用
        return [$result->value, $result->language];
    }

    /**
     * 获取 code 元素的 class 属性
     * @param string|null $language 检测到的语言
     * @return string
     */
    public function getCodeClass($language)
    {
        $classes = 'hljs';
        if ($language) {
            $classes .= ' language-' . $language;
        }
        return $classes;
    }
}
