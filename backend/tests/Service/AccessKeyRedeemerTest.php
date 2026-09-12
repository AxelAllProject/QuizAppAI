<?php

namespace App\Tests\Service;

use App\Entity\AccessKey;
use App\Repository\AccessKeyRepository;
use App\Service\AccessKeyRedeemer;
use PHPUnit\Framework\TestCase;

class AccessKeyRedeemerTest extends TestCase
{
    public function testTheDefaultBootstrapCodeWorksOutsideProduction(): void
    {
        $redeemer = $this->redeemer(bootstrapKey: 'admin', environment: 'dev');

        $this->assertSame(AccessKey::ROLE_ADMIN, $redeemer->redeem('admin'));
    }

    /**
     * « admin » est la valeur committée dans .env : si un déploiement oublie de la
     * remplacer dans .env.local, elle ne doit jamais donner les droits admin en prod.
     */
    public function testTheDefaultBootstrapCodeIsRefusedInProduction(): void
    {
        $redeemer = $this->redeemer(bootstrapKey: 'admin', environment: 'prod');

        $this->assertNull($redeemer->redeem('admin'));
    }

    public function testACustomBootstrapCodeStillWorksInProduction(): void
    {
        $redeemer = $this->redeemer(bootstrapKey: 'XPYX-KDEU-2RLE-97PV', environment: 'prod');

        $this->assertSame(AccessKey::ROLE_ADMIN, $redeemer->redeem('XPYX-KDEU-2RLE-97PV'));
    }

    public function testAnEmptyBootstrapCodeNeverGrantsAnything(): void
    {
        $redeemer = $this->redeemer(bootstrapKey: '', environment: 'dev');

        $this->assertNull($redeemer->redeem(''));
        $this->assertNull($redeemer->redeem('admin'));
    }

    private function redeemer(string $bootstrapKey, string $environment): AccessKeyRedeemer
    {
        $keys = $this->createStub(AccessKeyRepository::class);
        $keys->method('findActive')->willReturn(null);

        return new AccessKeyRedeemer($bootstrapKey, $environment, $keys);
    }
}
