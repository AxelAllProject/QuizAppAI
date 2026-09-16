<?php

namespace App\Tests\Shared\Infrastructure;

use App\Shared\Infrastructure\Persistence\LikePattern;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LikePatternTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function terms(): iterable
    {
        yield 'texte simple, en minuscules' => ['  Volcans ', '%volcans%'];
        yield 'pourcentage littéral' => ['100%', '%100!%%'];
        yield 'souligné littéral' => ['a_b', '%a!_b%'];
        yield 'caractère d’échappement doublé' => ['wow!', '%wow!!%'];
        yield 'accents mis en minuscules' => ['ÉTÉ', '%été%'];
    }

    #[DataProvider('terms')]
    public function testUserInputCannotActAsASqlWildcard(string $term, string $expected): void
    {
        $this->assertSame($expected, LikePattern::contains($term));
    }
}
