<?php

namespace App\Tests\Shared\Application;

use App\Shared\Application\AdminKeyGenerator;
use PHPUnit\Framework\TestCase;

class AdminKeyGeneratorTest extends TestCase
{
    public function testKeyUsesTheReadableFormat(): void
    {
        $key = (new AdminKeyGenerator())->generate();

        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{4}(-[A-HJ-NP-Z2-9]{4}){3}$/', $key);
    }

    public function testKeysAreNotRepeated(): void
    {
        $generator = new AdminKeyGenerator();
        $keys = array_map(static fn () => $generator->generate(), range(1, 50));

        $this->assertCount(50, array_unique($keys));
    }
}
