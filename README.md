mongo-odm
=========

A simple object document model for MongoDB under PHP.

Features
--------

1. Fields
2. Light wrappers of `find`, `findOne`, `insert`, `update` and `delete` operations
3. Automatic `_id`, `createdDate`, `updatedDate` fields
4. EmbedsOne sub-documents

Not Implemented
---------------

1. Embeds many
2. References (should these be supported?)
3. Datatype checks/conversions


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
"serialimprovement/mongo-dba": "0.3.3"
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

The collection name will be dynamically generated unless you override `getCollectionName`.
Its a good idea to create a single base descendant of `AbstractDocument` to set the
database name if you use a single db for your app.

Creating an instance:

```
// connect to mongo
$client = new \MongoDB\Client();

// statically set client for all instances
AbstractDocument::setClient($client);

$address = new Address();
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

Embedded Documents
------------------

### Embeds One

As simple as setting a property of a document with the instance of another.

Metadata is stored along with the class name (`embeddedClass` field) to resolve the class once reloaded from the database.
You must update `embeddedClass` in your database if you decide to refactor your code.

### Embeds Many

Not currently implemented
