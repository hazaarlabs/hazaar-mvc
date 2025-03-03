<?php

declare(strict_types=1);

namespace Hazaar\Template;

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
 * $tpl = new \Hazaar\Template\Smarty($template_content);
 * $tpl->render(['tagname' => 'Hello, World!']);
 * ```
 */
class Smarty
{
    public ?string $sourceFile = null;
    public ?string $cwd = null;

    /**
     * @var array<mixed>
     */
    public array $functions = [];

    public Compiler $compiler;

    /**
     * @var array<string>
     */
    protected array $includeFuncs = [];

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
     *
     * @param array<mixed> $customFunctions
     * @param array<mixed> $includeFuncs
     */
    public function __construct(
        ?string $content = null,
        ?array $customFunctions = null,
        ?array $includeFuncs = null
    ) {
        $this->compiler = new Compiler();
        $this->customFunctions = $customFunctions ?? [];
        $this->includeFuncs = $includeFuncs ?? [];
        if ($content) {
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
        $this->cwd = $file->dirname();
        $this->content = $file->getContents();
        $this->compiler->reset();
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
     * Render the template with the supplied parameters and return the rendered content.
     *
     * @param array<mixed> $params parameters to use when embedding variables in the rendered template
     */
    public function render(array $params = []): string
    {
        if (!$this->compiler->isCompiled()) {
            $this->compiler->exec($this->content);
        }

        $id = '_template_'.md5(uniqid());
        $code = $this->compiler->getCode($id);

        $errors = error_reporting();
        error_reporting(0);

        try {
            eval($code);
            ob_start();
            $obj = new $id();
            $obj->functionHandlers = $this->functionHandlers;
            $obj->includeFuncs = $this->includeFuncs;
            $obj->functions = $this->customFunctions;
            $obj->render($params);
            // Merge the functions from the included templates
            $this->functions = array_merge($this->functions, $obj->functions);
        } catch (\Throwable $e) {
            throw new SmartyTemplateError($e);
        } finally {
            error_clear_last();
            error_reporting($errors);
        }
        $content = ob_get_clean();
        if (count($this->filters) > 0) {
            foreach ($this->filters as $filter) {
                $content = $filter($content);
            }
        }

        return $content;
    }
}
