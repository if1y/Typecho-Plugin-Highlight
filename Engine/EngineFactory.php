<?php

namespace TypechoPlugin\Highlight\Engine;

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 引擎工厂
 */
class EngineFactory
{
    const ENGINE_HIGHLIGHT_PHP = 'highlight.php';
    const ENGINE_PHIKI = 'phiki';

    private static $engine = null;
    private static $config = [
        'engine' => self::ENGINE_HIGHLIGHT_PHP,  // 默认引擎
        'phiki_theme' => 'github-light',         // Phiki 主题
    ];

    /**
     * 设置引擎配置
     * @param array $config
     */
    public static function setConfig(array $config)
    {
        self::$config = array_merge(self::$config, $config);
        self::$engine = null;  // 重置引擎实例
    }

    /**
     * 获取当前配置
     * @return array
     */
    public static function getConfig()
    {
        return self::$config;
    }

    /**
     * 获取当前引擎
     * @return EngineInterface
     */
    public static function getEngine()
    {
        if (self::$engine === null) {
            $engineType = self::$config['engine'];

            switch ($engineType) {
                case self::ENGINE_PHIKI:
                    self::$engine = PhikiEngine::getInstance();
                    break;
                case self::ENGINE_HIGHLIGHT_PHP:
                default:
                    self::$engine = HighlightPhpEngine::getInstance();
                    break;
            }

            self::$engine->init();
        }

        return self::$engine;
    }

    /**
     * 获取当前引擎类型
     * @return string
     */
    public static function getEngineType()
    {
        return self::$config['engine'];
    }
}
