<?php
namespace SerialImprovement\Mongo;

use MongoDB\BSON\ObjectID;
use MongoDB\BSON\UTCDatetime;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;
use MongoDB\Model\BSONDocument;

/**
 * Class AbstractDocument
 *
 * @property string _id
 * @property UTCDatetime createdDate
 * @property UTCDatetime updatedDate
 *
 * @package SerialImprovement\Mongo
 */
abstract class AbstractDocument
{
    protected $attributes = [];
    protected $fields = [];

    const INTERNAL_FIELD_DATE = 'createdDate';
    const INTERNAL_PRIMARY_KEY = '_id';
    const INTERNAL_FIELD_UPDATED_DATE = 'updatedDate';
    const INTERNAL_EMBEDDED_CLASS_FIELD = 'embeddedClass';

    // ensures that BSONArrays are cast to regular php arrays on find operations
    const ARRAY_TYPE_MAP = [
        'typeMap' => [
            'root' => 'array',
            'array' => 'array',
            'document' => 'array',
        ]
    ];

    /** @var Client */
    protected static $client;

    public function __construct()
    {
        static::assertClientSet();

        $this->fields = $this->getDefaultFields();
        $this->fields[] = self::INTERNAL_PRIMARY_KEY;
        $this->fields[] = self::INTERNAL_FIELD_DATE;
        $this->fields[] = self::INTERNAL_FIELD_UPDATED_DATE;
    }

    protected static function getClient(): Client
    {
        static::assertClientSet();
        return static::$client;
    }

    public static function setClient(Client $client)
    {
        static::$client = $client;
    }

    protected static function assertClientSet()
    {
        if (!(static::$client instanceof Client)) {
            throw new \RuntimeException('Client was not set. Please use AbstractDocument::setClient');
        }
    }

    public function __set($name, $value)
    {
        $this->set($name, $value);
    }

    /**
     * @param string $name
     * @param mixed $value
     */
    public function set(string $name, $value)
    {
        if (!in_array($name, $this->fields)) {
            throw new \RuntimeException("$name does not exist in fields");
        }

        $this->attributes[$name] = $value;
    }

    public function __get($name)
    {
        return $this->get($name);
    }

    /**
     * @param string $name
     * @param mixed $default
     * @return mixed|null
     */
    public function get(string $name, $default = null)
    {
        if (!isset($this->attributes[$name])) {
            return $default;
        }

        return $this->attributes[$name];
    }

    public function fromDocument($document)
    {
        foreach ($this->fields as $field) {
            if (isset($document[$field])) {

                // could potentially be an embedded document
                $couldBeEmbedded = is_array($document[$field]) ||
                    $document[$field] instanceof BSONDocument;

                // definitely is embedded
                $isEmbedded = $couldBeEmbedded &&
                    isset($document[$field][self::INTERNAL_EMBEDDED_CLASS_FIELD]);

                if ($isEmbedded) {
                    $embeddedClass = $document[$field][self::INTERNAL_EMBEDDED_CLASS_FIELD];

                    $this->{$field} = new $embeddedClass($this->client);
                    $this->{$field}->fromDocument($document[$field]);
                } else {
                    $this->{$field} = $document[$field];
                }
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

        $doc = [];
        foreach ($this->attributes as $key => $value) {
            if ($value instanceof AbstractDocument) {
                $doc[$key] = $value->toDocument();
                $doc[$key][self::INTERNAL_EMBEDDED_CLASS_FIELD] = get_class($value);
            } else {
                $doc[$key] = $value;
            }
        }

        return $doc;
    }

    public function toArray()
    {
        $array = [];
        foreach ($this->attributes as $key => $value) {
            if ($value instanceof ObjectID) {
                $array[$key] = $value->__toString();
            } else if ($value instanceof UTCDatetime) {
                $array[$key] = $value->__toString();
            } else if ($value instanceof AbstractDocument) {
                $array[$key] = $value->toArray();
            } else {
                $array[$key] = $value;
            }
        }
        return $array;
    }

    public function insert()
    {
        static::getCollection()
            ->insertOne($this->toDocument());
    }

    public function update()
    {
        $update = $this->toDocument();
        $update[self::INTERNAL_FIELD_UPDATED_DATE] = new UTCDatetime(round(microtime(true) * 1000));

        // remove the _id from the update
        unset($update[self::INTERNAL_PRIMARY_KEY]);

        static::getCollection()
            ->updateOne([self::INTERNAL_PRIMARY_KEY => $this->_id], ['$set' => $update]);
    }

    /**
     * @param $criteria
     * @param $options
     * @return AbstractDocument[]
     */
    public static function find(array $criteria = [], array $options = []): array
    {
        $fqn = static::class;

        $cursor = static::getCollection()
            ->find($criteria, $options + self::ARRAY_TYPE_MAP);

        $results = [];
        foreach ($cursor as $item) {
            /** @var AbstractDocument $doc */
            $doc = new $fqn();
            $doc->fromDocument($item);
            $results[] = $doc;
        }

        return $results;
    }

    public static function findOne(array $criteria = [], array $options = []): AbstractDocument
    {
        $fqn = static::class;

        /** @var AbstractDocument $doc */
        $doc = new $fqn();

        $item = static::getCollection()
            ->findOne($criteria, $options + self::ARRAY_TYPE_MAP);


        if ($item === null) {
            throw new \RuntimeException('Document not found');
        }

        $doc->fromDocument($item);

        return $doc;
    }

    /**
     * Delete this object from the database
     */
    public function delete()
    {
        static::getCollection()
            ->deleteOne([self::INTERNAL_PRIMARY_KEY => $this->_id]);
    }

    public static function getDatabase(): Database
    {
        return static::getClient()
            ->selectDatabase(static::getDatabaseName());
    }

    public static function getCollection(): Collection
    {
        return static::getDatabase()
            ->selectCollection(static::getCollectionName());
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
    abstract public static function getDatabaseName(): string;

    /**
     * Should return the name of the collection to store this object in
     *
     * By default will use the lowercase version of the implementing class
     *
     * @return string
     */
    public static function getCollectionName(): string
    {
        return static::getDocumentName() . 's';
    }
}
