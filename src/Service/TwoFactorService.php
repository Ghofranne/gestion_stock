<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class TwoFactorService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly EntityManagerInterface $em,
    ) {}

    public function sendEmailCode(User $user, int $ttlMinutes = 10): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $user->setTwoFactorEmailCode($code);
        $user->setTwoFactorEmailExpiresAt(new \DateTimeImmutable("+{$ttlMinutes} minutes"));
        $this->em->flush();

        $mail = (new TemplatedEmail())
            ->from(new Address($_ENV['MAILER_FROM'] ?? 'no-reply@stockpro.local', 'StockPro'))
            ->to(new Address($user->getEmail()))
            ->subject('Votre code de vérification (2FA)')
            ->htmlTemplate('emails/2fa_email_code.html.twig')
            ->context([
                'user' => $user,
                'code' => $code,
                'expires_in' => $ttlMinutes,
            ]);

        $this->mailer->send($mail);
    }

    public function verifyEmailCode(User $user, string $code): bool
    {
        $expiresAt = $user->getTwoFactorEmailExpiresAt();
        if (!$user->getTwoFactorEmailCode() || !$expiresAt || $expiresAt < new \DateTimeImmutable()) {
            return false;
        }
        $ok = hash_equals($user->getTwoFactorEmailCode(), trim($code));
        if ($ok) {
            // Invalidate code after successful use
            $user->setTwoFactorEmailCode(null);
            $user->setTwoFactorEmailExpiresAt(null);
            $this->em->flush();
        }
        return $ok;
    }

    /**
     * Generate and store hashed backup codes. Returns the plain codes to display once.
     * Each code is 10 characters (A-Z2-9, no confusing chars).
     */
    public function generateBackupCodes(User $user, int $count = 10): array
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $c = '';
            for ($j = 0; $j < 10; $j++) {
                $c .= $alphabet[random_int(0, strlen($alphabet)-1)];
            }
            $codes[] = $c;
        }
        $hashed = array_map(static fn($c) => password_hash($c, PASSWORD_DEFAULT), $codes);
        $user->setTwoFactorBackupCodes($hashed);
        $this->em->flush();
        return $codes;
    }

    public function consumeBackupCode(User $user, string $code): bool
    {
        $list = $user->getTwoFactorBackupCodes() ?? [];
        foreach ($list as $idx => $hash) {
            if (password_verify($code, $hash)) {
                unset($list[$idx]);
                $user->setTwoFactorBackupCodes(array_values($list));
                $this->em->flush();
                return true;
            }
        }
        return false;
    }
}
