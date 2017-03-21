<?php


namespace SerialImprovement\Mongo;


use MongoDB\BSON\ObjectID;
use MongoDB\BSON\UTCDatetime;
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
    private $attributes = [];
    private $fields = [];
    private $fieldTypes = [];

    const TYPE_DATE = 'date';
    const INTERNAL_FIELD_DATE = 'createdDate';
    const INTERNAL_PRIMARY_KEY = '_id';
    const INTERNAL_FIELD_UPDATED_DATE = 'updatedDate';
    const INTERNAL_EMBEDDED_CLASS_FIELD = 'embeddedClass';

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

                // could potentially be an embedded document
                $couldBeEmbedded = is_array($document[$field]) ||
                    $document[$field] instanceof BSONDocument;

                // definitely is embedded
                $isEmbedded = $couldBeEmbedded &&
                    isset($document[$field][self::INTERNAL_EMBEDDED_CLASS_FIELD]);

                $isReference = isset($document['$ref']);

                if ($isEmbedded) {
                    $embeddedClass = $document[$field][self::INTERNAL_EMBEDDED_CLASS_FIELD];

                    $this->{$field} = new $embeddedClass($this->connector);
                    $this->{$field}->fromDocument($document[$field]);
                } elseif ($isReference) {
                    $this->{$field} = $this->resolveReference($document[$field]);
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
                $doc[$key] = $value->toEmbed();
            } else {
                $doc[$key] = $value;
            }
        }

        return $doc;
    }

    /**
     * Gets the embed version of this document
     *
     * @return array
     */
    public function toEmbed(): array
    {
        $embed = $this->toDocument();
        $embed[self::INTERNAL_EMBEDDED_CLASS_FIELD] = get_class($this);

        return $embed;
    }

    /**
     * Gets a DBRef for this document
     *
     * @return array
     */
    public function toReference(): array
    {
        if (!isset($this->attributes[self::INTERNAL_PRIMARY_KEY])) {
            throw new \RuntimeException('No primary key set, insert or load object first');
        }

        $reference = [
            '$ref' => $this->getDocumentName() . 's',
            '$id' => $this->{self::INTERNAL_PRIMARY_KEY},
            '$db' => $this->getDatabaseName(),
            'siClass' => get_class($this),
        ];

        return $reference;
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

    protected function resolveReference($reference)
    {
        if (!isset($reference['$ref'])) {
            throw new \RuntimeException('This object is not a reference');
        }

        $class = $reference['siClass'];

        return call_user_func($class . '::findOne', [
            self::INTERNAL_PRIMARY_KEY => $reference['$id']
        ], []);
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
     * Delete this object from the database
     */
    public function delete()
    {
        $name = static::getDocumentName();

        $this->connector
            ->getMongoClient()
            ->selectDatabase($this->getDatabaseName())
            ->selectCollection($name . 's')
            ->deleteOne([self::INTERNAL_PRIMARY_KEY => $this->_id]);
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
