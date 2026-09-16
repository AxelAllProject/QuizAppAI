<?php

namespace App\Tests\Ai\Application;

use App\Ai\Application\AiKeyRedeemer;
use App\Ai\Domain\Exception\AiKeyRedemptionException;
use App\Ai\Domain\Model\AiKey;
use App\Ai\Domain\Repository\AiKeyRepository;
use App\Identity\Domain\Model\User;
use App\Shared\Application\UnitOfWork;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

class AiKeyRedeemerTest extends TestCase
{
    public function testAValidKeyIsLinkedToTheAccountAndSaved(): void
    {
        $user = new User();
        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->expects($this->once())->method('flush');

        $key = $this->redeemer(new AiKey('CODE', 5), $unitOfWork)->redeem('CODE', $user);

        $this->assertSame($user, $key->getRedeemedBy());
    }

    public static function refusedKeys(): iterable
    {
        yield 'inconnue' => [null, 403];
        yield 'révoquée' => [(new AiKey('CODE', 5))->revoke(), 403];
        yield 'expirée' => [(new AiKey('CODE', 5))->setExpiresAt(new \DateTimeImmutable('2026-01-01')), 403];
        yield 'déjà liée à un autre compte' => [(new AiKey('CODE', 5))->redeemFor(self::userWithId(42)), 409];
        yield 'épuisée' => [(new AiKey('CODE', 1))->consume(), 409];
    }

    #[DataProvider('refusedKeys')]
    public function testRefusedKeysAreNeitherLinkedNorSaved(?AiKey $key, int $status): void
    {
        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->expects($this->never())->method('flush');

        try {
            $this->redeemer($key, $unitOfWork)->redeem('CODE', self::userWithId(7));
            $this->fail('La clé aurait dû être refusée.');
        } catch (AiKeyRedemptionException $exception) {
            $this->assertSame($status, $exception->getStatusCode());
        }
    }

    private static function userWithId(int $id): User
    {
        $user = new User();
        (new \ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }

    private function redeemer(?AiKey $key, UnitOfWork $unitOfWork): AiKeyRedeemer
    {
        $keys = $this->createStub(AiKeyRepository::class);
        $keys->method('findByValue')->willReturn($key);

        return new AiKeyRedeemer($keys, new MockClock('2026-09-16'), $unitOfWork);
    }
}
