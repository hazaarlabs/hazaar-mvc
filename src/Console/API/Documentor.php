<?php

declare(strict_types=1);

namespace Hazaar\Console\API;

use Hazaar\File;
use Hazaar\Parser\PHP;
use Hazaar\Parser\PHP\ParserFile;
use Hazaar\Parser\PHP\ParserNamespace;
use Hazaar\Parser\PHP\TokenParser;
use Hazaar\Template\Smarty;
use Hazaar\Util\Timer;

class Documentor
{
    public const DOC_OUTPUT_HTML = 1;
    public const DOC_OUTPUT_MARKDOWN = 2;
    public const DOC_OUTPUT_INDEX = 3;

    private int $outputFormat;
    private ?string $title;
    private ?\Closure $callback = null;

    private ?\stdClass $index = null;

    private string $outputPath;

    private string $scanPath;

    public function __construct(int $outputFormat, ?string $title = null)
    {
        $this->outputFormat = $outputFormat;
        $this->title = $title;
        $this->scanPath = getcwd();
        $this->outputPath = getcwd();
    }

    public function setCallback(\Closure $callback): void
    {
        $this->callback = $callback;
    }

    public function setScanPath(string $scanPath): void
    {
        $this->scanPath = realpath($scanPath);
    }

    public function setOutputPath(string $outputPath): void
    {
        $this->outputPath = $outputPath;
    }

    public function generate(): bool
    {
        if (!is_dir($this->outputPath)) {
            throw new \Exception('Output path does not exist', 1);
        }
        if (!is_writable($this->outputPath)) {
            throw new \Exception('Output path is not writable', 1);
        }
        $timer = new Timer();
        $timer->start('scan');
        $this->scan();
        $timer->checkpoint('render');
        $this->log('Scan completed in '.interval($timer->get('scan') / 1000));
        $result = $this->render($this->index, $this->outputPath);
        $this->log('Rendered in '.interval($timer->stop('render') / 1000));
        $this->log('Total time: '.interval($timer->get('total') / 1000));

        return $result;
    }

    public function generateIndex(string $style = 'vuepress'): bool
    {
        $outputDir = dirname($this->outputPath);
        if (!is_dir($outputDir)) {
            throw new \Exception('Output path does not exist', 1);
        }
        if (!is_writable($outputDir)) {
            throw new \Exception('Output path is not writable', 1);
        }
        $timer = new Timer();
        $timer->start('scan');
        $this->scan();
        $timer->checkpoint('sort');
        $this->log('Scan completed in '.interval($timer->get('scan') / 1000));
        $this->log('Sorting index');
        $this->createNamespaceHierarchy($this->index);
        $timer->checkpoint('render');
        $this->log('Sort completed in '.interval($timer->get('scan') / 1000));
        $templates = $this->loadTemplates(__DIR__.'/../../../libs/templates/api', self::DOC_OUTPUT_INDEX);
        if (!array_key_exists($style, $templates)) {
            throw new \Exception('Invalid index style: '.$style);
        }
        $this->log('Rendering index');
        file_put_contents($this->outputPath, $templates[$style]->render((array) $this->index));
        $this->log('Index written to '.$this->outputPath);
        $this->log('Rendered in '.interval($timer->stop('render') / 1000));
        $this->log('Total time: '.interval($timer->get('total') / 1000));

        return true;
    }

    private function scan(): bool
    {
        if (!file_exists($this->scanPath)) {
            return false;
        }

        $files = [];
        if (!is_dir($this->scanPath)) {
            $files[] = $this->scanPath;
        } else {
            $files = $this->getFiles($this->scanPath);
        }
        $PHPParser = new PHP();
        $this->index = (object) [
            'project' => [
                'title' => $this->title ?? 'API Documentation',
                'description' => '',
            ],
            'namespaces' => [],
            'interfaces' => [],
            'traits' => [],
            'classes' => [],
            'functions' => [],
            'constants' => [],
        ];
        $this->log('Generating API documentation');
        foreach ($files as $file) {
            try {
                $this->log("Parsing file: {$file}");
                $parsedFile = $PHPParser->parse($file);
                if (null === $parsedFile) {
                    continue;
                }
                $this->updateIndex($this->index, $parsedFile);
            } catch (\Exception $e) {
                $this->log("Error parsing file: {$file}");

                continue;
            }
        }

        return true;
    }

    private function render(\stdClass &$index, string $outputPath): bool
    {
        $templates = $this->loadTemplates(__DIR__.'/../../../libs/templates/api', $this->outputFormat);

        try {
            if (!file_exists($outputPath)) {
                mkdir($outputPath, 0777, true);
            }
            $subdirs = [
                'classes' => 'class',
                'functions' => 'function',
                'interfaces' => 'interface',
                'traits' => 'trait',
                'constants' => 'constant',
            ];
            foreach ($subdirs as &$subdir) {
                $subdir = rtrim($outputPath, '/ ').'/'.ltrim($subdir, '/ ');
                if (file_exists($subdir)) {
                    continue;
                }
                mkdir($subdir, 0777, true);
            }

            // Render the index
            $output = $outputPath.'/home';
            $this->renderNamespace($index, $subdirs, $templates);
            foreach ($index->namespaces as $namespace) {
                $this->log("Rendering namespace: {$namespace->name}");
                $this->renderNamespace($namespace, $subdirs, $templates);
            }
            $this->log('Rendering index');
            file_put_contents($output.'.md', $templates['index']->render((array) $index));
        } catch (\Throwable $e) {
            $this->log($e->getMessage());
        }

        return true;
    }

    /**
     * @param array<string,Smarty> $template
     * @param array<string>        $subdirs
     */
    private function renderNamespace(ParserNamespace|\stdClass $namespace, array $subdirs, array $template): void
    {
        foreach ($subdirs as $type => $subdir) {
            foreach ($namespace->{$type} as $item) {
                try {
                    $output = $subdir.'/'.($namespace instanceof ParserNamespace
                        ? str_replace('\\', '/', $namespace->name).'/'
                        : '').$item->name;
                    $name = basename($subdir);
                    if (!array_key_exists($name, $template)) {
                        continue;
                    }
                    if (!file_exists($dirname = dirname($output))) {
                        mkdir($dirname, 0777, true);
                    }
                    $content = $template[$name]->render([$name => $item]);
                    file_put_contents($output.'.md', $content);
                } catch (\Throwable $e) {
                    $this->log("Processing: {$item->name}".PHP_EOL.$e->getMessage());
                }
            }
        }
    }

    /**
     * @return array<string,Smarty>
     */
    private function loadTemplates(string $path, int $outputFormat = 2): array
    {
        $templatePath = rtrim($path, '/ ').'/'.match ($outputFormat) {
            self::DOC_OUTPUT_MARKDOWN => 'markdown',
            self::DOC_OUTPUT_INDEX => 'index',
            default => 'markdown'
        };
        if (!(file_exists($templatePath) && is_dir($templatePath))) {
            throw new \Exception('Template not found: '.$templatePath);
        }
        $templates = [];
        foreach (new \DirectoryIterator($templatePath) as $file) {
            if ($file->isDot() || $file->isDir()) {
                continue;
            }
            $template = new Smarty();
            $template->loadFromFile(new File($file->getPathname()));
            $template->addFilter($this->postProcessRemoveEmptyLines(...));
            $template->addFilter($this->postProcessReplaceClassLinks(...));
            $templates[$file->getBasename('.tpl')] = $template;
        }

        return $templates;
    }

    private function updateIndex(\stdClass &$index, ParserFile $parsedFile): void
    {
        if ($namespace = $parsedFile->namespace) {
            if (!(array_key_exists($namespace->name, $index->namespaces)
                && $index->namespaces[$namespace->name] instanceof ParserNamespace)) {
                $index->namespaces[$namespace->name] = $namespace;
            }
            $updateIndex = &$index->namespaces[$namespace->name];
        } else {
            $updateIndex = &$index;
        }
        $this->pushIndexItem($updateIndex->interfaces, $parsedFile->getInterfaces());
        $this->pushIndexItem($updateIndex->traits, $parsedFile->getTraits());
        $this->pushIndexItem($updateIndex->classes, $parsedFile->getClasses());
        $this->pushIndexItem($updateIndex->functions, $parsedFile->getFunctions());
        $this->pushIndexItem($updateIndex->constants, $parsedFile->getConstants());
    }

    /**
     * @param array<TokenParser> $array
     * @param array<TokenParser> $items
     */
    private function pushIndexItem(array &$array, array $items): void
    {
        foreach ($items as $item) {
            $array[$item->fullName] = $item;
        }
    }

    /**
     * @return array<string>
     */
    private function getFiles(string $path): array
    {
        $files = [];
        $dir = new \DirectoryIterator($path);
        foreach ($dir as $file) {
            if ($file->isDot()) {
                continue;
            }
            if ($file->isDir()) {
                $files = array_merge($files, $this->getFiles($file->getPathname()));
            } else {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function postProcessRemoveEmptyLines(string $content): string
    {
        return preg_replace('/^\s+$/m', '', $content);
    }

    private function postProcessReplaceClassLinks(string $content): string
    {
        return preg_replace_callback('/\[\[(.+)\]\]/', function ($item) {
            $pos = strrpos($item[1], '\\');
            if (false === $pos) {
                if (false === strpos($item[1], '|')) {
                    return '['.$item[1].'](https://www.php.net/manual/en/class.'.strtolower($item[1]).'.php)';
                }
                [$link, $title] = explode('|', $item[1]);

                return '['.$title.']('.$link.')';
            }
            $namespaceName = substr($item[1], 0, $pos);
            if (!array_key_exists($namespaceName, $this->index->namespaces)) {
                return $item[1];
            }
            $namespace = $this->index->namespaces[$namespaceName];
            $itemName = '\\'.$item[1];
            $extension = match ($this->outputFormat) {
                self::DOC_OUTPUT_MARKDOWN => 'md',
                default => 'html'
            };
            if (array_key_exists($itemName, $namespace->classes)) {
                $type = 'class';
            } elseif (array_key_exists($itemName, $namespace->interfaces)) {
                $type = 'interface';
            } elseif (array_key_exists($itemName, $namespace->traits)) {
                $type = 'trait';
            } else {
                return $item[1];
            }

            return '['.$item[1].'](/api/'.$type.'/'.str_replace('\\', '/', $item[1]).'.'.$extension.')';
        }, $content);
    }

    private function log(string $message): void
    {
        if ($this->callback) {
            ($this->callback)($message);
        }
    }

    private function createNamespaceHierarchy(\stdClass &$item): void
    {
        $namespaces = $item->namespaces;
        $item->namespaces = [];

        // Sort namespace keys to ensure proper hierarchy
        $keys = array_keys((array) $namespaces);
        sort($keys);

        foreach ($keys as $ns) {
            $parts = explode('\\', trim($ns, '\\'));
            $current = &$item->namespaces;
            $fullPath = '';

            foreach ($parts as $part) {
                $fullPath = trim($fullPath.'\\'.$part, '\\');
                // @phpstan-ignore isset.offset
                if (!isset($current[$part])) {
                    $current[$part] = (object) [
                        'name' => $part,
                        'fullName' => $fullPath,
                        'namespaces' => [],
                        'classes' => [],
                        'interfaces' => [],
                        'traits' => [],
                        'functions' => [],
                        'constants' => [],
                    ];
                }
                // If this is the last part, merge the original namespace data
                if ($fullPath === $ns) {
                    $original = $namespaces[$ns];
                    $current[$part]->classes = $original->classes;
                    $current[$part]->interfaces = $original->interfaces;
                    $current[$part]->traits = $original->traits;
                    $current[$part]->functions = $original->functions;
                    $current[$part]->constants = $original->constants;
                }
                $current = &$current[$part]->namespaces;
            }
        }
    }
}
