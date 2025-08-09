<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Créer un utilisateur administrateur pour StockPro',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Vérifier si un admin existe déjà
        $existingAdmin = $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_ADMIN%')
            ->getQuery()
            ->getOneOrNullResult();

        if ($existingAdmin) {
            $io->warning('Un administrateur existe déjà avec l\'email: ' . $existingAdmin->getEmail());
            return Command::SUCCESS;
        }

        // Créer un nouvel utilisateur admin
        $admin = new User();
        $admin->setEmail('admin@stockpro.com');
        $admin->setRoles(['ROLE_ADMIN']);
        
        // Hasher le mot de passe
        $hashedPassword = $this->passwordHasher->hashPassword($admin, 'admin123');
        $admin->setPassword($hashedPassword);

        try {
            $this->entityManager->persist($admin);
            $this->entityManager->flush();

            $io->success('Administrateur créé avec succès !');
            $io->table(['Propriété', 'Valeur'], [
                ['Email', 'admin@stockpro.com'],
                ['Mot de passe', 'admin123'],
                ['Rôles', 'ROLE_ADMIN'],
            ]);
            $io->note('Vous pouvez maintenant vous connecter avec ces identifiants et être redirigé vers la page d\'administration.');

        } catch (\Exception $e) {
            $io->error('Erreur lors de la création de l\'administrateur: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
