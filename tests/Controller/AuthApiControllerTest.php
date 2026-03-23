<?php
// tests/Controller/AuthApiControllerTest.php

namespace App\Tests\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AuthApiControllerTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        // ✅ createClient() UNE SEULE FOIS ici
        $this->client = static::createClient();
    }

    // ================================================
    // TEST 1 : register/options retourne un challenge
    // ================================================
    public function testRegisterOptionsReturnsChallenge(): void
    {
        $this->client->request(
            'POST',
            '/api/auth/register/options',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => 'test-register@example.com'])
        );

        $this->assertResponseIsSuccessful();

        $response = json_decode(
            $this->client->getResponse()->getContent(),
            true
        );

        $this->assertArrayHasKey('challenge', $response);
        $this->assertArrayHasKey('rp', $response);
        $this->assertArrayHasKey('user', $response);
    }

    // ================================================
    // TEST 2 : /me nécessite authentification
    // ================================================
    public function testMeEndpointRequiresAuth(): void
    {
        $this->client->request('GET', '/api/auth/me');
        $this->assertResponseStatusCodeSame(401);
    }

    // ================================================
    // TEST 3 : /me fonctionne avec un token valide
    // ================================================
    public function testMeEndpointWithValidToken(): void
    {
        $container = static::getContainer();
        $em        = $container->get('doctrine')->getManager();

        // Cherche ou crée l'utilisateur de test
        $user = $em->getRepository(User::class)
                   ->findOneBy(['email' => 'api-test@example.com']);

        if (!$user) {
            $user = new User();
            $user->setEmail('api-test@example.com');
            $user->setRoles(['ROLE_USER']);
            $user->setPassword('fake-password-for-test');
            $em->persist($user);
            $em->flush();
        }

        // Génère un JWT valide
        $token = $container
            ->get('lexik_jwt_authentication.jwt_manager')
            ->create($user);

        // ✅ Utilise le client existant avec setServerParameter
        $this->client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $token);
        $this->client->request('GET', '/api/auth/me');

        $this->assertResponseIsSuccessful();

        $response = json_decode(
            $this->client->getResponse()->getContent(),
            true
        );

        $this->assertEquals('api-test@example.com', $response['email']);
    }

    // ================================================
    // TEST 4 : login/options retourne un challenge
    // ================================================
    public function testLoginOptionsReturnsChallenge(): void
    {
        $this->client->request(
            'POST',
            '/api/auth/login/options',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json']
        );

        $this->assertResponseIsSuccessful();

        $response = json_decode(
            $this->client->getResponse()->getContent(),
            true
        );

        $this->assertArrayHasKey('challenge', $response);
    }

    // ================================================
    // TEST 5 : register/verify sans données → 400
    // ================================================
    public function testRegisterVerifyWithoutDataReturnsBadRequest(): void
    {
        $this->client->request(
            'POST',
            '/api/auth/register/verify',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([])
        );

        $this->assertResponseStatusCodeSame(400);
    }
}