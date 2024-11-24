<?php

declare(strict_types=1);

namespace Hazaar\Tool;

use Hazaar\Parser\PHP;
use Hazaar\Parser\PHP\ParserFile;
use Hazaar\Parser\PHP\ParserNamespace;
use Hazaar\Parser\PHP\TokenParser;
use Hazaar\Template\Smarty;

class APIDoc
{
    public const DOC_OUTPUT_HTML = 1;
    public const DOC_OUTPUT_MARKDOWN = 2;

    private int $outputFormat;

    public function __construct(int $outputFormat)
    {
        $this->outputFormat = $outputFormat;
    }

    public function generate(string $path, string $outputPath): bool
    {
        if (!file_exists($path)) {
            return false;
        }
        $templates = $this->loadTemplates(SUPPORT_PATH.'/templates/api');
        $title = 'API Documentation';
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
                'title' => $title,
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
        // $output = $outputPath.'/home';
        // file_put_contents($output.'.md', $templates['index']->render($index));

        return true;
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
        if ($namespace = $parsedFile->getNamespace()) {
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
            $array[$item->fullName()] = $item;
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
