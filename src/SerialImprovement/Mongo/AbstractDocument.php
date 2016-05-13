<?php


namespace SerialImprovement\Mongo;


use MongoDB\BSON\ObjectID;
use MongoDB\BSON\UTCDatetime;
use MongoDB\Client;

/**
 * Class AbstractDocument
 * @package SerialImprovement\Mongo
 *
 * @param _id string
 * @param createdDate UTCDatetime
 * @param updatedDate UTCDatetime
 */
abstract class AbstractDocument
{
    private $attributes = [];
    private $fields = [];
    private $fieldTypes = [];

    const TYPE_DATE = 'date';
    const INTERNAL_FIELD_DATE = 'createdDate';
    const INTERNAL_PRIMARY_KEY = '_id';
    const INTERNAL_FIELD_UPDATED_DATE = 'updatedDate';

    /** @var Connector */
    protected $connector;

    /**
     * Post constructor.
     * @param Connector $connector
     */
    public function __construct(Connector $connector)
    {
        $this->connector = $connector;

        $this->fields = $this->getDefaultFields();
        $this->fields[] = self::INTERNAL_PRIMARY_KEY;
        $this->fields[] = self::INTERNAL_FIELD_DATE;
        $this->fields[] = self::INTERNAL_FIELD_UPDATED_DATE;
    }

    public function __set($name, $value)
    {
        if (!in_array($name, $this->fields)) {
            throw new \RuntimeException("$name does not exist in fields");
        }

        $this->attributes[$name] = $value;
    }

    public function __get($name)
    {
        return $this->attributes[$name];
    }

    public function fromDocument($document)
    {
        foreach ($this->fields as $field) {
            if (isset($document[$field])) {
                $this->{$field} = $document[$field];
            }
        }
    }

    public function toDocument()
    {
        if (!isset($this->attributes[self::INTERNAL_FIELD_DATE])) {
            $this->attributes[self::INTERNAL_FIELD_DATE] = new UTCDatetime(round(microtime(true) * 1000));
        }

        if (!isset($this->attributes[self::INTERNAL_PRIMARY_KEY])) {
            $this->attributes[self::INTERNAL_PRIMARY_KEY] = new ObjectID();
        }

        return $this->attributes;
    }

    public function toArray()
    {
        $array = [];
        foreach ($this->attributes as $key => $value) {
            if ($value instanceof ObjectID) {
                $array[$key] = $value->__toString();
            } else if ($value instanceof UTCDatetime) {
                $array[$key] = $value->__toString();
            } else {
                $array[$key] = $value;
            }
        }
        return $array;
    }

    public function insert()
    {
        $name = $this->getDocumentName();

        $this->connector
            ->getMongoClient()
            ->selectDatabase($this->getDatabaseName())
            ->selectCollection($name . 's')
            ->insertOne($this->toDocument());
    }

    public function update()
    {
        $name = $this->getDocumentName();

        $update = $this->toDocument();
        $update[self::INTERNAL_FIELD_UPDATED_DATE] = new UTCDatetime(round(microtime(true) * 1000));

        $this->connector
            ->getMongoClient()
            ->selectDatabase($this->getDatabaseName())
            ->selectCollection($name . 's')
            ->updateOne([self::INTERNAL_PRIMARY_KEY => $this->_id], ['$set' => $update]);
    }

    /**
     * @param Connector $connector
     * @param $criteria
     * @param $options
     * @return AbstractDocument[]
     */
    public static function find(Connector $connector, array $criteria, array $options): array
    {
        $name = static::getDocumentName();
        $fqn = static::class;

        /** @var AbstractDocument $doc */
        $doc = new $fqn($connector);

        $cursor = $connector
            ->getMongoClient()
            ->selectDatabase($doc->getDatabaseName())
            ->selectCollection($name . 's')
            ->find($criteria, $options);

        $results = [];
        foreach ($cursor as $item) {
            /** @var AbstractDocument $doc */
            $doc = new $fqn($connector);
            $doc->fromDocument($item);
            $results[] = $doc;
        }

        return $results;
    }

    public static function findOne(Connector $connector, array $criteria, array $options): AbstractDocument
    {
        $name = static::getDocumentName();
        $fqn = static::class;

        /** @var AbstractDocument $doc */
        $doc = new $fqn($connector);

        $item = $connector
            ->getMongoClient()
            ->selectDatabase($doc->getDatabaseName())
            ->selectCollection($name . 's')
            ->findOne($criteria, $options);

        if ($item === null) {
            throw new \RuntimeException('Document not found');
        }

        $doc->fromDocument($item);

        return $doc;
    }

    /**
     * @return string
     */
    private static function getDocumentName()
    {
        $name = strtolower((new \ReflectionClass(static::class))->getShortName());
        return $name;
    }

    /**
     * Should return the names of the fields you wish your document to contain
     *
     * @return string[]
     */
    abstract protected function getDefaultFields(): array;

    /**
     * Should return the name of the database to store this object in
     *
     * @return string
     */
    abstract protected function getDatabaseName(): string;
}
