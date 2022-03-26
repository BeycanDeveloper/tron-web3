<?php

namespace Beycan\TronWeb3;

use Exception;
use \IEXBase\TronAPI\Tron;
use \IEXBase\TronAPI\Provider\HttpProvider;
use \IEXBase\TronAPI\Exception\TronException;


final class Connection
{
    public $tron;

    private $networks = [
        "mainnet" => [
            "host" => "https://api.trongrid.io/",
            "explorer" => "https://tronscan.io/"
        ],
        "testnet" => [
            "host" => "https://api.nileex.io/",
            "explorer" => "https://nile.tronscan.org/"
        ]
    ];

    public $network;

    public static $connection = null;

    /**
     * @param string $network
     * @throws Exception
     */
    public function __construct(string $network) 
    {
        if (!array_key_exists($network, $this->networks)) {
            throw new Exception('You entered an invalid network!');
        }

        $this->network = (object) $this->networks[$network];

        $fullNode = new HttpProvider($this->network->host);
        $solidityNode = new HttpProvider($this->network->host);
        $eventServer = new HttpProvider($this->network->host);

        try {
            $this->tron = new Tron($fullNode, $solidityNode, $eventServer);
        } catch (TronException $e) {
            throw new Exception($e->getMessage());
        }


        self::$connection = $this;
    }

    /**
     * @param string $method
     * @param array $params
     * @return object|null
     * @throws Exception
     */
    public function __call(string $method, array $params = [])
    {
        if (preg_match('/^[a-zA-Z0-9]+$/', $method) === 1) {
            return json_decode(json_encode($this->tron->$method(...$params)), false);
        } else {
            throw new Exception('Invalid method name');
        }
    }

    /**
     * @return Connection|null
     */
    public static function getConnection() : ?Connection
    {
        return self::$connection;
    }
}