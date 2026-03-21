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
 * Tests for import handling methods.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Css_Parser
 */
#[CoversClass(Parser::class)]
class ImportTest extends TestCase
{
    public function testGetImportsSingleImport(): void
    {
        $css = '@import "style.css"; body{color:red}';
        $parser = new Parser($css);
        $imports = $parser->getImports();

        $this->assertCount(1, $imports);
        $this->assertSame('style.css', $imports[0]->url);
    }

    public function testGetImportsMultipleImports(): void
    {
        $css = '@import "a.css"; @import url(b.css); body{}';
        $parser = new Parser($css);
        $imports = $parser->getImports();

        $this->assertCount(2, $imports);
        $this->assertSame('a.css', $imports[0]->url);
        $this->assertSame('b.css', $imports[1]->url);
    }

    public function testGetImportsNoImports(): void
    {
        $css = 'body{color:red}';
        $parser = new Parser($css);
        $imports = $parser->getImports();

        $this->assertCount(0, $imports);
        $this->assertSame([], $imports);
    }

    public function testRemoveImportsBasic(): void
    {
        $css = '@import "style.css"; body{color:red}';
        $parser = new Parser($css);
        $parser2 = $parser->removeImports();

        $result = $parser2->compress();
        $this->assertStringNotContainsString('@import', $result);
        $this->assertStringContainsString('color:red', $result);
    }

    public function testRemoveImportsImmutability(): void
    {
        $css = '@import "style.css"; body{color:red}';
        $parser = new Parser($css);
        $parser2 = $parser->removeImports();

        $this->assertNotSame($parser, $parser2);
        $this->assertStringContainsString('@import', $parser->render());
        $this->assertStringNotContainsString('@import', $parser2->render());
    }

    public function testRemoveImportsMultiple(): void
    {
        $css = '@import "a.css"; @import "b.css"; body{color:red}';
        $parser = new Parser($css);
        $parser2 = $parser->removeImports();

        $result = $parser2->compress();
        $this->assertStringNotContainsString('@import', $result);
        $this->assertStringNotContainsString('a.css', $result);
        $this->assertStringNotContainsString('b.css', $result);
        $this->assertStringContainsString('color:red', $result);
    }

    public function testRemoveImportsEmpty(): void
    {
        $css = 'body{color:red}';
        $parser = new Parser($css);
        $parser2 = $parser->removeImports();

        $this->assertSame($parser->compress(), $parser2->compress());
    }
}
