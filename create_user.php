<?php
require_once __DIR__ . '/vendor/autoload.php';

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

require_once __DIR__ . '/config/bootstrap.php';

$kernel = new \App\Kernel('prod', false);
$kernel->boot();

$container = $kernel->getContainer();
$em = $container->get('doctrine')->getManager();
$passwordHasher = $container->get('security.user_password_hasher');

// Vérifier si l'utilisateur existe déjà
$existing = $em->getRepository(User::class)->findOneBy(['email' => 'test@test.com']);

if ($existing) {
    echo "L'utilisateur test existe déjà !\n";
    exit;
}

$user = new User();
$user->setEmail('test@test.com');
$user->setRoles(['ROLE_USER']);
$user->setPassword($passwordHasher->hashPassword($user, 'user123'));
$user->setIsVerified(true);

$em->persist($user);
$em->flush();

echo "✅ Utilisateur test créé avec succès !\n";
echo "Email: test@test.com\n";
echo "Mot de passe: user123\n";
