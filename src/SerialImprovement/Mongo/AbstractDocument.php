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

    const OPT_ONLY_MODIFIED = 0010;

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

    /** @var array A hash where keys are field names and the value is just to signify a change occurred */
    protected $modifiedAttributes = [];

    /** @var AbstractDocument[] */
    protected $subscribers = [];

    public function __construct()
    {
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

        if ($this->get($name) !== $value) {
            $this->modifiedAttributes[$name] = 1;
            $this->publish();
        }

        if ($value instanceof AbstractDocument) {
            $value->subscribe($name, $this);
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

                    $this->{$field} = new $embeddedClass();
                    $this->{$field}->fromDocument($document[$field]);
                } elseif(is_array($document[$field])) {
                    // load documents in single dimensional arrays
                    $newArr = [];
                    foreach ($document[$field] as $key => $value) {
                        if (is_array($value) && isset($value[self::INTERNAL_EMBEDDED_CLASS_FIELD])) {
                            $embeddedClass = $value[self::INTERNAL_EMBEDDED_CLASS_FIELD];
                            $newArr[$key] = new $embeddedClass();
                            $newArr[$key]->fromDocument($value);
                        } else {
                            $newArr[$key] = $value;
                        }
                    }
                    $this->{$field} = $newArr;
                } else {
                    $this->{$field} = $document[$field];
                }
            }
        }

        // reset modified attributes so only changes are detected
        $this->modifiedAttributes = [];
    }

    /**
     * @param int $options A bitmask of options from the self::OPT_* family
     *
     * @return array
     */
    public function toDocument(int $options = 0)
    {
        if (!isset($this->attributes[self::INTERNAL_FIELD_DATE])) {
            $this->set(self::INTERNAL_FIELD_DATE, new UTCDatetime(round(microtime(true) * 1000)));
        }

        if ($options & self::OPT_ONLY_MODIFIED) {
            $properties = [];
            foreach ($this->modifiedAttributes as $field => $modified) {
                $properties[$field] = $this->get($field);
            }
        } else {
            $properties = $this->attributes;
        }

        $doc = [];
        foreach ($properties as $key => $value) {
            if ($value instanceof AbstractDocument) {
                $embedded = $value->toDocument();
                if (!empty($embedded)) {
                    $doc[$key] = $embedded;
                    $doc[$key][self::INTERNAL_EMBEDDED_CLASS_FIELD] = get_class($value);
                }
            } elseif(is_array($value)) {
                // embed documents in single dimensional arrays
                $newArr = [];
                foreach ($value as $deepKey => $deepValue) {
                    if ($deepValue instanceof AbstractDocument) {
                        $deepValueEmbedded = $deepValue->toDocument();
                        $newArr[$deepKey] = $deepValueEmbedded;
                        $newArr[$deepKey][self::INTERNAL_EMBEDDED_CLASS_FIELD] = get_class($deepValue);
                    } else {
                        $newArr[$deepKey] = $deepValue;
                    }
                }
                $doc[$key] = $newArr;
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
        $result = static::getCollection()
            ->insertOne($this->toDocument());

        $this->attributes[self::INTERNAL_PRIMARY_KEY] = $result->getInsertedId();
    }

    public function update($options = [])
    {
        $this->set(self::INTERNAL_FIELD_UPDATED_DATE, new UTCDatetime());
        $update = $this->toDocument(self::OPT_ONLY_MODIFIED);

        // update only changed nested fields
        foreach (array_keys($this->modifiedAttributes) as $modifiedField) {
            if ($this->{$modifiedField} instanceof AbstractDocument) {
                $values = $update[$modifiedField];
                unset($update[$modifiedField]);

                $deepModified = $this->{$modifiedField}->modifiedAttributes;
                foreach (array_keys($deepModified) as $deepKey) {
                    $update[$modifiedField . '.' . $deepKey] = $values[$deepKey];
                }
            }
        }

        // remove the _id from the update
        unset($update[self::INTERNAL_PRIMARY_KEY]);

        static::getCollection()
            ->updateOne([self::INTERNAL_PRIMARY_KEY => $this->_id], ['$set' => $update], $options);
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

    //----------------------------
    // Change Detector
    //----------------------------

    protected function wasModified($field)
    {
        $this->modifiedAttributes[$field] = 1;
    }

    protected function subscribe(string $field, AbstractDocument $document)
    {
        $this->subscribers[$field] = $document;
    }

    /**
     * Update subscribers
     */
    protected function publish()
    {
        foreach ($this->subscribers as $field => $subscription) {
            $subscription->wasModified($field);
        }
    }
}
