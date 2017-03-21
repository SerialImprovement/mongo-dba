<?php

namespace Banana;

use SerialImprovement\Mongo\AbstractDocument;
use SerialImprovement\Mongo\Connector;

require_once __DIR__ . '/vendor/autoload.php';

/**
 * @property string banana
 * @property UserDoc friend
 * @property AddressDoc address
 */
class UserDoc extends AbstractDocument
{
    protected function getDefaultFields(): array
    {
        return [
            'name',
            'pass',
            'address',
            'friend'
        ];
    }

    protected function getDatabaseName(): string
    {
        return 'mongoDba';
    }
}

class AddressDoc extends AbstractDocument
{
    protected function getDefaultFields(): array
    {
        return [
            'line1',
            'line2',
            'state',
            'city',
            'zip',
        ];
    }

    protected function getDatabaseName(): string
    {
        return 'mongoDba';
    }
}

$client = new \MongoDB\Client();
$connector = new Connector($client);

//$user = new UserDoc($connector);
//$user->name = 'bill';
//$user->pass = 'pass';
//
//$address = new AddressDoc($connector);
//$address->line1 = 'test';
//$address->line2 = 'test';
//$address->state = 'cambridge';
//$address->city = 'MA';
//$address->zip = '02140';
//$address->insert();
//
//$user->address = $address->toReference();
//
//$user->insert();

/** @var UserDoc $user */
$test = UserDoc::findOne($connector, ['name' => 'bill'], []);

//$test->friend = new UserDoc($connector);

//$test->banana = 'rama';
//$test->update();

print_r($test->toArray());
