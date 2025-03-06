<?php

declare(strict_types=1);

namespace Hazaar\Console\API;

use Hazaar\File;
use Hazaar\Parser\PHP;
use Hazaar\Parser\PHP\ParserFile;
use Hazaar\Parser\PHP\ParserNamespace;
use Hazaar\Parser\PHP\TokenParser;
use Hazaar\Template\Smarty;
use Hazaar\Timer;

class Documentor
{
    public const DOC_OUTPUT_HTML = 1;
    public const DOC_OUTPUT_MARKDOWN = 2;

    private int $outputFormat;
    private ?string $title;
    private ?\Closure $callback = null;

    private ?\stdClass $index = null;

    public function __construct(int $outputFormat, ?string $title = null)
    {
        $this->outputFormat = $outputFormat;
        $this->title = $title;
    }

    public function setCallback(\Closure $callback): void
    {
        $this->callback = $callback;
    }

    public function generate(string $path, string $outputPath): bool
    {
        if (!file_exists($path)) {
            return false;
        }
        $timer = new Timer();
        $timer->start('scan');
        $path = realpath($path);
        $files = [];
        if (!is_dir($path)) {
            $files[] = $path;
        } else {
            $files = $this->getFiles($path);
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
        $this->log('Scan completed in '.interval($timer->stop('scan') / 1000));
        $timer->start('render');
        $result = $this->render($this->index, $outputPath);
        $this->log('Rendered in '.interval($timer->stop('render') / 1000));
        $this->log('Total time: '.interval($timer->get('total') / 1000));

        return $result;
    }

    public function generateIndex(string $path, string $outputPath, string $style = 'vuepress'): bool
    {
        if (null === $this->index) {
            return false;
        }
        $templates = $this->loadTemplates(__DIR__.'/../../../libs/templates/api');
        $output = $outputPath.'/home';
        $this->log('Rendering index');
        file_put_contents($output.'.md', $templates['index']->render((array) $this->index));

        return true;
    }

    private function log(string $message): void
    {
        if ($this->callback) {
            ($this->callback)($message);
        }
    }

    private function render(\stdClass &$index, string $outputPath): bool
    {
        $templates = $this->loadTemplates(__DIR__.'/../../../libs/templates/api');

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
    private function loadTemplates(string $path): array
    {
        $templatePath = rtrim($path, '/ ').'/'.match ($this->outputFormat) {
            self::DOC_OUTPUT_MARKDOWN => 'markdown',
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
}
