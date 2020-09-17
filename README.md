# Paysera Pagination component

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Build Status][ico-travis]][link-travis]
[![Coverage Status][ico-scrutinizer]][link-scrutinizer]
[![Quality Score][ico-code-quality]][link-code-quality]
[![Total Downloads][ico-downloads]][link-downloads]

This component allows to paginate Doctrine `QueryBuilder` instances using cursor-based pagination. Offset is also supported.

## Why?

### Performance

Cursor pagination can be much more efficient under large data-sets.

For example, to take 3000th page, consisting of 100 items, using offset you would need to execute such query:
```sql
SELECT *
FROM items
WHERE field = 'some value'
ORDER BY created_at DESC
LIMIT 100 OFFSET 29900
```

This type of query, even if you have indexes on the fields you are querying by (and the fields you order by), would need
to iterate over 29900 items first, before giving you the requested result.

If using cursor based pagination, the resulting query can look like this:
```sql
SELECT *
FROM items
WHERE field = 'some value'
AND created_at <= '2018-01-01 00:01:02' AND id < 12345
ORDER BY created_at DESC, id DESC
LIMIT 100
```

This allows to add required indexes to the table to enable fast querying.
Keep in mind that without any indexes, the performance can be really similar.

In this concrete example, there should be a multiple-column index on `field,created_at,id` for best performance
(or just `field,created_at` if `created_at` is rather unique).

### Iterate over all the items

When using query from previous example with offset based pagination, it's possible to get the same item twice or
miss some of the items.

This is because when we're getting the pages, new items can be added or some of the items removed, which changes the
position of all other items.

Let's take an example. We have pages, each of 5 items. We've already got the first page with items `1 2 3 4 5`. Before
we get the second page, these situations might occur:

```
Original:        1 2 3 4 5 6 7 8 9
Second page:               6 7 8 9

"0" is added:    0 1 2 3 4 5 6 7 8 9
Second page:               5 6 7 8 9     <- 5 is duplicated

"2" was removed: 1 3 4 5 6 7 8 9
Second page:               7 8 9     <- 6 was skipped
```

If we're using cursor based pagination, we start the page after (including or excluding) or before some concrete item,
so we don't get these types of issues.

### Caveats

Using cursors, it's easy to move to the next and previous pages, but it's not possible to:
- know what page you are in right now;
- to skip some number of pages directly or go to page number X.

To support mixed cases:
- cursors are always provided and available for pagination;
- `hasNext` and `hasPrevious` are also always available to know if you're in the first or last page;
- offset is available for pagination, but can be limited to some maximum. This could be used to move to N-th page,
but not 100N-th, which is not needed that often;
- if you need to skip to the last page, you should offer (or do that automatically) to reverse the ordering and go to
first page instead;
- to display number of pages, total count must be calculated. This can be done but is disabled by default, again, for
performance reasons. You should avoid giving this with each of the pages – instead provide total count only when needed
or at least cache it.

## Installation

```
composer require paysera/lib-pagination
```

## Basic Usage

Use `ResultProvider` class (service) to provide the result.

To get the result, two arguments are needed:
- `ResultConfiguration`. This is wrapper around `QueryBuilder` and has configuration for available ordering fields,
maximum offset, result item transformation and whether total count should be calculated;
- `Pager`. This holds parameters provided by the user: offset or after/before cursor, limit and ordering directions.

Generally `ConfiguredQuery` holds internals related to `QueryBuilder` so it's recommended to return this from the
`Repository` directly – let's hold implementation details together.

`Pager` should be created somewhere in the input layer, usually in the controller or similar place.

Example usage:
```php
<?php
use Paysera\Pagination\Entity\Doctrine\ConfiguredQuery;
use Paysera\Pagination\Service\Doctrine\ResultProvider;
use Paysera\Pagination\Service\CursorBuilder;
use Paysera\Pagination\Service\Doctrine\QueryAnalyser;
use Paysera\Pagination\Entity\OrderingConfiguration;
use Paysera\Pagination\Entity\Pager;
use Paysera\Pagination\Entity\OrderingPair;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Doctrine\ORM\EntityManagerInterface;

$resultProvider = new ResultProvider(
    new QueryAnalyser(),
    new CursorBuilder(PropertyAccess::createPropertyAccessor())
);

/** @var EntityManagerInterface $entityManager */
$queryBuilder = $entityManager->createQueryBuilder()
    ->select('m')
    ->from('Bundle:MyEntity', 'm')
    ->andWhere('m.field = :param')
    ->setParameter('param', 'some value')
;

$configuredQuery = (new ConfiguredQuery($queryBuilder))
    ->addOrderingConfiguration(
        'my_field_name',
        (new OrderingConfiguration('m.field', 'field'))->setOrderAscending(true)
    )
    ->addOrderingConfiguration('my_other_name', new OrderingConfiguration('m.otherField', 'otherField'))
    ->setTotalCountNeeded(true) // total count will be returned only if this is called
    ->setMaximumOffset(100) // you can optionally limit maximum offset
    ->setItemTransformer(function ($item) {
        // return transformed item if needed
    })
;

$pager = (new Pager())
    ->setLimit(10)
    ->setOffset(123)    // set only one of offset, after or before
    ->setAfter('Cursor from Result::getNextCursor')
    ->setBefore('Cursor from Result::getPreviousCursor')
    ->addOrderBy(new OrderingPair('my_field_name')) // order by default direction (asc in this case)
    ->addOrderBy(new OrderingPair('my_other_name', true)) // or set direction here
;
$result = $resultProvider->getResultForQuery($configuredQuery, $pager);

$result->getItems(); // items in the page
$result->getNextCursor(); // value to pass with setAfter for next page
$result->getPreviousCursor(); // value to pass with setBefore for previous page
$result->getTotalCount(); // available only if setTotalCountNeeded(true) was called

$totalCount = $resultProvider->getTotalCountForQuery($configuredQuery); // calculate total count directly
```

## Using iterator

There are a few classes (services) to help iterating over large result sets:
- `ResultIterator` – iterates using pagination, hides the pagination beneath;
- `FlushingResultIterator` – same as `ResultIterator`, but flushes and clears `EntityManager` after each page.
Use when you need to modify Entities managed by Doctrine.
This pattern (instead of just calling `$em->flush()` in the end) is needed to avoid out of memory failures
and to optimize the process.

Using `ResultIterator`:
```php
<?php
use Paysera\Pagination\Entity\Doctrine\ConfiguredQuery;
use Paysera\Pagination\Service\Doctrine\ResultIterator;
use Paysera\Pagination\Service\CursorBuilder;
use Paysera\Pagination\Service\Doctrine\QueryAnalyser;
use Paysera\Pagination\Service\Doctrine\ResultProvider;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Psr\Log\NullLogger;

/** @var ConfiguredQuery $configuredQuery */

$configuredQuery = new ResultIterator(
    new ResultProvider(
        new QueryAnalyser(),
        new CursorBuilder(PropertyAccess::createPropertyAccessor())
    ),
    new NullLogger(),
    $defaultLimit = 1000
);

foreach ($this->resultIterator->iterate($configuredQuery) as $item) {
    // process $item where flush is not needed
    // for example, send ID or other data to working queue, process files etc.
}

```

Using `FlushingResultIterator`:
```php
<?php
use Paysera\Pagination\Entity\Doctrine\ConfiguredQuery;
use Paysera\Pagination\Service\Doctrine\FlushingResultIterator;
use Paysera\Pagination\Service\CursorBuilder;
use Paysera\Pagination\Service\Doctrine\QueryAnalyser;
use Paysera\Pagination\Service\Doctrine\ResultProvider;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Psr\Log\NullLogger;
use Doctrine\ORM\EntityManagerInterface;

/** @var ConfiguredQuery $configuredQuery */
/** @var EntityManagerInterface $entityManager */

$configuredQuery = new FlushingResultIterator(
    new ResultProvider(
        new QueryAnalyser(),
        new CursorBuilder(PropertyAccess::createPropertyAccessor())
    ),
    new NullLogger(),
    $defaultLimit = 1000,
    $entityManager
);

foreach ($this->resultIterator->iterate($configuredQuery) as $item) {
    // process $item or other entities where flush will be called after each page
    // for example:
    $item->setTitle(formatTitleFor($item));
    
    // keep in mind, that clear is also called – don't reuse other Entities outside of foreach cycle
}

echo "Updated successfully";
// no need to flush here anymore

```

If there was out of memory exception etc, search logs (INFO level) for last "Continuing with iteration" message,
look at "after" in the context, then:
```php
$lastCursor = '"123"'; // get from logs
$startPager = (new Pager())
    ->setLimit(500) // can also override default limit
    ->setAfter($lastCursor)
;
foreach ($this->resultIterator->iterate($configuredQuery, $startPager) as $item) {
    // process $item
}
```

## Semantic versioning

This library follows [semantic versioning](http://semver.org/spec/v2.0.0.html).

See [Symfony BC rules](http://symfony.com/doc/current/contributing/code/bc.html) for basic
information about what can be changed and what not in the API.

## Running tests

```
composer update
composer test
```

## Contributing

Feel free to create issues and give pull requests.

You can fix any code style issues using this command:
```
composer fix-cs
```

[ico-version]: https://img.shields.io/packagist/v/paysera/lib-pagination.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/paysera/lib-pagination/master.svg?style=flat-square
[ico-scrutinizer]: https://img.shields.io/scrutinizer/coverage/g/paysera/lib-pagination.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/paysera/lib-pagination.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/paysera/lib-pagination.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/paysera/lib-pagination
[link-travis]: https://travis-ci.org/paysera/lib-pagination
[link-scrutinizer]: https://scrutinizer-ci.com/g/paysera/lib-pagination/code-structure
[link-code-quality]: https://scrutinizer-ci.com/g/paysera/lib-pagination
[link-downloads]: https://packagist.org/packages/paysera/lib-pagination