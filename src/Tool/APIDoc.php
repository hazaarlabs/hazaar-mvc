<?php

declare(strict_types=1);

namespace Hazaar\Tool;

use Hazaar\Parser\PHP;
use Hazaar\Parser\PHP\TokenParser;

class APIDoc
{
    public const DOC_OUTPUT_HTML = 1;
    public const DOC_OUTPUT_MARKDOWN = 2;

    private int $outputFormat;

    public function __construct(int $outputFormat)
    {
        $this->outputFormat = $outputFormat;
    }

    public function generate(string $path): bool
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
        $index = [
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
                if ($namespace = $parsedFile->getNamespace()) {
                    $this->push($index['namespaces'], [$namespace]);
                }
                $this->push($index['interfaces'], $parsedFile->getInterfaces());
                $this->push($index['classes'], $parsedFile->getClasses());
                $this->push($index['functions'], $parsedFile->getFunctions());
                $this->push($index['constants'], $parsedFile->getConstants());
            } catch (\Exception $e) {
                echo "Error parsing file: {$file}\n";

                continue;
            }
        }

        return match ($this->outputFormat) {
            self::DOC_OUTPUT_HTML => $this->generateHTML($index),
            self::DOC_OUTPUT_MARKDOWN => $this->generateMarkdown($index),
            default => false,
        };
    }

    private function generateHTML(array $index): bool
    {
        $output = '<html><head><title>API Documentation</title></head><body>';
        $output .= '<h1>API Documentation</h1>';
        $output .= '<h2>Namespaces</h2>';
        $output .= '<ul>';
        foreach ($index['namespaces'] as $namespace) {
            $output .= "<li>{$namespace->name}</li>";
        }
        $output .= '</ul>';
        $output .= '<h2>Interfaces</h2>';
        $output .= '<ul>';
        foreach ($index['interfaces'] as $interface) {
            $output .= "<li>{$interface->name}</li>";
        }
        $output .= '</ul>';
        $output .= '<h2>Classes</h2>';
        $output .= '<ul>';
        foreach ($index['classes'] as $class) {
            $output .= "<li>{$class->name}</li>";
        }
        $output .= '</ul>';
        $output .= '<h2>Functions</h2>';
        $output .= '<ul>';
        foreach ($index['functions'] as $function) {
            $output .= "<li>{$function->name}</li>";
        }
        $output .= '</ul>';
        $output .= '<h2>Constants</h2>';
        $output .= '<ul>';
        foreach ($index['constants'] as $constant) {
            $output .= "<li>{$constant->name}</li>";
        }
        $output .= '</ul>';
        $output .= '</body></html>';

        return file_put_contents('api.html', $output) > 0;
    }

    private function generateMarkdown(array $index): bool
    {
        $output = '# API Documentation';
        $output .= "\n\n## Namespaces\n";
        foreach ($index['namespaces'] as $namespace) {
            $output .= "- {$namespace->name}\n";
        }
        $output .= "\n\n## Interfaces\n";
        foreach ($index['interfaces'] as $interface) {
            $output .= "- {$interface->name}\n";
        }
        $output .= "\n\n## Classes\n";
        foreach ($index['classes'] as $class) {
            $output .= "- {$class->name}\n";
        }
        $output .= "\n\n## Functions\n";
        foreach ($index['functions'] as $function) {
            $output .= "- {$function->name}\n";
        }
        $output .= "\n\n## Constants\n";
        foreach ($index['constants'] as $constant) {
            $output .= "- {$constant->name}\n";
        }

        return file_put_contents('api.md', $output) > 0;
    }

    /**
     * @param array<TokenParser> $array
     * @param array<TokenParser> $items
     */
    private function push(array &$array, array $items): void
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
