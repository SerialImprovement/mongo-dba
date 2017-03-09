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
            'banana',
            'address'
        ];
    }

    /**
     * Should return the name of the database to store this object in
     *
     * @return string
     */
    protected static function getDatabaseName(): string
    {
        return 'test';
    }
}

class AddressDocument extends AbstractDocument {
    protected function getDefaultFields(): array
    {
        return [
            'line1',
            'line2',
            'state',
            'city',
            'zip'
        ];
    }

    protected static function getDatabaseName(): string
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
     * EMBEDDED TESTS
     */

    public function testFromDocumentEmbedded()
    {
        $doc = new ConcreteDocument($this->buildMongoClientMock());

        $document = [
            'banana' => 'unpeeled',
            'address' => [
                'line1' => 'test',
                'line2' => 'test',
                'state' => 'cambridge',
                'city' => 'MA',
                'zip' => '12432',
                AbstractDocument::INTERNAL_EMBEDDED_CLASS_FIELD => 'SerialImprovement\Mongo\AddressDocument',
            ]
        ];

        $doc->fromDocument($document);

        $this->assertEquals('unpeeled', $doc->banana);
        $this->assertEquals('test', $doc->address->line1);
    }

    public function testToDocumentEmbedded()
    {
        $doc = new ConcreteDocument($this->buildMongoClientMock());
        $doc->banana = 'unpeeled';

        $address = new AddressDocument($this->buildMongoClientMock());
        $address->line1 = 'test';
        $address->line2 = 'test';
        $address->state = 'MA';
        $address->city = 'cambridge';
        $address->zip = '12432';

        $doc->address = $address;

        $actual = $doc->toDocument();
        $actual = $actual['address'];

        $this->assertArrayHasKey(
            AbstractDocument::INTERNAL_EMBEDDED_CLASS_FIELD,
            $actual,
            'address should have the special embedded class field'
        );

        $this->assertSame(
            AddressDocument::class,
            $actual[AbstractDocument::INTERNAL_EMBEDDED_CLASS_FIELD],
            'address key should contain a reference to its model class'
        );

        $this->assertArrayHasKey('line1', $actual);
        $this->assertArrayHasKey('createdDate', $actual, 'a date should be added if not specified');
        $this->assertInstanceOf('MongoDB\BSON\UTCDatetime', $actual['createdDate']);

        $this->assertArrayHasKey('_id', $actual, 'the _id should be added if not specified');
        $this->assertInstanceOf('MongoDB\BSON\ObjectID', $actual['_id']);
    }

    public function testToArrayEmbedded()
    {
        $doc = new ConcreteDocument($this->buildMongoClientMock());
        $doc->banana = 'peeled';

        $address = new AddressDocument($this->buildMongoClientMock());
        $address->line1 = 'test';
        $address->line2 = 'test';
        $address->state = 'MA';
        $address->city = 'cambridge';
        $address->zip = '12432';

        $doc->address = $address;

        $actual = $doc->toArray();

        $this->assertArrayHasKey('address', $actual);
        $this->assertArrayHasKey('line1', $actual['address']);
        $this->assertArrayHasKey('line2', $actual['address']);
        $this->assertArrayHasKey('state', $actual['address']);
        $this->assertArrayHasKey('city', $actual['address']);
        $this->assertArrayHasKey('zip', $actual['address']);

        $this->assertArrayNotHasKey(
            AbstractDocument::INTERNAL_EMBEDDED_CLASS_FIELD,
            $actual['address'],
            'Should not return the special embedded model class name'
        );
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
