<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AssertIsActiveTest extends TestCase
{
    public function testAssert(): void
    {
        $this->expectException(\AssertionError::class);
        assert(false);
    }
}
