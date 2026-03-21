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
 * Tests for URL extraction and modification methods.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Css_Parser
 */
#[CoversClass(Parser::class)]
class UrlTest extends TestCase
{
    public function testGetAllUrlsBasic(): void
    {
        $css = 'body{background:url(image.png)} .logo{background:url("logo.jpg")}';
        $parser = new Parser($css);
        $urls = $parser->getAllUrls();

        $this->assertCount(2, $urls);
        $this->assertSame('image.png', $urls[0]->url);
        $this->assertSame('logo.jpg', $urls[1]->url);
    }

    public function testGetAllUrlsNestedInLists(): void
    {
        $css = 'body{background:linear-gradient(red, blue), url(bg.png)}';
        $parser = new Parser($css);
        $urls = $parser->getAllUrls();

        $this->assertCount(1, $urls);
        $this->assertSame('bg.png', $urls[0]->url);
    }

    public function testGetAllUrlsNoUrls(): void
    {
        $css = 'body{color:red}';
        $parser = new Parser($css);
        $urls = $parser->getAllUrls();

        $this->assertCount(0, $urls);
        $this->assertSame([], $urls);
    }

    public function testGetAllUrlsFromMultipleRules(): void
    {
        $css = 'body{background-image:url(a.png)} @font-face{src:url(f.ttf)}';
        $parser = new Parser($css);
        $urls = $parser->getAllUrls();

        $this->assertCount(2, $urls);
        $this->assertSame('a.png', $urls[0]->url);
        $this->assertSame('f.ttf', $urls[1]->url);
    }

    public function testModifyUrlsBasic(): void
    {
        $css = 'body{background:url(image.png)}';
        $parser = new Parser($css);
        $parser2 = $parser->modifyUrls(fn($url) => '/assets/' . $url);

        $result = $parser2->compress();
        $this->assertStringContainsString('/assets/image.png', $result);
    }

    public function testModifyUrlsImmutability(): void
    {
        $css = 'body{background:url(image.png)}';
        $parser = new Parser($css);
        $parser2 = $parser->modifyUrls(fn($url) => '/assets/' . $url);

        $this->assertNotSame($parser, $parser2);
        $this->assertStringContainsString('image.png', $parser->compress());
        $this->assertStringNotContainsString('/assets/', $parser->compress());
    }

    public function testModifyUrlsPreservesNonUrlRules(): void
    {
        $css = 'body{color:red; background:url(a.png); margin:10px}';
        $parser = new Parser($css);
        $parser2 = $parser->modifyUrls(fn($url) => '/new/' . $url);

        $result = $parser2->compress();
        $this->assertStringContainsString('color:red', $result);
        $this->assertStringContainsString('margin:10px', $result);
        $this->assertStringContainsString('/new/a.png', $result);
    }

    public function testRemoveUrlRulesBasic(): void
    {
        $css = 'body{color:red; background:url(x.png)} .safe{margin:10px}';
        $parser = new Parser($css);
        $parser2 = $parser->removeUrlRules();

        $result = $parser2->compress();
        $this->assertStringContainsString('color:red', $result);
        $this->assertStringNotContainsString('background', $result);
        $this->assertStringContainsString('margin', $result);
    }

    public function testRemoveUrlRulesNestedUrls(): void
    {
        $css = 'body{background:linear-gradient(red), url(x.png)}';
        $parser = new Parser($css);
        $parser2 = $parser->removeUrlRules();

        $result = $parser2->compress();
        $this->assertStringNotContainsString('background', $result);
    }

    public function testRemoveUrlRulesImmutability(): void
    {
        $css = 'body{background:url(x.png)}';
        $parser = new Parser($css);
        $parser2 = $parser->removeUrlRules();

        $this->assertNotSame($parser, $parser2);
        $this->assertStringContainsString('background', $parser->compress());
        $this->assertStringNotContainsString('background', $parser2->compress());
    }

    public function testRemoveUrlRulesNoUrls(): void
    {
        $css = 'body{color:red;margin:10px}';
        $parser = new Parser($css);
        $parser2 = $parser->removeUrlRules();

        $this->assertSame($parser->compress(), $parser2->compress());
    }
}
