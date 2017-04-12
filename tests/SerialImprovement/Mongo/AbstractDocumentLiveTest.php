<?php
namespace Testing\Live;

use MongoDB\BSON\ObjectID;
use MongoDB\Client;
use SerialImprovement\Mongo\AbstractDocument;
use SerialImprovement\Mongo\HasOne;

/**
 * Because we are testing an abstract class we create a simple
 * concrete version with test values
 *
 * @property string banana
 * @property AddressDocument address
 *
 * Class ConcreteDocument
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

/**
 * @property string line1
 * @property string line2
 * @property string state
 * @property string city
 * @property string zip
 *
 * Class AddressDocument
 * @package Testing\Live
 */
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

/**
 * @property string          name
 * @property AddressDocument address
 *
 * Class PersonDocument
 * @package Testing\Live
 */
class PersonDocument extends AbstractDocument {
    /**
     * Should return the names of the fields you wish your document to contain
     *
     * @return string[]
     */
    protected function getDefaultFields(): array
    {
        return [
            'name',
            'address'
        ];
    }

    public function address(): HasOne
    {
        return $this->hasOne(AddressDocument::class, 'address');
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

        /** @var ConcreteDocument $doc */
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

    public function testHasOneReferences()
    {
        $person = new PersonDocument();
        $address = new AddressDocument();
        $address->fromDocument([
            'line1' => 'test',
            'line2' => 'test',
            'state' => 'cambridge',
            'city' => 'MA',
            'zip' => '12432',
        ]);
        $address->insert();

        $person->name = 'joanne';
        $person->address()->associate($address);

        $person->insert();

        /** @var PersonDocument $person */
        $person = PersonDocument::findOne(['_id' => $person->_id]);

        $this->assertSame(AddressDocument::class, get_class($person->address));
        $this->assertSame('cambridge', $person->address->state);
    }

    public function testHasOneReferencesUpdatingReference()
    {
        $person = new PersonDocument();
        $address = new AddressDocument();
        $address->fromDocument([
            'line1' => 'test',
            'line2' => 'test',
            'state' => 'cambridge',
            'city' => 'MA',
            'zip' => '12432',
        ]);
        $address->insert();

        $person->name = 'joanne';
        $person->address()->associate($address);

        $person->insert();

        /** @var PersonDocument $person */
        $person = PersonDocument::findOne(['_id' => $person->_id]);

        $this->assertSame(AddressDocument::class, get_class($person->address));
        $this->assertSame('cambridge', $person->address->state);

        $person->address->state = 'MA';
        $person->address->city = 'cambridge';
        $person->address->update();

        /** @var PersonDocument $person */
        $person = PersonDocument::findOne(['_id' => $person->_id]);

        $this->assertSame(AddressDocument::class, get_class($person->address));
        $this->assertSame('MA', $person->address->state);
        $this->assertSame('cambridge', $person->address->city);
    }

    /**
     * @return Client
     */
    private function buildMongoClient()
    {
        return new Client();
    }
}
