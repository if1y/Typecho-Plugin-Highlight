<?php

namespace TypechoPlugin\Highlight\Engine;

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * highlight.php 引擎实现
 */
class HighlightPhpEngine implements EngineInterface
{
    private static $instance = null;

    private function __construct()
    {
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private static $initialized = false;

    public function init()
    {
        if (!self::$initialized) {
            require_once __DIR__ . '/../vendor/Highlight/Autoloader.php';
            spl_autoload_register('\\Highlight\\Autoloader::load');
            \Highlight\Highlighter::registerAllLanguages();
            self::$initialized = true;
        }
    }

    public function isHighlighted($classAttr)
    {
        return strpos($classAttr, 'hljs') !== false;
    }

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

        return $result->value;
    }

    public function getCodeClass($language)
    {
        $classes = 'hljs';
        if ($language) {
            $classes .= ' language-' . $language;
        }
        return $classes;
    }

    public function getEngineName()
    {
        return 'highlight.php';
    }
}
