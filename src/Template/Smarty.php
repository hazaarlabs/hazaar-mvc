<?php

declare(strict_types=1);

namespace Hazaar\Template;

use Hazaar\File;
use Hazaar\Template\Smarty\Compiler;
use Hazaar\Template\Smarty\Enum\RendererType;

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
    public string $ldelim = '{';
    public string $rdelim = '}';
    public ?string $sourceFile = null;
    public ?string $cwd = null;

    /**
     * @var array<mixed>
     */
    public array $functions = [];

    /**
     * @var array<string>
     */
    protected array $includeFuncs = [];

    /**
     * @var array<mixed>
     */
    protected array $customFunctions = [];

    /**
     * @var array<string>
     */
    protected string $content = '';
    protected string $compiledContent = '';

    /**
     * @var array<string>
     */
    protected array $includes = [];
    private RendererType $rendererType = RendererType::AUTO;

    /**
     * @var array<object>
     */
    private array $customFunctionHandlers = [];

    /**
     * @var array<\Closure>
     */
    private array $filters = [];

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
        $this->compiledContent = '';
        $this->rendererType = RendererType::MEMORY;
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
        $this->compiledContent = '';
        $this->rendererType = RendererType::FILE;
    }

    public function registerFunctionHandler(object $object): void
    {
        $this->customFunctionHandlers[] = $object;
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
        if (!$this->compiledContent) {
            $compiler = new Compiler();
            $this->compiledContent = $compiler->exec($this->content);
        }
        $renderer = match ($this->rendererType) {
            RendererType::MEMORY => new Smarty\Renderer\Memory($this->compiledContent, $this->customFunctions, $this->includeFuncs),
            RendererType::FILE => new Smarty\Renderer\File($this->compiledContent, $this->customFunctions, $this->includeFuncs),
            default => throw new Exception\SmartyRendererTypeNotSupported($this->rendererType),
        };
        $content = $renderer->exec($params);
        if (count($this->filters) > 0) {
            foreach ($this->filters as $filter) {
                $content = $filter($content);
            }
        }

        return $content;
    }

    /**
     * Compile the template ready for rendering.
     *
     * This will normally happen automatically when calling Hazaar\Template\Smarty::render() but can be called
     * separately if needed.  The compiled template content is returned and can be stored externally.
     */
    public function compile(): string
    {
        if ($this->compiledContent) {
            return $this->compiledContent;
        }
        $compiler = new Compiler($this->ldelim, $this->rdelim);

        return $this->compiledContent = $compiler->exec($this->content);
    }
}
