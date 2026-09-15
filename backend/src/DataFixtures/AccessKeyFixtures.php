<?php

namespace App\DataFixtures;

use App\Entity\AccessKey;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/** Une clé d'accès dans chacun des états affichés par le back-office. */
class AccessKeyFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    /** Les clés documentées dans docs/administration.md : elles restent valables après rechargement. */
    public const ADMIN_KEY = '2MZL-SEPP-F67F-9TGN';
    public const TEACHER_KEY = '6YW5-4X49-2FFX-NB5P';

    public function load(ObjectManager $manager): void
    {
        $keys = [
            $this->key(self::ADMIN_KEY, AccessKey::ROLE_ADMIN, 'Compte administrateur principal'),
            $this->key(self::TEACHER_KEY, AccessKey::ROLE_TEACHER, 'Équipe pédagogique'),
            $this->key('DEMO-PERI-MEE7-2025', AccessKey::ROLE_TEACHER, 'Rentrée 2025')
                ->setExpiresAt(new \DateTimeImmutable('-10 days')),
            $this->key('DEMO-REVQ-UEE7-KEYS', AccessKey::ROLE_TEACHER, 'Remplaçant parti')->revoke(),
            $this->key('DEMO-ATTR-IBUE-DURA', AccessKey::ROLE_TEACHER, 'M. Durand')
                ->assignTo($this->getReference(UserFixtures::ref('m.durand'), User::class)),
        ];

        foreach ($keys as $key) {
            $manager->persist($key);
        }

        $manager->flush();
    }

    private function key(string $value, string $role, string $label): AccessKey
    {
        return (new AccessKey())
            ->setValue($value)
            ->setRole($role)
            ->setLabel($label)
            ->setCreatedBy('fixtures');
    }

    public function getDependencies(): array
    {
        return [UserFixtures::class];
    }

    public static function getGroups(): array
    {
        return ['demo', 'access-keys'];
    }
}
