<?php

namespace SimpleNeo4j;

class Client
{
    const DEFAULT_CONFIG = [
        'host' => 'localhost',
        'port' => 7474,
        'secure' => false,
        'protocol' => 'http',
    ];

    /**
     * @var \GuzzleHttp\Client
     */
    private $_http_client;

    /**
     * @var array
     */
    private $_config;

    /**
     * @var array
     */
    private $_query_queue = [];

    /**
     * @param array $config
     * @param \GuzzleHttp\Client|null $http_client
     */
    public function __construct( array $config = [], \GuzzleHttp\Client $http_client = null )
    {
        $this->_setConfigFromOptions($config);
        $this->_http_client = $http_client ?: new \GuzzleHttp\Client();
    }

    /**
     * @return string
     */
    public function getHost()
    {
        return $this->_config['host'];
    }

    /**
     * @return int
     */
    public function getPort()
    {
        return $this->_config['port'];
    }

    /**
     * @return bool
     */
    public function isSecure()
    {
        return $this->_config['secure'];
    }

    /**
     * @return string
     */
    public function getProtocol()
    {
        return $this->_config['protocol'];
    }

    public function executeQuery( string $query, array $params = [] ) {

    }

    /**
     * @param array $config
     */
    private function _setConfigFromOptions( array $config = [] )
    {
        $this->_config = array_merge(self::DEFAULT_CONFIG, $config);

        if (!isset($config['port']) && isset($config['secure'])) {
            $this->_config['port'] = $config['secure'] ? 7473 : 7474;
        }

        if (!isset($config['protocol']) && $this->_config['secure']) {
            $this->_config['protocol'] = 'https';
        }
    }
}
