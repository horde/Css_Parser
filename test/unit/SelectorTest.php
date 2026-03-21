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
 * Tests for selector matching methods.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Css_Parser
 */
#[CoversClass(Parser::class)]
class SelectorTest extends TestCase
{
    public function testGetRulesBySelectorsBasic(): void
    {
        $css = 'body{color:red} .header{margin:10px} footer{padding:5px}';
        $parser = new Parser($css);
        $rules = $parser->getRulesBySelectors(['body', 'footer']);

        $this->assertStringContainsString('color', $rules);
        $this->assertStringContainsString('red', $rules);
        $this->assertStringContainsString('padding', $rules);
        $this->assertStringNotContainsString('margin', $rules);
    }

    public function testGetRulesBySelectorsClassSelectors(): void
    {
        $css = 'body{color:red} .header{margin:10px}';
        $parser = new Parser($css);
        $rules = $parser->getRulesBySelectors(['.header']);

        $this->assertStringContainsString('margin', $rules);
        $this->assertStringNotContainsString('color', $rules);
    }

    public function testGetRulesBySelectorsNoMatches(): void
    {
        $css = 'body{color:red}';
        $parser = new Parser($css);
        $rules = $parser->getRulesBySelectors(['.header']);

        $this->assertSame('', $rules);
    }

    public function testGetRulesBySelectorsEmptyList(): void
    {
        $css = 'body{color:red}';
        $parser = new Parser($css);
        $rules = $parser->getRulesBySelectors([]);

        $this->assertSame('', $rules);
    }

    public function testImpViewUseCase(): void
    {
        // Simulate IMP View printAttach selector extraction
        $css = 'body{font:12px Arial} .headerblock{background:#eee;padding:10px} .content{margin:20px} footer{font-size:10px}';
        $parser = new Parser($css);

        // Extract rules for body and headerblock class
        $rules = $parser->getRulesBySelectors(['body', '.headerblock']);

        $this->assertStringContainsString('font', $rules);
        $this->assertStringContainsString('12px', $rules);
        $this->assertStringContainsString('background', $rules);
        $this->assertStringContainsString('#eee', $rules);
        $this->assertStringContainsString('padding', $rules);
        $this->assertStringContainsString('10px', $rules);

        // Should not include .content or footer
        $this->assertStringNotContainsString('margin', $rules);
        $this->assertStringNotContainsString('20px', $rules);
    }
}
