<?php

namespace SerialImprovement\Mongo;

/**
 * @property string className
 * @property string localKey
 * @property string foreignKey
 * @property string foreignKeyValue
 *
 * Class HasOne
 * @package SerialImprovement\Mongo
 */
class HasOne extends AbstractReference
{
    private $document;

    public function __construct(
        string $foreignKey = AbstractDocument::INTERNAL_PRIMARY_KEY
    ) {
        parent::__construct();
        $this->foreignKey = $foreignKey;
    }

    public function associate(AbstractDocument $document)
    {
        if ($document->_id === null) {
            throw new \InvalidArgumentException('given AbstractDocument must have an _id');
        }

        $this->foreignKeyValue = $document->{$this->foreignKey};
    }

    /**
     * Should return the names of the fields you wish your document to contain
     *
     * @return string[]
     */
    protected function getDefaultFields(): array
    {
        return [
            'className',
            'localKey',
            'foreignKey',
            'foreignKeyValue',
        ];
    }

    /**
     * Should return the name of the database to store this object in
     *
     * @return string
     */
    public static function getDatabaseName(): string
    {
        return '__reference';
    }

    public function resolve()
    {
        if ($this->document) {
            return $this->document;
        } else {
            $this->document = $this->fetch();
            return $this->document;
        }
    }

    protected function fetch()
    {
        return call_user_func($this->className . '::findOne', [
            $this->foreignKey => $this->foreignKeyValue
        ]);
    }
}
