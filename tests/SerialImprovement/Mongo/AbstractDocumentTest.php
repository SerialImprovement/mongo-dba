<?php


namespace SerialImprovement\Mongo;


use MongoDB\Client;

/**
 * Because we are testing an abstract class we create a simple
 * concrete version with test values
 *
 * Class ConcreteDocument
 * @property string banana
 * @package Bigtallbill\DarkWeb\Documents
 */
class ConcreteDocument extends AbstractDocument {
    /**
     * Should return the names of the fields you wish your document to contain
     *
     * @return string[]
     */
    protected function getDefaultFields(): array
    {
        return [
            'banana'
        ];
    }

    /**
     * Should return the name of the database to store this object in
     *
     * @return string
     */
    protected function getDatabaseName(): string
    {
        return 'test';
    }
}

class AbstractDocumentTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \RuntimeException
     */
    public function testCannotSetUnknownField()
    {
        $doc = new ConcreteDocument($this->buildMongoClientMock());
        $doc->wally = 'does not work';
    }

    public function testFromDocument()
    {
        $doc = new ConcreteDocument($this->buildMongoClientMock());

        $document = [
            'banana' => 'unpeeled'
        ];

        $doc->fromDocument($document);

        $this->assertEquals('unpeeled', $doc->banana);
    }

    public function testToDocument()
    {
        $doc = new ConcreteDocument($this->buildMongoClientMock());

        $document = [
            'banana' => 'unpeeled'
        ];

        $doc->fromDocument($document);
        $actual = $doc->toDocument();

        $this->assertArrayHasKey('banana', $actual);
        $this->assertArrayHasKey('createdDate', $actual, 'a date should be added if not specified');
        $this->assertInstanceOf('MongoDB\BSON\UTCDatetime', $actual['createdDate']);

        $this->assertArrayHasKey('_id', $actual, 'the _id should be added if not specified');
        $this->assertInstanceOf('MongoDB\BSON\ObjectID', $actual['_id']);
    }

    public function testToArray()
    {
        $doc = new ConcreteDocument($this->buildMongoClientMock());

        $doc->banana = 'peeled';
        $actual = $doc->toArray();

        $this->assertArrayHasKey('banana', $actual);
        $this->assertEquals('peeled', $actual['banana']);
    }

    /**
     * @return Connector
     */
    private function buildMongoClientMock()
    {
        /** @var Client $clientMock */
        $clientMock = $this->getMockBuilder('MongoDB\Client')->getMock();
        return new Connector($clientMock);
    }
}
