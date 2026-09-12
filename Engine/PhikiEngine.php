<?php

namespace TypechoPlugin\Highlight\Engine;

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * Phiki 引擎实现
 */
class PhikiEngine implements EngineInterface
{
    private static $instance = null;
    private $phiki = null;
    private $theme = 'github-light';

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

    public function init()
    {
        if ($this->phiki === null) {
            // 从工厂配置读取主题
            require_once __DIR__ . '/EngineFactory.php';
            $config = EngineFactory::getConfig();
            $this->theme = $config['phiki_theme'] ?? 'github-light';

            // 预加载核心依赖文件（按依赖顺序）
            $baseDir = __DIR__ . '/../vendor/Phiki/src/';

            // 1. Support 基础类（必须最先加载）
            require_once $baseDir . 'Support/Arr.php';
            require_once $baseDir . 'Support/Str.php';
            require_once $baseDir . 'Support/Regex.php';

            // 2. Contracts 接口（必须在所有类之前加载）
            require_once $baseDir . 'Contracts/ThemeRepositoryInterface.php';
            require_once $baseDir . 'Contracts/GrammarRepositoryInterface.php';
            require_once $baseDir . 'Contracts/PatternInterface.php';
            require_once $baseDir . 'Contracts/ExtensionInterface.php';
            require_once $baseDir . 'Contracts/HasContentNameInterface.php';
            require_once $baseDir . 'Contracts/InjectionMatcherInterface.php';
            require_once $baseDir . 'Contracts/InjectionSelectorParserInputInterface.php';
            require_once $baseDir . 'Contracts/RequiresGrammarInterface.php';
            require_once $baseDir . 'Contracts/RequiresThemesInterface.php';
            require_once $baseDir . 'Contracts/TransformerInterface.php';

            // 3. Token 类
            require_once $baseDir . 'Token/Token.php';
            require_once $baseDir . 'Token/HighlightedToken.php';

            // 4. Theme 相关
            require_once $baseDir . 'Theme/TokenSettings.php';
            require_once $baseDir . 'Theme/TokenColor.php';
            require_once $baseDir . 'Theme/TokenColorMatchResult.php';
            require_once $baseDir . 'Theme/ScopeMatchResult.php';
            require_once $baseDir . 'Theme/Scope.php';
            require_once $baseDir . 'Theme/ParsedTheme.php';
            require_once $baseDir . 'Theme/Theme.php';
            require_once $baseDir . 'Theme/ThemeParser.php';
            require_once $baseDir . 'Theme/ThemeColorExtractor.php';
            require_once $baseDir . 'Theme/ThemeRepository.php';

            // 5. Exceptions 异常类
            require_once $baseDir . 'Exceptions/UnrecognisedGrammarException.php';
            require_once $baseDir . 'Exceptions/UnrecognisedThemeException.php';
            require_once $baseDir . 'Exceptions/MissingRequiredGrammarKeyException.php';
            require_once $baseDir . 'Exceptions/GenericPatternException.php';
            require_once $baseDir . 'Exceptions/FailedToInitializePatternSearchException.php';
            require_once $baseDir . 'Exceptions/FailedToSetSearchPositionException.php';

            // 6. Grammar 相关
            require_once $baseDir . 'Grammar/MatchedPattern.php';
            require_once $baseDir . 'Grammar/ParsedGrammar.php';
            require_once $baseDir . 'Grammar/GrammarParser.php';
            require_once $baseDir . 'Grammar/Grammar.php';
            require_once $baseDir . 'Grammar/GrammarRepository.php';
            require_once $baseDir . 'Grammar/BeginEndPattern.php';
            require_once $baseDir . 'Grammar/BeginWhilePattern.php';
            require_once $baseDir . 'Grammar/EndPattern.php';
            require_once $baseDir . 'Grammar/WhilePattern.php';
            require_once $baseDir . 'Grammar/MatchPattern.php';
            require_once $baseDir . 'Grammar/Capture.php';
            require_once $baseDir . 'Grammar/IncludePattern.php';
            require_once $baseDir . 'Grammar/CollectionPattern.php';
            require_once $baseDir . 'Grammar/Injections/Injection.php';
            require_once $baseDir . 'Grammar/Injections/Prefix.php';
            require_once $baseDir . 'Grammar/Injections/Selector.php';
            require_once $baseDir . 'Grammar/Injections/Scope.php';
            require_once $baseDir . 'Grammar/Injections/Path.php';
            require_once $baseDir . 'Grammar/Injections/Expression.php';
            require_once $baseDir . 'Grammar/Injections/Group.php';
            require_once $baseDir . 'Grammar/Injections/Filter.php';
            require_once $baseDir . 'Grammar/Injections/Operator.php';
            require_once $baseDir . 'Grammar/Injections/Composite.php';
            require_once $baseDir . 'Grammar/MatchedInjection.php';

            // 7. TextMate 相关
            require_once $baseDir . 'TextMate/AttributedScopeStack.php';
            require_once $baseDir . 'TextMate/ScopeStack.php';
            require_once $baseDir . 'TextMate/StateStack.php';
            require_once $baseDir . 'TextMate/LocalStackElement.php';
            require_once $baseDir . 'TextMate/WhileStackElement.php';
            require_once $baseDir . 'TextMate/LineTokens.php';
            require_once $baseDir . 'TextMate/PatternSearcher.php';
            require_once $baseDir . 'TextMate/Tokenizer.php';

            // 8. Highlighting 相关
            require_once $baseDir . 'Highlighting/Highlighter.php';

            // 9. Phast 相关（HTML 生成）
            require_once $baseDir . 'Phast/ClassList.php';
            require_once $baseDir . 'Phast/Properties.php';
            require_once $baseDir . 'Phast/Literal.php';
            require_once $baseDir . 'Phast/Element.php';
            require_once $baseDir . 'Phast/Text.php';
            require_once $baseDir . 'Phast/Root.php';

            // 10. Output 相关
            require_once $baseDir . 'Output/Html/PendingHtmlOutput.php';

            // 11. Transformers 相关
            require_once $baseDir . 'Transformers/Meta.php';

            // 12. Environment
            require_once $baseDir . 'Environment.php';

            // 13. 主类
            require_once $baseDir . 'Phiki.php';

            // PSR-4 autoloading for Phiki（作为后备）
            spl_autoload_register(function ($class) {
                $prefix = 'Phiki\\';
                $base_dir = __DIR__ . '/../vendor/Phiki/src/';

                $len = strlen($prefix);
                if (strncmp($prefix, $class, $len) !== 0) {
                    return;
                }

                $relative_class = substr($class, $len);
                $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

                if (file_exists($file)) {
                    require $file;
                }
            });

            $this->phiki = new \Phiki\Phiki();
        }
    }

    public function isHighlighted($classAttr)
    {
        return strpos($classAttr, 'phiki') !== false;
    }

    public function highlight($code, $language)
    {
        if (!$language) {
            $language = 'plaintext';
        }

        try {
            return (string) $this->phiki->codeToHtml($code, $language, $this->theme);
        } catch (\Throwable $e) {
            return '<pre><code>' . htmlspecialchars($code) . '</code></pre>';
        }
    }

    public function getCodeClass($language)
    {
        // Phiki 使用内联样式，不需要 class
        return '';
    }

    public function getEngineName()
    {
        return 'phiki';
    }
}
