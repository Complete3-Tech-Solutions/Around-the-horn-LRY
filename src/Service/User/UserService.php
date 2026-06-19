<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordEncoder,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function createUser(string $username, string $password): ?User
    {
        $existingUser = $this->userRepository->findOneBy(['username' => $username]);
        if ($existingUser) {
            throw new \Exception('User already exists');
        }

        try {
            $this->entityManager->beginTransaction();
            $user = new User();
            $user->setUsername($username);
            $user->setPassword($this->passwordEncoder->hashPassword($user, $password));

            $this->entityManager->persist($user);
            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Exception $e) {
            throw new \Exception('Error creating user');
        }

        return $user;
    }

    /**
     * Set a new password for an existing user (hashing it). Used by the
     * in-app moderator "change password" screen — the caller is already
     * authenticated, so no current-password check is required here.
     */
    public function changePassword(User $user, string $newPassword): void
    {
        $user->setPassword($this->passwordEncoder->hashPassword($user, $newPassword));
        $this->entityManager->flush();
    }
}
