<?php

namespace App\DataFixtures;

use App\Entity\AiKey;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/** Une clé IA dans chacun des états filtrables : active, à distribuer, épuisée, périmée, révoquée. */
class AiKeyFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    /** Clé libre, à saisir dans « Mon compte → Clé IA » pour tester la génération. */
    public const FREE_KEY = 'DEMO-AIKY-FREE-2026';

    public function load(ObjectManager $manager): void
    {
        $martin = $this->getReference(UserFixtures::ref('mme.martin'), User::class);
        $durand = $this->getReference(UserFixtures::ref('m.durand'), User::class);

        $active = $this->key('DEMO-AIKY-MRTN-2026', 10, 'Mme Martin')->redeemFor($martin);
        $active->consume()->consume();

        $exhausted = $this->key('DEMO-AIKY-DRND-USED', 3, 'M. Durand')->redeemFor($durand);
        $exhausted->consume()->consume()->consume();

        $keys = [
            $active,
            $exhausted,
            $this->key(self::FREE_KEY, 5, 'À distribuer'),
            $this->key('DEMO-AIKY-PERI-MEE7', 5, 'Essai terminé')->setExpiresAt(new \DateTimeImmutable('-1 day')),
            $this->key('DEMO-AIKY-REVQ-UEE7', 5, 'Révoquée')->revoke(),
        ];

        foreach ($keys as $key) {
            $manager->persist($key);
        }

        $manager->flush();
    }

    private function key(string $value, int $generations, string $label): AiKey
    {
        return (new AiKey($value, $generations))->setLabel($label)->setCreatedBy('fixtures');
    }

    public function getDependencies(): array
    {
        return [UserFixtures::class];
    }

    public static function getGroups(): array
    {
        return ['demo', 'ai-keys'];
    }
}
