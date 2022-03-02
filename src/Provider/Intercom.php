<?php

namespace Intercom\OAuth2\Client\Provider;

use JetBrains\PhpStorm\ArrayShape;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Http\Message\ResponseInterface;

class Intercom extends AbstractProvider
{
    /**
     * @var boolean By default, Intercom strategy rejects users with unverified email addresses.
     */
    protected bool $verifyEmail = true;

    /**
     * Get authorization url to begin OAuth flow
     *
     * @return string
     */
    public function getBaseAuthorizationUrl(): string
    {
        return 'https://app.intercom.com/oauth';
    }

    /**
     * Get access token url to retrieve token
     *
     * @param array $params
     * @return string
     */
    public function getBaseAccessTokenUrl(array $params): string
    {
        return 'https://api.intercom.io/auth/eagle/token';
    }

    /**
     * Get provider url to fetch user details
     *
     * @param  AccessToken $token
     *
     * @return string
     */
    public function getResourceOwnerDetailsUrl(AccessToken $token): string
    {
        return 'https://api.intercom.io/me';
    }

    /**
     * Get the default scopes used by this provider.
     *
     * This should not be a complete list of all scopes, but the minimum
     * required for the provider user interface!
     *
     * @return array
     */
    protected function getDefaultScopes(): array
    {
        return [];
    }

    /**
     * Check a provider response for errors.
     * @throws IdentityProviderException
     */
    protected function checkResponse(ResponseInterface $response, $data): void
    {
        $statusCode = $response->getStatusCode();
        if (empty($data['errors']) && $statusCode === 200) {
            return;
        }

        throw new IdentityProviderException(
            $data['errors'][0]['message'] ?: $response->getReasonPhrase(),
            $statusCode,
            $response
        );
    }

    /**
     * Returns the default headers used by this provider.
     *
     * Typically this is used to set 'Accept' or 'Content-Type' headers.
     *
     * @return array
     */
    #[ArrayShape(['Accept' => "string", 'User-Agent' => "string"])]
    protected function getDefaultHeaders(): array
    {
        return [ 'Accept' => 'application/json', 'User-Agent' => 'league/oauth2-intercom/2.0.0' ];
    }


    /**
     * Returns the authorization headers used by this provider.
     *
     * @param  mixed|null $token Either a string or an access token instance
     * @return array
     */
    protected function getAuthorizationHeaders($token = null)
    {
        return [ 'Authorization' => 'Basic ' . base64_encode("$token:") ];
    }

    /**
     * Requests resource owner details.
     *
     * @param AccessToken $token
     * @return mixed
     * @throws IdentityProviderException
     */
    protected function fetchResourceOwnerDetails(AccessToken $token): mixed
    {
        $url = $this->getResourceOwnerDetailsUrl($token);

        $request = $this->getAuthenticatedRequest(self::METHOD_GET, $url, $token);

        return $this->getParsedResponse($request);
    }

    /**
     * Generate a user object from a successful user details request.
     *
     * @param array $response
     * @param AccessToken $token
     * @return IntercomResourceOwner|ResourceOwnerInterface
     */
    protected function createResourceOwner(
        array $response,
        AccessToken $token
    ): IntercomResourceOwner|ResourceOwnerInterface {
        $validatedResponse = $response;
        if ($this->verifyEmail && !$response['email_verified']) {
            $validatedResponse = [];
        }
        return new IntercomResourceOwner($validatedResponse);
    }
}
