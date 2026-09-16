<?php

namespace App\Tests\Identity\Application;

use App\Access\Application\AccessKeyRedeemer;
use App\Access\Domain\Repository\AccessKeyRepository;
use App\Identity\Application\Exception\RegistrationFailedException;
use App\Identity\Application\RegisterUser;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Repository\UserRepository;
use App\Shared\Application\UnitOfWork;
use App\Shared\Domain\Exception\DuplicateEntryException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class RegisterUserTest extends TestCase
{
    public function testTakenEmailAndNameAreBothReported(): void
    {
        $users = $this->createStub(UserRepository::class);
        $users->method('findOneByEmail')->willReturn(new User());
        $users->method('isUsernameTaken')->willReturn(true);

        try {
            $this->registerUser($users, $this->createStub(UnitOfWork::class))->register('bob@exemple.test', 'bob', 'motdepasse', null);
            $this->fail('L’inscription aurait dû être refusée.');
        } catch (RegistrationFailedException $exception) {
            $this->assertSame(['Un compte existe déjà avec cette adresse e-mail.', 'Ce pseudo est déjà pris.'], $exception->getErrors());
        }
    }

    /** Deux inscriptions simultanées passent la vérification en PHP : la contrainte d'unicité doit donner une erreur de formulaire, pas une 500. */
    public function testAConcurrentDuplicateBecomesAFormError(): void
    {
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('transactional')->willThrowException(new DuplicateEntryException());

        $this->expectException(RegistrationFailedException::class);
        $this->expectExceptionMessage('Un compte existe déjà avec cette adresse e-mail ou ce pseudo.');

        $this->registerUser($this->createStub(UserRepository::class), $unitOfWork)->register('bob@exemple.test', 'bob', 'motdepasse', null);
    }

    public function testARejectedAccessKeyWritesNothing(): void
    {
        $users = $this->createMock(UserRepository::class);
        $users->expects($this->never())->method('add');

        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('transactional')->willReturnCallback(static fn (callable $operation) => $operation());

        $this->expectExceptionMessage("Clé d'accès invalide ou révoquée.");

        $this->registerUser($users, $unitOfWork)->register('bob@exemple.test', 'bob', 'motdepasse', 'INCONNUE');
    }

    private function registerUser(UserRepository $users, UnitOfWork $unitOfWork): RegisterUser
    {
        $keys = $this->createStub(AccessKeyRepository::class);
        $redeemer = new AccessKeyRedeemer('', 'dev', $keys, $users);

        return new RegisterUser($users, $redeemer, $this->createStub(UserPasswordHasherInterface::class), $unitOfWork);
    }
}
