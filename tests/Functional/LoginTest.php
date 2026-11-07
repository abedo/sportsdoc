<?php

namespace App\Tests\Functional;

final class LoginTest extends DatabaseWebTestCase
{
    public function testLoginPageIsPublicAndHasGoogleAndFacebookButtons(): void
    {
        $this->client->request('GET', '/login');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/connect/google"]');
        self::assertSelectorExists('a[href="/connect/facebook"]');
    }

    public function testConnectFacebookRedirectsToFacebookWithAppId(): void
    {
        $this->client->request('GET', '/connect/facebook');
        self::assertResponseRedirects();
        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertStringContainsString('facebook.com', $location);
        self::assertStringContainsString('client_id=test-fb-id', $location);
        self::assertStringContainsString('connect%2Ffacebook%2Fcheck', $location);
    }

    public function testCheckForFacebookWithoutCodeIsRejected(): void
    {
        $this->client->request('GET', '/connect/facebook/check');
        self::assertResponseStatusCodeSame(403);
    }

    public function testConnectRedirectsToGoogleWithClientId(): void
    {
        $this->client->request('GET', '/connect/google');
        self::assertResponseRedirects();
        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertStringStartsWith('https://accounts.google.com/', $location);
        self::assertStringContainsString('client_id=test-client-id', $location);
        self::assertStringContainsString('redirect_uri=', $location);
    }

    public function testCheckWithoutCodeIsRejected(): void
    {
        $this->client->request('GET', '/connect/google/check');
        self::assertResponseStatusCodeSame(403);
    }

    public function testLogoutRedirectsToLogin(): void
    {
        $this->client->request('GET', '/logout');
        self::assertResponseRedirects('/login');
    }

    public function testDevQuickLoginDoesNotExistOutsideDevEnvironment(): void
    {
        $this->client->request('GET', '/dev/login');
        self::assertResponseStatusCodeSame(404);
    }
}
