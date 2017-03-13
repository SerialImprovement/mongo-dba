<?php
namespace Testing\Live;

use MongoDB\BSON\ObjectID;
use MongoDB\Client;
use SerialImprovement\Mongo\AbstractDocument;
use SerialImprovement\Mongo\Connector;

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
    /** @var  Connector */
    protected $connector;

    protected function setUp()
    {
        $this->connector = $this->buildMongoClient();
        parent::setUp();
    }

    protected function tearDown()
    {
        $this->buildMongoClient()->getMongoClient()->selectDatabase('test')->drop();
        parent::tearDown();
    }

    public function testInsertAndFineOne()
    {
        $address = new AddressDocument($this->connector);
        $address->fromDocument([
            'line1' => 'test',
            'line2' => 'test',
            'state' => 'cambridge',
            'city' => 'MA',
            'zip' => '12432',
        ]);

        $address->insert();

        /** @var AddressDocument $addressFromDb */
        $addressFromDb = AddressDocument::findOne($this->connector, ['_id' => new ObjectID($address->_id)]);

        $this->assertNotNull($addressFromDb);
        $this->assertEquals('cambridge', $addressFromDb->state);
        $this->assertEquals('12432', $addressFromDb->zip);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testFindOneThrowsWhenNotFound()
    {
        AddressDocument::findOne($this->connector);
    }

    public function testFind()
    {
        $address = new AddressDocument($this->connector);
        $address->fromDocument([
            'line1' => 'test',
            'line2' => 'test',
            'state' => 'cambridge',
            'city' => 'MA',
            'zip' => '12432',
        ]);

        $address->insert();

        $address2 = new AddressDocument($this->connector);
        $address2->fromDocument([
            'line1' => 'test1',
            'line2' => 'test2',
            'state' => 'NV',
            'city' => 'framingham',
            'zip' => '12432',
        ]);

        $address2->insert();

        /** @var AddressDocument[] $addresses */
        $addresses = AddressDocument::find($this->connector);

        $this->assertCount(2, $addresses);
        $this->assertEquals($address->toArray(), $addresses[0]->toArray());
        $this->assertEquals($address2->toArray(), $addresses[1]->toArray());
    }

    public function testUpdate()
    {
        $address = new AddressDocument($this->connector);
        $address->fromDocument([
            'line1' => 'test',
            'line2' => 'test',
            'state' => 'cambridge',
            'city' => 'MA',
            'zip' => '12432',
        ]);

        $address->insert();

        $address = AddressDocument::findOne($this->connector, ['_id' => $address->_id]);
        $address->city = 'cambridge';
        $address->state = 'MA';

        $address->update();

        // finally get a fresh one from the db
        $address = AddressDocument::findOne($this->connector, ['_id' => $address->_id]);

        $this->assertNotNull($address);
        $this->assertEquals('cambridge', $address->city);
        $this->assertEquals('MA', $address->state);
    }

    public function testDistinct()
    {
        for ($i = 0; $i < 5; $i++) {
            $address = new AddressDocument($this->connector);
            $address->fromDocument([
                'line1' => 'test' . $i,
            ]);
            $address->insert();
        }

        $distinct = AddressDocument::getCollection($this->connector)->distinct('line1');

        $this->assertCount(5, $distinct);
        $this->assertSame('test0', $distinct[0]);
        $this->assertSame('test4', $distinct[4]);
    }

    /**
     * @return Connector
     */
    private function buildMongoClient()
    {
        return new Connector(new Client());
    }
}
