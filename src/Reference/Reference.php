<?php

namespace Joli\Jane\Reference;

/**
 * Deal with Json Reference (only support internal pointer for the moment)
 *
 * @package Joli\Jane\Reference
 */
class Reference
{
    /**
     * @var string
     */
    private $scheme;

    /**
     * @var string
     */
    private $host;

    /**
     * @var integer
     */
    private $port;

    /**
     * @var string
     */
    private $path;

    /**
     * @var string
     */
    private $query;

    /**
     * @var string
     */
    private $fragment;

    /**
     * @param $ref
     */
    public function __construct($ref)
    {
        $this->scheme   = parse_url($ref, PHP_URL_SCHEME);
        $this->host     = parse_url($ref, PHP_URL_HOST);
        $this->port     = parse_url($ref, PHP_URL_PORT);
        $this->path     = parse_url($ref, PHP_URL_PATH);
        $this->query    = parse_url($ref, PHP_URL_QUERY);
        $this->fragment = parse_url($ref, PHP_URL_FRAGMENT);

        // Differentiate, root fragment and none existent fragment
        if ($this->fragment === null && preg_match('/#/', $ref)) {
            $this->fragment = '';
        }
    }

    /**
     * @return string
     */
    public function getFragment()
    {
        return $this->fragment;
    }

    /**
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @return int
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * @return string
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * @return string
     */
    public function getScheme()
    {
        return $this->scheme;
    }

    /**
     * Whether this reference is relative to the current document
     *
     * @return bool
     */
    public function isRelative()
    {
        return $this->host === null;
    }

    /**
     * Whether this reference is in the current document
     *
     * @return bool
     */
    public function isInCurrentDocument()
    {
        return $this->isRelative() && $this->path === null;
    }

    /**
     * Whether this reference has fragment
     *
     * @return bool
     */
    public function hasFragment()
    {
        return $this->fragment !== null;
    }
}
 