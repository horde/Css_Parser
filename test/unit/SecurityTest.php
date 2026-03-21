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
 * Tests for security filtering methods.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Css_Parser
 */
#[CoversClass(Parser::class)]
class SecurityTest extends TestCase
{
    public function testRemoveRulesByNameSingleRule(): void
    {
        $css = 'body{color:red; cursor:pointer; margin:10px}';
        $parser = new Parser($css);
        $parser2 = $parser->removeRulesByName('cursor');

        $result = $parser2->compress();
        $this->assertStringContainsString('color:red', $result);
        $this->assertStringNotContainsString('cursor', $result);
        $this->assertStringContainsString('margin', $result);
    }

    public function testRemoveRulesByNameMultipleRules(): void
    {
        $css = 'body{color:red; cursor:pointer; behavior:none; margin:10px}';
        $parser = new Parser($css);
        $parser2 = $parser->removeRulesByName('cursor', 'behavior');

        $result = $parser2->compress();
        $this->assertStringContainsString('color:red', $result);
        $this->assertStringNotContainsString('cursor', $result);
        $this->assertStringNotContainsString('behavior', $result);
        $this->assertStringContainsString('margin', $result);
    }

    public function testRemoveRulesByNameNoMatches(): void
    {
        $css = 'body{color:red}';
        $parser = new Parser($css);
        $parser2 = $parser->removeRulesByName('cursor');

        $result = $parser2->compress();
        $this->assertStringContainsString('color:red', $result);
    }

    public function testRemoveRulesByNameImmutability(): void
    {
        $css = 'body{cursor:pointer}';
        $parser = new Parser($css);
        $parser2 = $parser->removeRulesByName('cursor');

        $this->assertNotSame($parser, $parser2);
        $this->assertStringContainsString('cursor', $parser->compress());
        $this->assertStringNotContainsString('cursor', $parser2->compress());
    }

    public function testCombinedFiltering(): void
    {
        $css = '@import "evil.css"; body{color:red; cursor:pointer; background:url(evil.png)}';
        $parser = new Parser($css);
        $safe = $parser
            ->removeImports()
            ->removeUrlRules()
            ->removeRulesByName('cursor');

        $result = $safe->compress();
        $this->assertStringNotContainsString('@import', $result);
        $this->assertStringNotContainsString('cursor', $result);
        $this->assertStringNotContainsString('background', $result);
        $this->assertStringContainsString('color:red', $result);
    }

    public function testImpEmailUseCase(): void
    {
        // Simulate IMP email CSS filtering
        $untrustedCss = '@import "phishing.css"; body{background:url(tracker.gif); cursor:crosshair; color:blue}';
        $parser = new Parser($untrustedCss);

        // Apply IMP security filters
        $safe = $parser
            ->removeImports()
            ->removeUrlRules()
            ->removeRulesByName('cursor', 'behavior');

        $result = $safe->compress();

        // Should keep safe properties
        $this->assertStringContainsString('color:blue', $result);

        // Should remove dangerous properties
        $this->assertStringNotContainsString('@import', $result);
        $this->assertStringNotContainsString('url', $result);
        $this->assertStringNotContainsString('cursor', $result);
    }

    public function testCssMinifyUseCase(): void
    {
        // Simulate CssMinify URL rewriting
        $css = '@import "shared.css"; body{background:url(../img/bg.png)} .logo{background:url(logo.svg)}';
        $parser = new Parser($css);

        // Get imports for separate processing
        $imports = $parser->getImports();
        $this->assertCount(1, $imports);
        $this->assertSame('shared.css', $imports[0]->url);

        // Remove imports and rewrite URLs
        $processed = $parser
            ->removeImports()
            ->modifyUrls(fn($url) => '/assets/' . basename($url));

        $result = $processed->compress();

        $this->assertStringNotContainsString('@import', $result);
        $this->assertStringContainsString('/assets/bg.png', $result);
        $this->assertStringContainsString('/assets/logo.svg', $result);
    }
}
