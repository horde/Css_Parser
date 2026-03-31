<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Css_Parser
 */

namespace Horde\Css\Parser\Test;

use Horde\Css\Parser\Parser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Parser core functionality.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Css_Parser
 */
#[CoversClass(Parser::class)]
class ParserTest extends TestCase
{
    public function testBasicParsing(): void
    {
        $css = 'body{color:red;margin:10px}';
        $parser = new Parser($css);

        $this->assertSame('body{color:red;margin:10px}', $parser->compress());
    }

    public function testCompress(): void
    {
        $css = "body {\n  color: red;\n  margin: 10px;\n}";
        $parser = new Parser($css);

        $this->assertSame('body{color:red;margin:10px}', $parser->compress());
    }

    public function testRender(): void
    {
        $css = 'body{color:red}';
        $parser = new Parser($css);

        $rendered = $parser->render();
        $this->assertStringContainsString('body', $rendered);
        $this->assertStringContainsString('color', $rendered);
        $this->assertStringContainsString('red', $rendered);
    }

    public function testToString(): void
    {
        $css = 'body{color:red}';
        $parser = new Parser($css);

        $rendered = (string) $parser;
        $this->assertStringContainsString('body', $rendered);
        $this->assertStringContainsString('color', $rendered);
        $this->assertStringContainsString('red', $rendered);
    }

    public function testEmptyDocument(): void
    {
        $css = '';
        $parser = new Parser($css);

        $this->assertSame('', $parser->compress());
    }

    public function testImmutability(): void
    {
        $css = '@import "x.css"; body{color:red; cursor:pointer; background:url(a.png)}';
        $parser1 = new Parser($css);
        $parser2 = $parser1->removeImports();
        $parser3 = $parser1->removeUrlRules();
        $parser4 = $parser1->removeRulesByName('cursor');
        $parser5 = $parser1->modifyUrls(fn($u) => '/assets/' . $u);

        $this->assertNotSame($parser1, $parser2);
        $this->assertNotSame($parser1, $parser3);
        $this->assertNotSame($parser1, $parser4);
        $this->assertNotSame($parser1, $parser5);

        // Original unchanged
        $original = $parser1->render();
        $this->assertStringContainsString('@import', $original);
        $this->assertStringContainsString('cursor', $original);
    }

    public function testMethodChaining(): void
    {
        $css = '@import "evil.css"; body{color:red; cursor:pointer; background:url(evil.png)}';
        $parser = new Parser($css);

        $safe = $parser
            ->removeImports()
            ->removeUrlRules()
            ->removeRulesByName('cursor');

        $result = $safe->compress();

        $this->assertStringContainsString('color:red', $result);
        $this->assertStringNotContainsString('@import', $result);
        $this->assertStringNotContainsString('cursor', $result);
        $this->assertStringNotContainsString('background', $result);
    }

    public function testComplexCss(): void
    {
        $css = <<<CSS
            @import "base.css";

            body {
                font-family: Arial, sans-serif;
                background: linear-gradient(red, blue), url(bg.png);
                color: #333;
                cursor: default;
            }

            .header {
                margin: 0;
                padding: 10px 20px;
                background-image: url("header.jpg");
            }

            footer {
                border-top: 1px solid #ccc;
            }
            CSS;

        $parser = new Parser($css);

        // Test import extraction
        $imports = $parser->getImports();
        $this->assertCount(1, $imports);
        $this->assertSame('base.css', $imports[0]->url);

        // Test URL extraction
        $urls = $parser->getAllUrls();
        $this->assertCount(2, $urls); // bg.png and header.jpg

        // Test filtering
        $safe = $parser
            ->removeImports()
            ->removeRulesByName('cursor')
            ->removeUrlRules();

        $result = $safe->compress();
        $this->assertStringNotContainsString('@import', $result);
        $this->assertStringNotContainsString('cursor', $result);
        $this->assertStringNotContainsString('url', $result);
        $this->assertStringContainsString('color:#333', $result);
        $this->assertStringContainsString('border-top', $result);
    }

    public function testKeepOnlyUrlRules(): void
    {
        $css = <<<'CSS'
            .safe { color: red; }
            .tracking { background: url("pixel.gif"); }
            .more-safe { margin: 10px; }
            CSS;

        $parser = new Parser($css);
        $urlOnly = $parser->keepOnlyUrlRules();

        $result = $urlOnly->compress();
        $this->assertStringContainsString('url', $result);
        $this->assertStringContainsString('background', $result);
        $this->assertStringNotContainsString('color:red', $result);
        $this->assertStringNotContainsString('margin', $result);
    }

    public function testKeepOnlyRulesByName(): void
    {
        $css = <<<'CSS'
            .normal { color: red; cursor: pointer; margin: 10px; }
            CSS;

        $parser = new Parser($css);
        $cursorOnly = $parser->keepOnlyRulesByName('cursor');

        $result = $cursorOnly->compress();
        $this->assertStringContainsString('cursor', $result);
        $this->assertStringNotContainsString('color', $result);
        $this->assertStringNotContainsString('margin', $result);
    }

    public function testKeepOnlyDangerousCss(): void
    {
        $css = <<<'CSS'
            @import url("external.css");
            .safe { color: red; margin: 10px; }
            .tracking { background: url("pixel.gif"); }
            .cursor-rule { cursor: none; }
            .more-safe { font-size: 12px; }
            CSS;

        $parser = new Parser($css);
        $dangerous = $parser->keepOnlyDangerousCss('cursor');

        $result = $dangerous->compress();
        // Should contain dangerous CSS
        $this->assertStringContainsString('@import', $result);
        $this->assertStringContainsString('url', $result);
        $this->assertStringContainsString('cursor', $result);
        // Should NOT contain safe CSS
        $this->assertStringNotContainsString('color:red', $result);
        $this->assertStringNotContainsString('margin', $result);
        $this->assertStringNotContainsString('font-size', $result);
    }

    public function testKeepOnlyDangerousCssWithoutNamedRules(): void
    {
        $css = <<<'CSS'
            @import url("external.css");
            .safe { color: red; }
            .tracking { background: url("pixel.gif"); }
            CSS;

        $parser = new Parser($css);
        $dangerous = $parser->keepOnlyDangerousCss();

        $result = $dangerous->compress();
        $this->assertStringContainsString('@import', $result);
        $this->assertStringContainsString('url', $result);
        $this->assertStringNotContainsString('color', $result);
    }
}
