<?php

declare(strict_types=1);

namespace Hazaar\Tests;

use Hazaar\Template\Smarty;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class TemplateTest extends TestCase
{
    public function testCanRenderSmartyTemplate(): void
    {
        $smarty = new Smarty();
        $smarty->loadFromString('Hello {$name}!');
        $this->assertEquals('Hello World!', $smarty->render(['name' => 'World']));
    }

    public function testCanRenderSmartyTemplateWithCustomDelimiters(): void
    {
        $smarty = new Smarty();
        $params = ['name' => 'World'];
        $smarty->setDelimiters('{{', '}}');
        $smarty->loadFromString('Hello {{$name}}!');
        $this->assertEquals('Hello World!', $smarty->render($params));
        $smarty->setDelimiters('<', '>');
        $smarty->loadFromString('Hello <$name>!');
        $this->assertEquals('Hello World!', $smarty->render($params));
        // Test that the delimiters are not replaced in the template
        $smarty->loadFromString('Hello {{$name}}!');
        $this->assertEquals('Hello {{$name}}!', $smarty->render($params));
    }

    public function testCanRenderSmartyTemplateWithCustomDelimitersAndEscape(): void
    {
        $smarty = new Smarty();
        $params = ['name' => 'World'];
        $smarty->setDelimiters('{{', '}}');
        $smarty->loadFromString('Hello {{$name|escape}}!');
        $this->assertEquals('Hello World!', $smarty->render($params));
        $smarty->setDelimiters('<', '>');
        $smarty->loadFromString('Hello <$name|escape>!');
        $this->assertEquals('Hello World!', $smarty->render($params));
        // Test that the delimiters are not replaced in the template
        $smarty->loadFromString('Hello {{$name|escape}}!');
        $this->assertEquals('Hello {{$name|escape}}!', $smarty->render($params));
    }

    public function testCanRenderSmartyTemplateWithCustomDelimitersAndEscapeAndModifiers(): void
    {
        $smarty = new Smarty();
        $params = ['name' => 'World'];
        $smarty->setDelimiters('{{', '}}');
        $smarty->loadFromString('Hello {{$name|escape|upper}}!');
        $this->assertEquals('Hello WORLD!', $smarty->render($params));
        $smarty->setDelimiters('<', '>');
        $smarty->loadFromString('Hello <$name|escape|upper>!');
        $this->assertEquals('Hello WORLD!', $smarty->render($params));
        // Test that the delimiters are not replaced in the template
        $smarty->loadFromString('Hello {{$name|escape|upper}}!');
        $this->assertEquals('Hello {{$name|escape|upper}}!', $smarty->render($params));
    }

    public function testCanRenderSmartyTemplateWithCustomFunctions(): void
    {
        $smarty = new Smarty();
        $params = ['name' => 'World'];
        $smarty->registerFunction('hello', function ($name) {
            return 'Hello '.$name.'!';
        });
        $smarty->loadFromString('{hello name=$name}');
        $this->assertEquals('Hello World!', $smarty->render($params));
    }

    public function testCanRenderSmartyTemplateWithSortFunction(): void
    {
        $smarty = new Smarty();
        $params = ['name' => ['d' => 'World', 'a' => 'Hello']];
        $smarty->registerFunction('sort', function (&$items) {
            ksort($items);
        });
        $smarty->loadFromString('{sort items=$name}{$name|implode: }!');
        $this->assertEquals('Hello World!', $smarty->render($params));
    }
}
