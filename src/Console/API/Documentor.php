<?php

declare(strict_types=1);

namespace Hazaar\Console\API;

use Hazaar\Parser\PHP;
use Hazaar\Parser\PHP\ParserFile;
use Hazaar\Parser\PHP\ParserNamespace;
use Hazaar\Parser\PHP\TokenParser;
use Hazaar\Template\Smarty;

class Documentor
{
    public const DOC_OUTPUT_HTML = 1;
    public const DOC_OUTPUT_MARKDOWN = 2;

    private int $outputFormat;
    private ?string $title;

    public function __construct(int $outputFormat, ?string $title = null)
    {
        $this->outputFormat = $outputFormat;
        $this->title = $title;
    }

    public function generate(string $path, string $outputPath): bool
    {
        if (!file_exists($path)) {
            return false;
        }
        $path = realpath($path);
        $files = [];
        if (!is_dir($path)) {
            $files[] = $path;
        } else {
            $files = $this->getFiles($path);
        }
        $PHPParser = new PHP();
        $index = (object) [
            'project' => [
                'title' => $this->title ?? 'API Documentation',
                'description' => '',
            ],
            'namespaces' => [],
            'interfaces' => [],
            'classes' => [],
            'functions' => [],
            'constants' => [],
        ];
        foreach ($files as $file) {
            try {
                $parsedFile = $PHPParser->parse($file);
                if (null === $parsedFile) {
                    continue;
                }
                $this->updateIndex($index, $parsedFile);
            } catch (\Exception $e) {
                echo "Error parsing file: {$file}\n";

                continue;
            }
        }

        return $this->render($index, $outputPath);
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
            file_put_contents($output.'.md', $templates['index']->render((array) $index));
            $this->renderNamespace($index, $subdirs, $templates);
            foreach ($index->namespaces as $namespace) {
                $this->renderNamespace($namespace, $subdirs, $templates);
            }
        } catch (\Throwable $e) {
            ob_end_clean();
            echo "\n\n".$e->getMessage()."\n\nFiles:\n";
            echo ' * Template: '.$templates['index']->getTemplateFile()."\n";
            echo isset($output) ? ' * Output: '.$output.'.md'."\n\n" : "\n";
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
                file_put_contents($output.'.md', $template[$name]->render([$name => $item]));
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
            $template->loadFromFile($file->getPathname());
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
}
