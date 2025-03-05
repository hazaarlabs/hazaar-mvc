<?php

declare(strict_types=1);

namespace Hazaar\Tests;

use Hazaar\File;
use Hazaar\Template\Smarty;
use Hazaar\Template\Smarty\Compiler;
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
        $smarty->compiler->setDelimiters('{{', '}}');
        $smarty->loadFromString('Hello {{$name}}!');
        $this->assertEquals('Hello World!', $smarty->render($params));
        $smarty->compiler->setDelimiters('<', '>');
        $smarty->loadFromString('Hello <$name>!');
        $this->assertEquals('Hello World!', $smarty->render($params));
        // Test that the delimiters are not replaced in the template
        $smarty->loadFromString('Hello {{$name}}!');
        $this->assertEquals('Hello {{$name}}!', $smarty->render($params));
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
            ksort($items, SORT_REGULAR);
        });
        $smarty->loadFromString('{sort items=$name}{$name|implode: }!');
        $this->assertEquals('Hello World!', $smarty->render($params));
    }

    public function testCanRenderSmartyTemplateWithModifierCapitalize(): void
    {
        $smarty = new Smarty();
        $smarty->loadFromString('Hello {$name|capitalize}!');
        $this->assertEquals('Hello World!', $smarty->render(['name' => 'world']));
    }

    public function testCanRenderSmartyTemplateWithModifierCat(): void
    {
        $smarty = new Smarty();
        $smarty->loadFromString('Hello {$name|cat: :World}!');
        $this->assertEquals('Hello Hello World!', $smarty->render(['name' => 'Hello']));
    }

    public function testCanRenderSmartyTemplateWithModifierCountCharacters(): void
    {
        $smarty = new Smarty();
        $smarty->loadFromString('Hello {$name|count_characters}!');
        $this->assertEquals('Hello 5!', $smarty->render(['name' => 'World']));
    }

    public function testCanRenderSmartyTemplateWithModifierCountParagraphs(): void
    {
        $smarty = new Smarty();
        $smarty->loadFromString('{$value|count_paragraphs}');
        $this->assertEquals(1, $smarty->render(['value' => 'Hello World']));
        $this->assertEquals(2, $smarty->render(['value' => "Hello\n\nWorld"]));
        $this->assertEquals(2, $smarty->render(['value' => "Hello\n\n\nWorld"]));
    }

    public function testCanRenderSmartyTemplateWithModifierCountSentences(): void
    {
        $smarty = new Smarty();
        $smarty->loadFromString('{$value|count_sentences}');
        $this->assertEquals(1, $smarty->render(['value' => 'Hello, World!']));
    }

    public function testCanRenderSmartyTemplateWithModifierCountWords(): void
    {
        $smarty = new Smarty();
        $smarty->loadFromString('Hello {$name|count_words}!');
        $this->assertEquals('Hello 1!', $smarty->render(['name' => 'World']));
    }

    public function testCanRenderSmartyTemplateWithModifierDateFormat(): void
    {
        $smarty = new Smarty();
        $smarty->loadFromString('Hello {$name|date_format:"Y-m-d H:i:s"}!');
        $this->assertEquals('Hello 2025-02-22 07:59:46!', $smarty->render(['name' => 1740211186]));
    }

    public function testCanRenderSmartyTemplateWithModifierDefault(): void
    {
        $smarty = new Smarty();
        $smarty->loadFromString('Hello {$name|default:World}!');
        $this->assertEquals('Hello World!', $smarty->render([]));
    }

    public function testCanRenderSmartyTemplateWithModifierPrint(): void
    {
        $smarty = new Smarty();
        $smarty->loadFromString('Hello {$name|print}!');
        $this->assertEquals('Hello World!', $smarty->render(['name' => 'World']));
    }

    public function testCanRenderSmartyTemplateWithModifierExport(): void
    {
        $smarty = new Smarty();
        $smarty->loadFromString('Hello {$name|export}!');
        $this->assertEquals('Hello \'World\'!', $smarty->render(['name' => 'World']));
    }

    public function testCanRenderSmartyTemplateWithModifierType(): void
    {
        $smarty = new Smarty();
        $smarty->loadFromString('Hello {$name|type}!');
        $this->assertEquals('Hello string!', $smarty->render(['name' => 'World']));
    }

    public function testCanRenderSmartyTemplateWithModifierEscape(): void
    {
        $smarty = new Smarty();
        $smarty->loadFromString('Hello {$name|escape}!');
        $this->assertEquals('Hello &lt;World&gt;!', $smarty->render(['name' => '<World>']));
    }

    public function testCanRenderSmartyTemplateWithModifierIndent(): void
    {
        $smarty = new Smarty();
        $smarty->loadFromString('Hello {$name|indent:4}!');
        $this->assertEquals("Hello \n    World!", $smarty->render(['name' => 'World']));
    }

    public function testCanRenderSmartyTemplateWithModifierLower(): void
    {
        $smarty = new Smarty();
        $smarty->loadFromString('Hello {$name|lower}!');
        $this->assertEquals('Hello world!', $smarty->render(['name' => 'World']));
    }

    public function testCanRenderSmartyTemplateWithModifierNl2br(): void
    {
        $smarty = new Smarty();
        $smarty->loadFromString('Hello {$name|nl2br}!');
        $this->assertEquals('Hello World<br />!', $smarty->render(['name' => "World\n"]));
    }

    public function testCanRenderSmartyTemplateWithModifierNumberFormat(): void
    {
        $smarty = new Smarty();
        $smarty->loadFromString('Hello {$name|number_format:2}!');
        $this->assertEquals('Hello 1,000.00!', $smarty->render(['name' => 1000]));
    }

    public function testCanRenderSmartyTemplateWithModifierRegexReplace(): void
    {
        $smarty = new Smarty();
        $smarty->loadFromString('Hello {$name|regex_replace:"/World/":"Universe"}!');
        $this->assertEquals('Hello Hello Universe!', $smarty->render(['name' => 'Hello World']));
    }

    public function testCanRenderSmartyTemplateWithModifierReplace(): void
    {
        $smarty = new Smarty();
        $smarty->loadFromString('Hello {$name|replace:"World":"Universe"}!');
        $this->assertEquals('Hello Hello Universe!', $smarty->render(['name' => 'Hello World']));
    }

    public function testCanRenderSmartyTemplateWithModifierSpacify(): void
    {
        $smarty = new Smarty();
        $smarty->loadFromString('Hello {$name|spacify}!');
        $this->assertEquals('Hello W o r l d!', $smarty->render(['name' => 'World']));
        $smarty->loadFromString('Hello {$name|spacify:_}!');
        $this->assertEquals('Hello W_o_r_l_d!', $smarty->render(['name' => 'World']));
    }

    public function testCanRenderSmartyTemplateWithModifierStringFormat(): void
    {
        $smarty = new Smarty();
        $smarty->loadFromString('Hello {$value|string_format:%.2f}!');
        $this->assertEquals('Hello 1.54!', $smarty->render(['value' => 1.5432]));
    }

    public function testCanRenderSmartyTemplateWithModifierStrip(): void
    {
        $smarty = new Smarty();
        $smarty->loadFromString('Hello {$name|strip}!');
        $this->assertEquals('Hello World!', $smarty->render(['name' => ' World ']));
    }

    public function testCanRenderSmartyTemplateWithModifierStripTags(): void
    {
        $smarty = new Smarty();
        $smarty->loadFromString('Hello {$name|strip_tags}!');
        $this->assertEquals('Hello World!', $smarty->render(['name' => '<b>World</b>']));
    }

    public function testCanRenderSmartyTemplateWithModifierTruncate(): void
    {
        $smarty = new Smarty();
        $smarty->loadFromString('Hello {$name|truncate:5}!');
        $this->assertEquals('Hello Wo...!', $smarty->render(['name' => 'World']));
    }

    public function testCanRenderSmartyTemplateWithModifierUpper(): void
    {
        $smarty = new Smarty();
        $smarty->loadFromString('Hello {$name|upper}!');
        $this->assertEquals('Hello WORLD!', $smarty->render(['name' => 'world']));
    }

    public function testCanRenderSmartyTemplateWithModifierWordwrap(): void
    {
        $smarty = new Smarty();
        $smarty->loadFromString('Hello {$name|wordwrap:3}!');
        $this->assertEquals("Hello Wor\nld!", $smarty->render(['name' => 'World']));
    }

    public function testCanRenderSmartyTemplateWithModifierImplode(): void
    {
        $smarty = new Smarty();
        $smarty->loadFromString('Hello {$name|implode:" "}!');
        $this->assertEquals('Hello Hello World!', $smarty->render(['name' => ['Hello', 'World']]));
    }

    public function testCanRenderSmartyTemplateFromFile(): void
    {
        $smarty = new Smarty();
        $smarty->loadFromFile(new File(__DIR__.'/templates/hello.tpl'));
        $result = $smarty->render([
            'userName' => 'John Doe',
            'welcomeMessage' => 'Welcome to our Amazing Site',
            'introText' => 'This is a personalized welcome message for our valued users.',
            'items' => [
                ['title' => 'First Item', 'description' => 'Description of the first item'],
                ['title' => 'Second Item', 'description' => 'Description of the second item'],
            ],
        ]);
        $this->assertStringContainsString('Welcome, John Doe', $result);
        $this->assertStringContainsString('Welcome to our Amazing Site', $result);
        $this->assertStringContainsString('<h3>First Item</h3>', $result);
        $this->assertStringContainsString('<p>Description of the first item</p>', $result);
        $this->assertStringContainsString('<h3>Second Item</h3>', $result);
    }

    public function testCanCompileSmartyTemplate(): void
    {
        $smartyCompiler = new Compiler();
        $result = $smartyCompiler->exec('Hello {$name}!');
        $this->assertTrue($result);
        $compiledContent = $smartyCompiler->getCompiledContent();
        $this->assertStringContainsString('Hello <?php $this->write(($name??null)); ?>!', $compiledContent);
        $compiled = $smartyCompiler->getCode('_test_template_');
        $this->assertStringContainsString('class _test_template_', $compiled);
    }
}
