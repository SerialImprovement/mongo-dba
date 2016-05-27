mongo-odm
=========

A simple object document model for MongoDB under PHP.

Features
--------

1. Fields
1. Light wrappers of `find`, `findOne`, `insert`, `update` and `delete` operations
1. Automatic `_id`, `createdDate`, `updatedDate` fields
2. EmbedsOne sub-documents


[![Build Status](https://travis-ci.org/SerialImprovement/mongo-dba.svg?branch=master)](https://travis-ci.org/SerialImprovement/mongo-dba)


Installation
------------

DONT, it is not ready for production yet.

However, if you are really interested, you can use composer's repository functionality to install it.

Add this to composer.json:

```
"repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/SerialImprovement/mongo-dba"
    }
]
```
and then require as usual:
```
"serialimprovement/mongo-dba": "dev-master"
```

Usage
-----

Extend the base AbstractDocument class and implement the missing methods `getDefaultFields` and `getDatabaseName`.

```
class Address extends AbstractDocument
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
        return 'addresses';
    }
}
```

Creating an instance:

```
// connect to mongo
$client = new \MongoDB\Client();

// create connector class to manage client
// the connector is what you provide to new
// document instances
$connector = new Connector($client);

$address = new Address($connector);
$address->line1 = 'test';
$address->line2 = 'test';
$address->state = 'cambridge';
$address->city = 'MA';
$address->zip = '12345';

$address->insert();
```

An _id will be generated if not set.

The resulting document might look like this:

```
{
    "_id" : ObjectId("57466b241aa4769f71222361"),
    "line1" : "test",
    "line2" : "test",
    "state" : "cambridge",
    "city" : "MA",
    "zip" : "12345",
    "createdDate" : ISODate("2016-05-26T03:19:00.988Z")
}
```
