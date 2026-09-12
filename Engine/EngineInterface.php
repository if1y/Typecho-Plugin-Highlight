<?php

namespace TypechoPlugin\Highlight\Engine;

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 代码高亮引擎接口
 */
interface EngineInterface
{
    /**
     * 初始化引擎
     */
    public function init();

    /**
     * 检查是否已高亮
     * @param string $classAttr code 元素的 class 属性
     * @return bool
     */
    public function isHighlighted($classAttr);

    /**
     * 执行代码高亮
     * @param string $code 代码内容
     * @param string|null $language 语言标识
     * @return string 高亮后的 HTML（仅包含 code 内部）
     */
    public function highlight($code, $language);

    /**
     * 获取 code 元素的 class 属性
     * @param string|null $language 检测到的语言
     * @return string
     */
    public function getCodeClass($language);

    /**
     * 获取引擎标识
     * @return string
     */
    public function getEngineName();
}
