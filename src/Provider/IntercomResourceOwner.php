<?php

namespace Intercom\OAuth2\Client\Provider;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;

class IntercomResourceOwner implements ResourceOwnerInterface
{
    /**
     * Raw response
     *
     * @var array
     */
    protected $response;

    /**
     * Creates new resource owner.
     *
     * @param array  $response
     */
    public function __construct(array $response = array())
    {
        $this->response = $response;
    }

    /**
     * Get resource owner id
     *
     * @return string
     */
    public function getId()
    {
        return $this->response['id'] ?: null;
    }

    /**
     * Get resource owner name
     *
     * @return string
     */
    public function getName()
    {
        return $this->response['name'] ?: null;
    }

    /**
     * Get resource owner email
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->response['email'] ?: null;
    }

    /**
     * Get resource owner avatar URL
     *
     * @return string
     */
    public function getAvatarUrl()
    {
        return $this->response['avatar']['image_url'] ?: null;
    }

    /**
     * Return all of the owner details available as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->response;
    }
}
