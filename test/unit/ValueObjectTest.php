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

use Horde\Css\Parser\Import;
use Horde\Css\Parser\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Error;

/**
 * Tests for value objects (Import, Url).
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Css_Parser
 */
#[CoversClass(Import::class)]
#[CoversClass(Url::class)]
class ValueObjectTest extends TestCase
{
    public function testImportValueObject(): void
    {
        $import = new Import('test.css');
        $this->assertSame('test.css', $import->url);
    }

    public function testUrlValueObject(): void
    {
        $url = new Url('image.png');
        $this->assertSame('image.png', $url->url);
    }

    public function testImportIsReadonly(): void
    {
        $import = new Import('test.css');

        $this->expectException(Error::class);
        $import->url = 'other.css'; // @phpstan-ignore-line
    }

    public function testUrlIsReadonly(): void
    {
        $url = new Url('image.png');

        $this->expectException(Error::class);
        $url->url = 'other.png'; // @phpstan-ignore-line
    }
}
