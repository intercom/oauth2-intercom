<?php

namespace Intercom\OAuth2\Client\Test\Provider;

use Mockery as m;

class IntercomTest extends \PHPUnit_Framework_TestCase
{
    protected $provider;

    protected function setUp()
    {
        $this->provider = new \Intercom\OAuth2\Client\Provider\Intercom([
            'clientId' => 'mock_client_id',
            'clientSecret' => 'mock_secret',
            'redirectUri' => 'none',
        ]);
    }

    public function tearDown()
    {
        m::close();
        parent::tearDown();
    }

    public function testAuthorizationUrl()
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

    public function testGetAuthorizationUrl()
    {
        $url = $this->provider->getAuthorizationUrl();
        $this->assertContains('https://app.intercom.io/oauth', $url);
    }

    public function testGetBaseAccessTokenUrl()
    {
        $url = $this->provider->getBaseAccessTokenUrl([]);
        $this->assertContains('https://api.intercom.io/auth/eagle/token', $url);
    }

    public function getResourceOwnerDetailsUrl()
    {
        $url = $this->provider->getBaseAccessTokenUrl('mock_token');
        $this->assertContains('https://api.intercom.io/me', $url);
    }

    public function testGetAccessToken()
    {
        $response = m::mock('Psr\Http\Message\ResponseInterface');
        $response->shouldReceive('getBody')->andReturn('{"access_token": "mock_access_token", "token_type": "bearer", "uid": "12345"}');
        $response->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $response->shouldReceive('getStatusCode')->andReturn(200);

        $client = m::mock('GuzzleHttp\ClientInterface');
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

        $postResponse->shouldReceive('getBody')->andReturn('{"token": "mock_access_token", "access_token": "mock_access_token", "token_type": "Bearer"}');
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $postResponse->shouldReceive('getStatusCode')->andReturn(200);

        $userJson = '{"type":"admin","id":"368312","email":"fizbit@intercom.io","name":"Fizbit Grappleboot","email_verified":true,"app":{"type":"app","id_code":"2qmk5gy1","created_at":1358214715,"secure":true},"avatar":{"type":"avatar", "image_url":"https://static.intercomassets.com/avatars/228311/square_128/1462489937.jpg"}}';
        $userArray = json_decode($userJson, true);

        $userResponse = m::mock('Psr\Http\Message\ResponseInterface');
        $userResponse->shouldReceive('getBody')->andReturn($userJson);
        $userResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $userResponse->shouldReceive('getStatusCode')->andReturn(200);

        $client = m::mock('GuzzleHttp\ClientInterface');
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

    public function testUserUnverifiedEmail()
    {
        $postResponse = m::mock('Psr\Http\Message\ResponseInterface');

        $postResponse->shouldReceive('getBody')->andReturn('{"token": "mock_access_token", "access_token": "mock_access_token", "token_type": "Bearer"}');
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $postResponse->shouldReceive('getStatusCode')->andReturn(200);

        $userJson = '{"type":"admin","id":"368312","email":"fizbit@intercom.io","name":"Fizbit Grappleboot","email_verified":false,"app":{"type":"app","id_code":"2qmk5gy1","created_at":1358214715,"secure":true},"avatar":{"type":"avatar", "image_url":"https://static.intercomassets.com/avatars/228311/square_128/1462489937.jpg"}}';
        $userArray = json_decode($userJson, true);

        $userResponse = m::mock('Psr\Http\Message\ResponseInterface');
        $userResponse->shouldReceive('getBody')->andReturn($userJson);
        $userResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $userResponse->shouldReceive('getStatusCode')->andReturn(200);

        $client = m::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')
            ->times(2)
            ->andReturn($postResponse, $userResponse);
        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        $user = $this->provider->getResourceOwner($token);

        $this->assertEquals(array(), $user->toArray());
        $this->assertEquals(null, $user->getId());
        $this->assertEquals(null, $user->getEmail());
        $this->assertEquals(null, $user->getName());
        $this->assertEquals(null, $user->getAvatarUrl());
    }

    public function testSkipUserUnverifiedEmailCheck()
    {
        $provider = new \Intercom\OAuth2\Client\Provider\Intercom([
            'clientId' => 'mock_client_id',
            'clientSecret' => 'mock_secret',
            'redirectUri' => 'none',
            'verifyEmail' => false
        ]);

        $postResponse = m::mock('Psr\Http\Message\ResponseInterface');

        $postResponse->shouldReceive('getBody')->andReturn('{"token": "mock_access_token", "access_token": "mock_access_token", "token_type": "Bearer"}');
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $postResponse->shouldReceive('getStatusCode')->andReturn(200);

        $userJson = '{"type":"admin","id":"368312","email":"fizbit@intercom.io","name":"Fizbit Grappleboot","email_verified":false,"app":{"type":"app","id_code":"2qmk5gy1","created_at":1358214715,"secure":true},"avatar":{"type":"avatar", "image_url":"https://static.intercomassets.com/avatars/228311/square_128/1462489937.jpg"}}';
        $userArray = json_decode($userJson, true);

        $userResponse = m::mock('Psr\Http\Message\ResponseInterface');
        $userResponse->shouldReceive('getBody')->andReturn($userJson);
        $userResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $userResponse->shouldReceive('getStatusCode')->andReturn(200);

        $client = m::mock('GuzzleHttp\ClientInterface');
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
     * @expectedException League\OAuth2\Client\Provider\Exception\IdentityProviderException
     **/
    public function testExceptionThrownWhenErrorResponse()
    {
        $postResponse = m::mock('Psr\Http\Message\ResponseInterface');

        $postResponse->shouldReceive('getBody')->andReturn('{"type":"error.list","request_id":"anvt4on87prigma30i8g","errors":[{"code":"server_error","message":"Server Error"}]}');
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $postResponse->shouldReceive('getStatusCode')->andReturn(401);

        $client = m::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')
            ->times(1)
            ->andReturn($postResponse);
        $this->provider->setHttpClient($client);
        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
    }

    /**
     * @expectedException League\OAuth2\Client\Provider\Exception\IdentityProviderException
     **/
    public function testExceptionThrownWhenStatusNotSuccess()
    {
        $postResponse = m::mock('Psr\Http\Message\ResponseInterface');

        $postResponse->shouldReceive('getBody')->andReturn('');
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $postResponse->shouldReceive('getReasonPhrase')->andReturn('Internal Server Error');
        $postResponse->shouldReceive('getStatusCode')->andReturn(500);

        $client = m::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')
            ->times(1)
            ->andReturn($postResponse);
        $this->provider->setHttpClient($client);
        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
    }
}
