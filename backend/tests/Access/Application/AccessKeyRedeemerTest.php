<?php

namespace App\Tests\Access\Application;

use App\Access\Application\AccessKeyRedeemer;
use App\Access\Domain\Model\AccessKey;
use App\Access\Domain\Repository\AccessKeyRepository;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;

class AccessKeyRedeemerTest extends TestCase
{
    public function testTheDefaultBootstrapCodeWorksOutsideProduction(): void
    {
        $user = new User();

        $this->assertTrue($this->redeemer(bootstrapKey: 'admin', environment: 'dev')->redeem('admin', $user));
        $this->assertSame(User::ROLE_ADMIN, $user->getRole());
    }

    /**
     * « admin » est la valeur committée dans .env : si un déploiement oublie de la
     * remplacer dans .env.local, elle ne doit jamais donner les droits admin en prod.
     */
    public function testTheDefaultBootstrapCodeIsRefusedInProduction(): void
    {
        $user = new User();

        $this->assertFalse($this->redeemer(bootstrapKey: 'admin', environment: 'prod')->redeem('admin', $user));
        $this->assertSame(User::ROLE_PLAYER, $user->getRole());
    }

    public function testACustomBootstrapCodeStillWorksInProduction(): void
    {
        $redeemer = $this->redeemer(bootstrapKey: 'XPYX-KDEU-2RLE-97PV', environment: 'prod');

        $this->assertTrue($redeemer->redeem('XPYX-KDEU-2RLE-97PV', new User()));
    }

    public function testAnEmptyBootstrapCodeNeverGrantsAnything(): void
    {
        $redeemer = $this->redeemer(bootstrapKey: '', environment: 'dev');

        $this->assertFalse($redeemer->redeem('', new User()));
        $this->assertFalse($redeemer->redeem('admin', new User()));
    }

    /** La clé de secours crée le premier admin ; ensuite, elle ne doit plus être une porte d'entrée. */
    public function testTheBootstrapCodeIsRefusedOnceAnAdminExists(): void
    {
        $redeemer = $this->redeemer(bootstrapKey: 'XPYX-KDEU-2RLE-97PV', environment: 'prod', adminExists: true);

        $this->assertFalse($redeemer->redeem('XPYX-KDEU-2RLE-97PV', new User()));
    }

    public function testAnAdminKeyAlreadyClaimedBySomeoneElseGrantsNothing(): void
    {
        $user = new User();
        $redeemer = $this->redeemer(key: (new AccessKey())->setRole(AccessKey::ROLE_ADMIN), claimSucceeds: false);

        $this->assertFalse($redeemer->redeem('CODE', $user));
        $this->assertSame(User::ROLE_PLAYER, $user->getRole());
    }

    private function redeemer(
        string $bootstrapKey = '',
        string $environment = 'dev',
        bool $adminExists = false,
        ?AccessKey $key = null,
        bool $claimSucceeds = true,
    ): AccessKeyRedeemer {
        $keys = $this->createStub(AccessKeyRepository::class);
        $keys->method('findActive')->willReturn($key);
        $keys->method('claim')->willReturn($claimSucceeds);

        $users = $this->createStub(UserRepository::class);
        $users->method('hasAdmin')->willReturn($adminExists);

        return new AccessKeyRedeemer($bootstrapKey, $environment, $keys, $users);
    }
}
