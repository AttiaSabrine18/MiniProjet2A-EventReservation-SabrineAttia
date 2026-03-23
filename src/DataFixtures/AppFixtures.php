<?php
// src/DataFixtures/AppFixtures.php

namespace App\DataFixtures;

use App\Entity\Event;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        // ====== CRÉER UN ADMIN ======
        $admin = new User();
        $admin->setEmail('admin@test.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword(
            $this->passwordHasher->hashPassword($admin, 'admin123')
        );
        $manager->persist($admin);

        // ====== CRÉER UN UTILISATEUR ======
        $user = new User();
        $user->setEmail('sabrine@test.com');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword(
            $this->passwordHasher->hashPassword($user, 'user123')
        );
        $manager->persist($user);

        // ====== CRÉER DES ÉVÉNEMENTS ======
        $events = [
            [
                'title'       => 'Concert Jazz Night',
                'description' => 'Une soirée jazz exceptionnelle avec les meilleurs musiciens.',
                'date'        => new \DateTimeImmutable('+7 days'),
                'location'    => 'Salle des fêtes, Tunis',
                'seats'       => 100,
                'image'       => 'https://picsum.photos/seed/jazz/800/400',
            ],
            [
                'title'       => 'Conférence Tech 2026',
                'description' => 'Les dernières tendances en intelligence artificielle.',
                'date'        => new \DateTimeImmutable('+14 days'),
                'location'    => 'Centre de congrès, Sousse',
                'seats'       => 200,
                'image'       => 'https://picsum.photos/seed/tech/800/400',
            ],
            [
                'title'       => 'Festival Culturel',
                'description' => 'Célébration de la culture tunisienne.',
                'date'        => new \DateTimeImmutable('+21 days'),
                'location'    => 'Amphithéâtre, Carthage',
                'seats'       => 500,
                'image'       => 'https://picsum.photos/seed/festival/800/400',
            ],
            [
                'title'       => 'Workshop Symfony',
                'description' => 'Apprenez Symfony 7 avec des experts.',
                'date'        => new \DateTimeImmutable('+3 days'),
                'location'    => 'ISSAT Sousse',
                'seats'       => 30,
                'image'       => 'https://picsum.photos/seed/symfony/800/400',
            ],
            [
                'title'       => 'Soirée Startup',
                'description' => 'Rencontrez les startups tunisiennes.',
                'date'        => new \DateTimeImmutable('+10 days'),
                'location'    => 'Hub Innovant, Sfax',
                'seats'       => 150,
                'image'       => 'https://picsum.photos/seed/startup/800/400',
            ],
        ];

        foreach ($events as $eventData) {
            $event = new Event();
            $event->setTitle($eventData['title']);
            $event->setDescription($eventData['description']);
            $event->setDate($eventData['date']);
            $event->setLocation($eventData['location']);
            $event->setSeats($eventData['seats']);
            $event->setImage($eventData['image']);
            $manager->persist($event);
        }

        $manager->flush();
    }
}