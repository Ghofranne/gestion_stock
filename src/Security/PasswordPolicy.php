<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\PasswordHistoryRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class PasswordPolicy
{
    public function __construct(
        private readonly PasswordHistoryRepository $historyRepo,
        private readonly UserPasswordHasherInterface $hasher,
        // parameters
        private readonly int $minLength = 8,
        private readonly bool $requireUpper = true,
        private readonly bool $requireLower = true,
        private readonly bool $requireDigit = true,
        private readonly bool $requireSpecial = true,
        private readonly int $historyDepth = 5,
    ) {}

    public function validateComplexity(string $password): ?string
    {
        if (mb_strlen($password) < $this->minLength) {
            return sprintf('Le mot de passe doit contenir au moins %d caractères.', $this->minLength);
        }
        if ($this->requireUpper && !preg_match('/[A-Z]/u', $password)) {
            return 'Le mot de passe doit contenir au moins une majuscule (A-Z).';
        }
        if ($this->requireLower && !preg_match('/[a-z]/u', $password)) {
            return 'Le mot de passe doit contenir au moins une minuscule (a-z).';
        }
        if ($this->requireDigit && !preg_match('/\d/u', $password)) {
            return 'Le mot de passe doit contenir au moins un chiffre (0-9).';
        }
        if ($this->requireSpecial && !preg_match('/[^\p{L}\p{N}\s]/u', $password)) {
            return 'Le mot de passe doit contenir au moins un caractère spécial (!@#$%^&*...).';
        }
        return null;
    }

    public function violatesHistory(User $user, string $newPassword): bool
    {
        $histories = $this->historyRepo->findRecentByUser($user, $this->historyDepth);
        foreach ($histories as $h) {
            if (password_verify($newPassword, $h->getPasswordHash())) {
                return true;
            }
        }
        return false;
    }

    public function getHistoryDepth(): int { return $this->historyDepth; }
}
