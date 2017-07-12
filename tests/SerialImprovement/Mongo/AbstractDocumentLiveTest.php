<?php
namespace Testing\Live;

use MongoDB\BSON\ObjectID;
use MongoDB\Client;
use SerialImprovement\Mongo\AbstractDocument;

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
    public static function getDatabaseName(): string
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

    public static function getDatabaseName(): string
    {
        return 'test';
    }
}

class AbstractDocumentLiveTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        AbstractDocument::setClient($this->buildMongoClient());
        parent::setUp();
    }

    protected function tearDown()
    {
        $this->buildMongoClient()->selectDatabase('test')->drop();
        parent::tearDown();
    }

    public function testInsertAndFineOne()
    {
        $address = new AddressDocument();
        $address->fromDocument([
            'line1' => 'test',
            'line2' => 'test',
            'state' => 'cambridge',
            'city' => 'MA',
            'zip' => '12432',
        ]);

        $address->insert();

        /** @var AddressDocument $addressFromDb */
        $addressFromDb = AddressDocument::findOne(['_id' => new ObjectID($address->_id)]);

        $this->assertNotNull($addressFromDb);
        $this->assertEquals('cambridge', $addressFromDb->state);
        $this->assertEquals('12432', $addressFromDb->zip);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testFindOneThrowsWhenNotFound()
    {
        AddressDocument::findOne();
    }

    public function testFind()
    {
        $address = new AddressDocument();
        $address->fromDocument([
            'line1' => 'test',
            'line2' => 'test',
            'state' => 'cambridge',
            'city' => 'MA',
            'zip' => '12432',
        ]);

        $address->insert();

        $address2 = new AddressDocument();
        $address2->fromDocument([
            'line1' => 'test1',
            'line2' => 'test2',
            'state' => 'NV',
            'city' => 'framingham',
            'zip' => '12432',
        ]);

        $address2->insert();

        /** @var AddressDocument[] $addresses */
        $addresses = AddressDocument::find();

        $this->assertCount(2, $addresses);
        $this->assertEquals($address->toArray(), $addresses[0]->toArray());
        $this->assertEquals($address2->toArray(), $addresses[1]->toArray());
    }

    public function testUpdate()
    {
        $address = new AddressDocument();
        $address->fromDocument([
            'line1' => 'test',
            'line2' => 'test',
            'state' => 'cambridge',
            'city' => 'MA',
            'zip' => '12432',
        ]);

        $address->insert();

        $address = AddressDocument::findOne(['_id' => $address->_id]);
        $address->city = 'cambridge';
        $address->state = 'MA';

        $address->update();

        // finally get a fresh one from the db
        $address = AddressDocument::findOne(['_id' => $address->_id]);

        $this->assertNotNull($address);
        $this->assertEquals('cambridge', $address->city);
        $this->assertEquals('MA', $address->state);
    }

    public function testDistinct()
    {
        for ($i = 0; $i < 5; $i++) {
            $address = new AddressDocument();
            $address->fromDocument([
                'line1' => 'test' . $i,
            ]);
            $address->insert();
        }

        $distinct = AddressDocument::getCollection()->distinct('line1');

        $this->assertCount(5, $distinct);
        $this->assertSame('test0', $distinct[0]);
        $this->assertSame('test4', $distinct[4]);
    }

    public function testArraysAreNotBSONArray()
    {
        $doc = new ConcreteDocument();
        $doc->banana = [
            1,
            2,
            3
        ];

        $doc->insert();

        $doc = ConcreteDocument::findOne(['_id' => $doc->_id]);

        $this->assertCount(3, $doc->banana);
        $this->assertInternalType('array', $doc->banana, 'Banana should be a regular php array');
    }

    public function testArraysAreNotBSONArrayNested()
    {
        $doc = new ConcreteDocument();
        $doc->banana = [
            [0, 1, 2],
            [3, 4, 5],
            [6, 7, 8],
        ];

        $doc->insert();

        $doc = ConcreteDocument::findOne(['_id' => $doc->_id]);

        $this->assertCount(3, $doc->banana);
        $this->assertInternalType('array', $doc->banana, 'Banana should be a regular php array');

        $this->assertCount(3, $doc->banana[0]);
        $this->assertInternalType('array', $doc->banana[0], 'Banana should be a regular php array');

        $this->assertCount(3, $doc->banana[1]);
        $this->assertInternalType('array', $doc->banana[1], 'Banana should be a regular php array');

        $this->assertCount(3, $doc->banana[2]);
        $this->assertInternalType('array', $doc->banana[2], 'Banana should be a regular php array');
    }

    public function testEmbeddedObjects()
    {
        $doc = new ConcreteDocument();
        $address = new AddressDocument();
        $address->fromDocument([
            'line1' => 'test',
            'line2' => 'test',
            'state' => 'cambridge',
            'city' => 'MA',
            'zip' => '12432',
        ]);

        $doc->banana = $address;
        $doc->insert();

        $doc = ConcreteDocument::findOne(['_id' => $doc->_id]);

        $this->assertSame(AddressDocument::class, get_class($doc->banana));
        $this->assertSame('cambridge', $doc->banana->state);
    }

    public function testToDocument()
    {
        $address = new AddressDocument();
        $address->fromDocument([
            'line1' => 'test',
            'line2' => 'test',
            'state' => 'cambridge',
            'city' => 'MA',
            'zip' => '12432',
        ]);

        $document = $address->toDocument(AbstractDocument::OPT_ONLY_MODIFIED);
        $this->assertCount(1, $document, 'only the updatedDate field should be modified after loading');

        $address->line1 = 'foo';
        $address->line2 = 'bar';
        $address->state = 'cambridge';

        $document = $address->toDocument(AbstractDocument::OPT_ONLY_MODIFIED);
        $this->assertCount(
            3,
            $document,
            'fields which have been modified should be recognised'
        );
        $this->assertArrayHasKey('line1', $document);
        $this->assertArrayHasKey('line2', $document);
        $this->assertArrayNotHasKey('state', $document, 'state is not changed from its original value so it isn\'t counted');

        $document = $address->toDocument();
        $this->assertCount(
            6,
            $document,
            'All fields should be encoded into the document'
        );
    }

    public function testToDocumentEmbedded()
    {
        $doc = new ConcreteDocument();
        $address = new AddressDocument();
        $address->fromDocument([
            'line1' => 'test',
            'line2' => 'test',
            'state' => 'cambridge',
            'city' => 'MA',
            'zip' => '12432',
        ]);

        $doc->banana = $address;
        $doc->insert();

        /** @var ConcreteDocument $doc */
        $doc = ConcreteDocument::findOne(['_id' => $doc->_id]);

        $doc->banana->line1 = 'foo';

        $modified = $doc->toDocument(AbstractDocument::OPT_ONLY_MODIFIED);
        $this->assertArrayHasKey('banana', $modified, 'embedded objects should be checked for modifications');
    }

    public function testToDocumentEmbeddedHashTable()
    {
        $doc = new ConcreteDocument();
        $address = new AddressDocument();
        $address->fromDocument([
            'line1' => 'test',
            'line2' => 'test',
            'state' => 'cambridge',
            'city' => 'MA',
            'zip' => '12432',
        ]);

        $doc->banana = [
            'foo' => $address
        ];

        $document = $doc->toDocument();
        $this->assertArrayHasKey('banana', $document);
        $this->assertArrayHasKey('foo', $document['banana']);
        $this->assertArrayHasKey(AbstractDocument::INTERNAL_EMBEDDED_CLASS_FIELD, $document['banana']['foo'], 'document should be embedded in array');

        $doc->insert();

        /** @var ConcreteDocument $doc */
        $doc = ConcreteDocument::findOne(['_id' => $doc->_id]);
        $this->assertInternalType('array', $doc->banana);
        $this->assertArrayHasKey('foo', $doc->banana);
        $this->assertInstanceOf(AddressDocument::class, $doc->banana['foo']);
    }

    public function testToDocumentEmbeddedSeriesArray()
    {
        $doc = new ConcreteDocument();
        $address = new AddressDocument();
        $address->fromDocument([
            'line1' => 'test',
            'line2' => 'test',
            'state' => 'cambridge',
            'city' => 'MA',
            'zip' => '12432',
        ]);

        $doc->banana = [
            $address
        ];

        $document = $doc->toDocument();
        $this->assertArrayHasKey('banana', $document);
        $this->assertArrayHasKey(0, $document['banana']);
        $this->assertArrayHasKey(AbstractDocument::INTERNAL_EMBEDDED_CLASS_FIELD, $document['banana'][0], 'document should be embedded in array');

        $doc->insert();

        /** @var ConcreteDocument $doc */
        $doc = ConcreteDocument::findOne(['_id' => $doc->_id]);
        $this->assertInternalType('array', $doc->banana);
        $this->assertArrayHasKey(0, $doc->banana);
        $this->assertInstanceOf(AddressDocument::class, $doc->banana[0]);
    }

    public function testToDocumentEmbeddedSeriesArrayObjectIDs()
    {
        $doc = new ConcreteDocument();

        $doc->banana = [
            new ObjectID(),
            new ObjectID(),
            new ObjectID(),
            new ObjectID(),
            new ObjectID(),
        ];

        $document = $doc->toDocument();
        $this->assertArrayHasKey('banana', $document);
        $this->assertArrayHasKey(0, $document['banana']);
        $this->assertCount(5, $document['banana']);

        $doc->insert();

        /** @var ConcreteDocument $doc */
        $doc = ConcreteDocument::findOne(['_id' => $doc->_id]);
        $this->assertInternalType('array', $doc->banana);
        $this->assertArrayHasKey(0, $doc->banana);

        foreach ($doc->banana as $item) {
            $this->assertInstanceOf(ObjectID::class, $item);
        }
    }

    /**
     * @return Client
     */
    private function buildMongoClient()
    {
        return new Client();
    }
}
