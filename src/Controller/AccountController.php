<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class AccountController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $error = $authenticationUtils->getLastAuthenticationError();

        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('account/login.html.twig', [
            'last_username' => $lastUsername,
            'error'         => $error,
        ]);
    }

    /**
     * Déconnexion — gérée automatiquement par Symfony
     * URL : /logout
     */
    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        
        throw new \LogicException('Ce code ne doit jamais être exécuté.');
    }

    /**
     * Page d'inscription classique (email + password)
     * URL : /register
     */
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $error = null;

        if ($request->isMethod('POST')) {
            $email    = $request->request->get('email');
            $password = $request->request->get('password');

            $existingUser = $em->getRepository(User::class)
                              ->findOneBy(['email' => $email]);

            if ($existingUser) {
                $error = 'Cet email est déjà utilisé.';
            } elseif (strlen($password) < 8) {
                $error = 'Le mot de passe doit contenir au moins 8 caractères.';
            } else {
                $user = new User();
                $user->setEmail($email);
                $user->setRoles(['ROLE_USER']);

                $hashedPassword = $passwordHasher->hashPassword($user, $password);
                $user->setPassword($hashedPassword);

                $em->persist($user);
                $em->flush();

                $this->addFlash('success', ' Compte créé ! Connectez-vous.');
                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('account/register.html.twig', [
            'error' => $error,
        ]);
    }
}