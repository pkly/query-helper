# QueryHelper
Simple utility class for easier use of Doctrine ORM Queries in certain scenarios

[![Packagist Downloads](https://img.shields.io/packagist/dt/pkly/query-helper)](https://packagist.org/packages/pkly/query-helper)

## Installation

Simply run

```
composer require pkly/query-helper
```


## Usage

In your repositories simply return an instance of QueryHelper instead of a full type.

```php
class FooEntityRepository extends \Doctrine\ORM\EntityRepository
{
    /**
    * @return QueryHelper<FooEntity, int> 
    */
    public function findByOrder(
        Order $order
    ): QueryHelper {
        return new QueryHelper(
            $this->createQueryBuilder('entity')
                ->where('entity.relatedOrder = :relaterOrder')
                ->setParameter('relaterOrder', $order)
        );
    }
}
```

Then use it as normal.

```php
// inside of a service, controller, etc.
$helper = $repository->findByOrder($order);

$helper->ids(); // list of ids
$helper->id(); // single id
$helper->value(); // single object
$helper->list(); // list of objects
$helper->reference(); // single reference from Doctrine
$helper->references(); // list of references
$helper->fields(['order']); // single array of fields (one row)
$helper->fieldList(['order']); // list of fields (multiple rows)
$helper->count(); // simple count
$helper->exists(); // boolean existence check

// more advanced use
// load one object with lock from query
$object = $em->getRepository(FooEntity::class)
    ->findByOrder($order)
    ->lockMode(LockMode::PESSIMISTIC_WRITE)
    ->value(); // returns T|null
```

### Feature requests?

Sure, hit me up with an issue if you wish.