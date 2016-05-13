<?php


namespace SerialImprovement\Mongo;


use MongoDB\Client;

class Connector
{
    /** @var Client */
    private $mongoClient;

    /**
     * Connector constructor.
     * @param Client $mongoClient
     */
    public function __construct(Client $mongoClient)
    {
        $this->mongoClient = $mongoClient;
    }

    /**
     * @return Client
     */
    public function getMongoClient(): Client
    {
        return $this->mongoClient;
    }
}
