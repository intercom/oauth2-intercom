# Intercom Provider for OAuth 2.0 Client

[![Build Status](https://travis-ci.org/intercom/oauth2-intercom.svg?branch=master)](https://travis-ci.org/intercom/oauth2-intercom)

This package provides Intercom OAuth 2.0 support for the PHP League's [OAuth 2.0 Client](https://github.com/thephpleague/oauth2-client).

## Installation

To install, use composer:

```
composer require intercom/oauth2-intercom
```

## Usage

Usage is the same as The League's OAuth client, using `\Intercom\OAuth2\Client\Provider\Intercom` as the provider.

### Authorization Code Flow

```php
$provider = new Intercom\OAuth2\Client\Provider\Intercom([
    'clientId'          => '{intercom-client-id}',
    'clientSecret'      => '{intercom-client-secret}',
    'redirectUri'       => 'https://example.com/callback-url'
]);

if (!isset($_GET['code'])) {

    // If we don't have an authorization code then get one
    $authUrl = $provider->getAuthorizationUrl();
    $_SESSION['oauth2state'] = $provider->getState();
    header('Location: '.$authUrl);
    exit;

// Check given state against previously stored one to mitigate CSRF attack
} elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {

    unset($_SESSION['oauth2state']);
    exit('Invalid state');

} else {

    // Get access token using the authorization code grant
    $token = $provider->getAccessToken('authorization_code', [
        'code' => $_GET['code']
    ]);

    // Optional: Now you have token, you can look up a users profile data
    try {

        // We got an access token, let's now get the user's details
        $user = $provider->getResourceOwner($token);

        // Use these details to create a new profile
        printf('Hello %s', $user->getName());

    } catch (Exception $e) {

        exit('Failed to get user details');
    }

    echo $token;

}
```


## Refreshing a Token

Intercom's OAuth implementation does not use refresh tokens. Access tokens are valid until a user revokes access manually, or until an app deauthorizes itself.

## Testing

``` bash
$ ./vendor/bin/phpunit
```

## License

The Apache 2 License. Please see [License File](https://github.com/intercom/oauth2-intercom/blob/master/LICENSE) for more information.