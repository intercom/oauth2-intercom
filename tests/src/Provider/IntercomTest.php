<?php

namespace Intercom\OAuth2\Client\Test\Provider;

use Intercom\OAuth2\Client\Provider\Intercom;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\ClientInterface;

class IntercomTest extends TestCase
{
    protected Intercom $provider;

    protected function setUp(): void
    {
        $this->provider = new Intercom([
            'clientId' => 'mock_client_id',
            'clientSecret' => 'mock_secret',
            'redirectUri' => 'none',
        ]);
    }

    public function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    public function testAuthorizationUrl(): void
    {
        $url = $this->provider->getAuthorizationUrl(['approval_prompt' => []]);
        $uri = parse_url($url);
        parse_str($uri['query'], $query);

        $this->assertArrayHasKey('client_id', $query);
        $this->assertArrayHasKey('redirect_uri', $query);
        $this->assertArrayHasKey('state', $query);
        $this->assertArrayHasKey('scope', $query);
        $this->assertArrayHasKey('response_type', $query);
        $this->assertArrayNotHasKey('approval_prompt', $query);
        $this->assertNotNull($this->provider->getState());
    }

    public function testGetAuthorizationUrl(): void
    {
        $url = $this->provider->getAuthorizationUrl();
        $this->assertStringContainsString('https://app.intercom.com/oauth', $url);
    }

    public function testGetBaseAccessTokenUrl(): void
    {
        $url = $this->provider->getBaseAccessTokenUrl([]);
        $this->assertStringContainsString('https://api.intercom.io/auth/eagle/token', $url);
    }

    public function getResourceOwnerDetailsUrl(): void
    {
        $url = $this->provider->getBaseAccessTokenUrl((array)'mock_token');
        $this->assertStringContainsString('https://api.intercom.io/me', $url);
    }

    /**
     * @throws IdentityProviderException
     */
    public function testGetAccessToken(): void
    {
        $response = m::mock(ResponseInterface::class);
        $response->shouldReceive('getBody')->andReturn(
            '{"access_token": "mock_access_token", "token_type": "bearer", "uid": "12345"}'
        );
        $response->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $response->shouldReceive('getStatusCode')->andReturn(200);

        $client = m::mock(ClientInterface::class);
        $client->shouldReceive('send')->times(1)->andReturn($response);
        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);

        $this->assertEquals('mock_access_token', $token->getToken());
        $this->assertNull($token->getExpires());
        $this->assertNull($token->getRefreshToken());
        $this->assertNull($token->getResourceOwnerId());
    }

    public function testUserData()
    {
        $postResponse = m::mock('Psr\Http\Message\ResponseInterface');

        $postResponse->shouldReceive('getBody')->andReturn(
            '{"token": "mock_access_token", "access_token": "mock_access_token", "token_type": "Bearer"}'
        );
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $postResponse->shouldReceive('getStatusCode')->andReturn(200);

        $userJson = '{
            "type":"admin",
            "id":"368312",
            "email":"fizbit@intercom.io",
            "name":"Fizbit Grappleboot",
            "email_verified":true,
            "app":{
                "type":"app",
                "id_code":"2qmk5gy1",
                "created_at":1358214715,
                "secure":true
            },
            "avatar":{
                "type":"avatar",
                "image_url":"https://static.intercomassets.com/avatars/228311/square_128/1462489937.jpg"
            }
        }';
        $userArray = json_decode($userJson, true);

        $userResponse = m::mock('Psr\Http\Message\ResponseInterface');
        $userResponse->shouldReceive('getBody')->andReturn($userJson);
        $userResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $userResponse->shouldReceive('getStatusCode')->andReturn(200);

        $client = m::mock(ClientInterface::class);
        $client->shouldReceive('send')
            ->times(2)
            ->andReturn($postResponse, $userResponse);
        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        $user = $this->provider->getResourceOwner($token);

        $this->assertEquals($userArray, $user->toArray());
        $this->assertEquals($userArray['id'], $user->getId());
        $this->assertEquals($userArray['email'], $user->getEmail());
        $this->assertEquals($userArray['name'], $user->getName());
        $this->assertEquals($userArray['avatar']['image_url'], $user->getAvatarUrl());
    }

    public function testUserUnverifiedEmail(): void
    {
        $postResponse = m::mock('Psr\Http\Message\ResponseInterface');

        $postResponse->shouldReceive('getBody')->andReturn(
            '{"token": "mock_access_token", "access_token": "mock_access_token", "token_type": "Bearer"}'
        );
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $postResponse->shouldReceive('getStatusCode')->andReturn(200);

        $userJson = '{
            "type":"admin",
            "id":"368312",
            "email":"fizbit@intercom.io",
            "name":"Fizbit Grappleboot",
            "email_verified":false,
            "app":{
                "type":"app",
                "id_code":"2qmk5gy1",
                "created_at":1358214715,
                "secure":true
            },
            "avatar":{
                "type":"avatar",
                "image_url":"https://static.intercomassets.com/avatars/228311/square_128/1462489937.jpg"
            }
        }';
        $userArray = json_decode($userJson, true);

        $userResponse = m::mock('Psr\Http\Message\ResponseInterface');
        $userResponse->shouldReceive('getBody')->andReturn($userJson);
        $userResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $userResponse->shouldReceive('getStatusCode')->andReturn(200);

        $client = m::mock(ClientInterface::class);
        $client->shouldReceive('send')
            ->times(2)
            ->andReturn($postResponse, $userResponse);
        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        $user = $this->provider->getResourceOwner($token);

        $this->assertEquals([], $user->toArray());
        $this->assertEquals(null, $user->getId());
        $this->assertEquals(null, $user->getEmail());
        $this->assertEquals(null, $user->getName());
        $this->assertEquals(null, $user->getAvatarUrl());
    }

    public function testSkipUserUnverifiedEmailCheck(): void
    {
        $provider = new Intercom([
            'clientId' => 'mock_client_id',
            'clientSecret' => 'mock_secret',
            'redirectUri' => 'none',
            'verifyEmail' => false,
        ]);

        $postResponse = m::mock('Psr\Http\Message\ResponseInterface');

        $postResponse->shouldReceive('getBody')->andReturn('{
            "token": "mock_access_token",
            "access_token": "mock_access_token",
            "token_type": "Bearer"
        }');
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $postResponse->shouldReceive('getStatusCode')->andReturn(200);

        $userJson = '{
            "type":"admin",
            "id":"368312",
            "email":"fizbit@intercom.io",
            "name":"Fizbit Grappleboot",
            "email_verified":false,
            "app":{"type":"app","id_code":"2qmk5gy1","created_at":1358214715,"secure":true},
            "avatar":{
                "type":"avatar",
                "image_url":"https://static.intercomassets.com/avatars/228311/square_128/1462489937.jpg"
            }
        }';
        $userArray = json_decode($userJson, true);

        $userResponse = m::mock('Psr\Http\Message\ResponseInterface');
        $userResponse->shouldReceive('getBody')->andReturn($userJson);
        $userResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $userResponse->shouldReceive('getStatusCode')->andReturn(200);

        $client = m::mock(ClientInterface::class);
        $client->shouldReceive('send')
            ->times(2)
            ->andReturn($postResponse, $userResponse);
        $provider->setHttpClient($client);

        $token = $provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        $user = $provider->getResourceOwner($token);

        $this->assertEquals($userArray, $user->toArray());
        $this->assertEquals($userArray['id'], $user->getId());
        $this->assertEquals($userArray['email'], $user->getEmail());
        $this->assertEquals($userArray['name'], $user->getName());
        $this->assertEquals($userArray['avatar']['image_url'], $user->getAvatarUrl());
    }

    /**
     * @throws IdentityProviderException
     */
    public function testExceptionThrownWhenErrorResponse(): void
    {
        $this->expectException(IdentityProviderException::class);
        $postResponse = m::mock(ResponseInterface::class);

        $errorBody = '{
            "type":"error.list",
            "request_id":"anvt4on87prigma30i8g",
            "errors":[{"code":"server_error","message":"Server Error"}]
        }';

        $postResponse->shouldReceive('getBody')->andReturn($errorBody);
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $postResponse->shouldReceive('getStatusCode')->andReturn(401);

        $client = m::mock(ClientInterface::class);
        $client->shouldReceive('send')
            ->times(1)
            ->andReturn($postResponse);
        $this->provider->setHttpClient($client);
        $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
    }

    /**
     * @throws IdentityProviderException
     */
    public function testExceptionThrownWhenStatusNotSuccess(): void
    {
        $this->expectException(IdentityProviderException::class);
        $postResponse = m::mock(ResponseInterface::class);

        $errorBody = '{
            "type":"error.list",
            "request_id":"anvt4on87prigma30i8g",
            "errors":[{"code":"server_error","message":"Server Error"}]
        }';

        $postResponse->shouldReceive('getBody')->andReturn($errorBody);
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $postResponse->shouldReceive('getReasonPhrase')->andReturn('Internal Server Error');
        $postResponse->shouldReceive('getStatusCode')->andReturn(500);

        $client = m::mock(ClientInterface::class);
        $client->shouldReceive('send')
            ->times(1)
            ->andReturn($postResponse);
        $this->provider->setHttpClient($client);
        $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
    }
}
