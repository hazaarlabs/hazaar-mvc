<?php

declare(strict_types=1);

namespace Hazaar\Template;

use Hazaar\Application;
use Hazaar\Application\Runtime;
use Hazaar\File;
use Hazaar\Template\Exception\SmartyTemplateError;
use Hazaar\Template\Smarty\Compiler;

/**
 * Smarty 2.0 Templates.
 *
 * This class implements the entire Smarty 2.0 template specification.  For documentation on the
 * Smarty 2.0 template format see the Smarty 2.0 online documentation: https://www.smarty.net/docsv2/en/
 *
 * Tags are in the format of {$tagname}.  This tag would reference a parameter passed to the parser
 * with the array key value of 'tagname'.  Such as:
 *
 * ```
 * $tpl = new \Hazaar\Template\Smarty($templateContent);
 * $tpl->render(['tagname' => 'Hello, World!']);
 * ```
 */
class Smarty
{
    public static string $templatePrefix = '_smarty_template_';
    public ?string $sourceFile = null;
    public bool $allowGlobals = true;

    /**
     * @var array<mixed>
     */
    public array $functions = [];

    public Compiler $compiler;

    /**
     * @var array<mixed>
     */
    protected array $customFunctions = [];

    protected string $content = '';

    /**
     * @var array<string>
     */
    protected array $includes = [];

    /**
     * @var array<\Closure>
     */
    private array $filters = [];

    /**
     * @var array<mixed>
     */
    private array $functionHandlers = [];

    /**
     * Create a new Smarty template object.
     */
    public function __construct(?string $content = null, ?Compiler $compiler = null)
    {
        $this->compiler = $compiler ?? new Compiler();
        if (!empty($content)) {
            $this->loadFromString($content);
        }
    }

    /**
     * Load the SMARTy template from a supplied string.
     *
     * @param string $content The template source code
     */
    public function loadFromString(string $content): void
    {
        $this->content = (string) $content;
        $this->compiler->reset();
    }

    /**
     * Read the template from a file.
     *
     * @param File $file can be either a Hazaar\File object or a string to a file on disk
     */
    public function loadFromFile(File $file): void
    {
        if (!$file->exists()) {
            throw new Exception\IncludeFileNotFound($file->fullpath());
        }
        $this->sourceFile = $file->fullpath();
        $this->content = $file->getContents();
        $this->compiler->reset();
        $this->compiler->setCWD($file->dirname());
    }

    /**
     * Register a custom function with the template.
     *
     * Custom functions are functions that can be called from within the template.  The function must be
     * defined in the template and can be called using the syntax:
     *
     * ```
     * {$functionName param1="value" param2="value"}
     * ```
     *
     * The function will be called with the parameters as an array.  The function must return a string
     * which will be inserted into the template at the point the function was called.
     */
    public function registerFunction(string $functionName, callable $callback): void
    {
        $this->customFunctions[$functionName] = $callback;
    }

    /**
     * Register a custom function handler.
     *
     * Customer function handlers are objects that can be used to handle custom functions in the template.  A custom
     * function is a function that is not built-in and is defined in the template and can be called using the syntax:
     *
     * ```
     * {$functionName param1="value" param2="value"}
     * ```
     *
     * You can register multiple custom function handlers.  The first handler that contains a method with the same
     * name as the function will be used to handle the function.
     */
    public function registerFunctionHandler(mixed $handler): void
    {
        $this->functionHandlers[] = $handler;
    }

    /**
     * Returns the original un-compiled template.
     */
    public function getTemplate(): string
    {
        return $this->content;
    }

    /**
     * Retrieves the template file path.
     *
     * @return null|string the path to the template file, or null if not set
     */
    public function getTemplateFile(): ?string
    {
        return $this->sourceFile;
    }

    /**
     * Prepend a string to the existing content.
     */
    public function prepend(string $string): void
    {
        $this->content = $string.$this->content;
    }

    /**
     * Append a string to the existing content.
     */
    public function append(string $string): void
    {
        $this->content .= $string;
    }

    /**
     * Add a post-processing filter to the template.
     *
     * Filters are applied after the template has been rendered and can be used to modify the output.  Useful for
     * things like minifying the output or removing whitespace.
     */
    public function addFilter(\Closure $filter): void
    {
        $this->filters[] = $filter;
    }

    /**
     * Renders the stored template with the supplied parameters and return the rendered content.
     *
     * This method processes the template using the Smarty templating engine, merging
     * default parameters with the provided parameters.
     *
     * Internally, it calls the `renderString` method to handle the rendering process, passing
     * the template content and parameters. The rendered content is then returned as a string.
     *
     * @param array<mixed> $params parameters to use when embedding variables in the rendered template
     */
    public function render(array $params = []): string
    {
        return $this->renderString($this->content, $params);
    }

    /**
     * Renders a string template with the provided parameters.
     *
     * This method processes a string template using the Smarty templating engine.
     * It merges default parameters with the provided parameters, prepares the
     * renderer class, and executes the rendering process. Additionally, it applies
     * any registered filters to the rendered content.
     *
     * @param string $content the string template content to be rendered
     * @param array  $params  An associative array of parameters to be passed to the template.
     *                        These parameters will be merged with default parameters.
     *                        Special handling is applied if a key named '*' exists.
     *
     * @return string the rendered content after processing the template and applying filters
     *
     * @throws \SmartyTemplateError if an error occurs during the rendering process
     */
    public function renderString(string $content, array $params = []): string
    {
        $app = Application::getInstance();
        $defaultParams = [
            'hazaar' => ['version' => HAZAAR_VERSION],
            'application' => $app ?? null,
            'now' => time(),
            'smarty' => [
                'capture' => [],
                'section' => [],
                'foreach' => [],
                'template' => null,
                'version' => 2,
            ],
        ];
        $renderParameters = array_merge($defaultParams, (array) $params);
        if (array_key_exists('*', $renderParameters)) {
            $renderParameters['__DEFAULT_VAR__'] = $renderParameters['*'];
            unset($params['*']);
        } else {
            $renderParameters['__DEFAULT_VAR__'] = '';
        }

        try {
            $templateId = $this->prepareRendererClass($content);
            $obj = new $templateId();
            $obj->functionHandlers = $this->functionHandlers;
            $obj->functions = $this->customFunctions;
            $content = $obj->render($renderParameters);
            // Merge the functions from the included templates
            $this->functions = array_merge($this->functions, $obj->functions);
        } catch (\Throwable $e) {
            throw new SmartyTemplateError($e);
        } finally {
            error_clear_last();
        }
        if (count($this->filters) > 0) {
            foreach ($this->filters as $filter) {
                $content = $filter($content);
            }
        }

        return $content;
    }

    /**
     * Prepare the renderer class.
     */
    private function prepareRendererClass(?string $content = null): string
    {
        $app = Application::getInstance();
        if (null !== $content || !($this->sourceFile && $app instanceof Application)) {
            return $this->prepareEvalRenderer($content);
        }

        return $this->preparePHPRenderer();
    }

    private function prepareEvalRenderer(string $content): string
    {
        $templateId = self::$templatePrefix.md5(uniqid());
        if (class_exists($templateId)) {
            return $templateId;
        }
        if (!$this->compiler->isCompiled()) {
            $this->compiler->exec($content);
        }
        $code = $this->compiler->getCode($templateId);
        eval($code);

        return $templateId;
    }

    private function preparePHPRenderer(): string
    {
        $templateId = self::$templatePrefix.md5($this->sourceFile);
        if (class_exists($templateId)) {
            return $templateId;
        }
        $templatePath = Runtime::getInstance()->getPath('templates', true);
        $templateFile = $templatePath.DIRECTORY_SEPARATOR.$templateId.'.php';
        /*
         * Watch the source file and the renderer files for changes
         * We need to watch the compiler and renderer class files as well because they are referenced
         * in the compiled template so if they change we need to recompile the template.
         */
        $watchFiles = [
            $this->sourceFile,
            __FILE__,
            __DIR__.DIRECTORY_SEPARATOR.'Smarty'.DIRECTORY_SEPARATOR.'Compiler.php',
            __DIR__.DIRECTORY_SEPARATOR.'Smarty'.DIRECTORY_SEPARATOR.'Renderer.php',
        ];
        if (!file_exists($templateFile) || $this->checkFilesChanged($watchFiles, filemtime($templateFile))) {
            if (!$this->compiler->isCompiled()) {
                $this->compiler->exec($this->content);
            }
            $code = $this->compiler->getCode($templateId);
            file_put_contents($templateFile, "<?php\n".$code);
        }

        include_once $templateFile;

        return $templateId;
    }

    /**
     * Check if any of the files have changed sinace the template was last compiled.
     *
     * @param array<string> $watchFiles The files to check for changes
     * @param int           $timestamp  The timestamp of the template file
     */
    private function checkFilesChanged(array $watchFiles, int $timestamp): bool
    {
        foreach ($watchFiles as $file) {
            if (filemtime($file) > $timestamp) {
                return true;
            }
        }

        return false;
    }
}
