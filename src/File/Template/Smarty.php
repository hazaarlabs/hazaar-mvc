<?php

declare(strict_types=1);

namespace Hazaar\File\Template;

use Hazaar\Application;
use Hazaar\File;
use Hazaar\File\Dir;
use Hazaar\File\Template\Exception\SmartyRenderException;

/**
 * The View\Template class.
 */
class Smarty extends \Hazaar\Template\Smarty
{
    public static bool $cache_enabled = true;
    private bool $__cache_enabled = false;
    private ?File $__source_file = null;
    private ?File $__cache_file = null;
    private string $__cwd;

    public function __construct(string $file, ?bool $cache_enabled = null)
    {
        $this->loadFromFile($file);
        if (null === $cache_enabled) {
            $cache_enabled = Smarty::$cache_enabled;
        }
        $this->__cache_enabled = $cache_enabled;
        parent::$tags[] = 'config_load';
    }

    public function __destruct()
    {
        if ($this->__cache_file) {
            $header = "@{$this->__source_file}";
            if (count($this->__includes) > 0) {
                foreach ($this->__includes as &$include) {
                    $include = $this->__source_file->relativepath($include);
                }
                $header .= ';'.implode(';', $this->__includes);
            }
            $this->__cache_file->putContents($header."\n".$this->__compiled_content);
        }
    }

    public function loadFromFile(File|string $file): void
    {
        if (!$file instanceof File) {
            $file = new File($file);
        }
        if (!$file->exists()) {
            throw new \Exception('Template file not found!');
        }
        $this->__source_file = $file;
        $this->__cwd = $file->dirname();
    }

    public function compile(): string
    {
        $this->__cache_file = null;
        if (!$this->__source_file instanceof File) {
            throw new \Exception('Template compilation failed! No source file or template content has been loaded!');
        }
        if ($this->__cache_enabled) {
            if ($content = $this->getCompiledContentFromCache()) {
                return $content;
            }
        }
        $cwd = getcwd();
        chdir($this->__cwd);
        $this->__content = $this->__source_file->getContents();
        $this->__compiled_content = parent::compile();
        chdir($cwd);

        return $this->__compiled_content;
    }

    /**
     * @param array<mixed> $params
     */
    public function render(array $params = []): string
    {
        try {
            $out = parent::render($params);
        } catch (\Throwable $e) {
            $this->__cache_file = null;
            $line = ($e->getLine() - 22);

            throw new SmartyRenderException($e->getMessage(), $this->__source_file, $line, $e);
        }

        return $out;
    }

    /**
     * Compile the CONFIG_LOAD tag.
     */
    public function compileCONFIG_LOAD(string $params): string
    {
        $params = $this->parsePARAMS($params);
        if (!array_key_exists('file', $params)) {
            return '';
        }
        $file = $this->compilePARAMS($params['file']);
        $code = '<?php ';
        if (array_key_exists('section', $params)) {
            $section = $this->compilePARAMS($params['section']);
            $code .= '@$new_variables = parse_ini_file('.$file.', true); if($new_variables && array_key_exists('.$section.', $new_variables)) $this->variables = array_merge($this->variables, $new_variables['.$section.']);';
        } else {
            $code .= '@$this->variables = array_merge($this->variables, parse_ini_file('.$file.'));';
        }
        $code .= '?>';

        return $code;
    }

    private function getCompiledContentFromCache(): false|string
    {
        $cache_id = md5($this->__source_file->fullpath());
        $cache_dir = new Dir(Application::getInstance()->runtimePath('template_cache', true));
        $this->__cache_file = $cache_dir->get($cache_id.'.tpl');
        if (!$this->__cache_file->exists()) {
            return false;
        }
        $this->__cache_file->open('rw');
        if (!(($header = $this->__cache_file->gets()) && '@' === $header[0])) {
            return false;
        }
        $parts = explode(';', trim(substr($header, 1)));
        // Check that the header references the actual source file and that is hasn't been modified
        if ($parts[0] !== $this->__source_file->fullpath()
            || $this->__cache_file->mtime() <= $this->__source_file->mtime()) {
            return false;
        }
        // Check that all files used have not been modified since the cache file was created
        for ($i = 1; $i < count($parts); ++$i) {
            $path = $this->__source_file->parent()->get($parts[$i]);
            // If the file doesn't exists or has changed, don't load cached content
            if (!($path->exists() && $this->__cache_file->mtime() > $path->mtime())) {
                return false;
            }
        }
        $content = $this->__cache_file->getContents(strlen($header));
        // Unset the cache file so we don't re-cache everything
        $this->__cache_file = null;

        return $content;
    }
}
