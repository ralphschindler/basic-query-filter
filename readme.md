# Basic Query Filter

This started out as a weekend-project. A DSL Parser for a filter query langague that
primarily can be used in an API or similar setting where filtering is based on predicate logic.

The goal is to be able to take string filters and parse them into predicate expression objects
in order to adjoin them to a backend implementation.  This backend implementation could be
Doctrine, Laravel Eloquent, or document storage.

Example queries in this language:

- `price > 100`
- `price > 100 AND active = 1`
- `product.price > 100 AND category.id = 7`
- `name =~ "Foo%"`
- `created_at > "2017-01-01" and created_at < "2017-01-31"`
- `status = 1 AND (name = "PHP Rocks" || name = "I ♥ API's")`

## Install

```
composer require "ralphschindler/basic-query-filter"
```

## Usage

Basic usage is to take a string and parse it, using the ParseTree to be able to convert the
expressions to whatever is required by the library you are adjoining.

```php
$parseTree = (new QueryFilter\Parser)->parse($filter);
foreach ($parseTree->getPredicates() as $predicateInfo) {
    list($combinedBy, $predicate) = $predicateInfo;
    // ...    
}
```

### Laravel

Expand usage as necessary to accommodate more filtering features.

```php
// $modelQuery is a Model::newQuery() instance (Illuminate\Database\Eloquent\Builder)

foreach ($parseTree->getPredicates() as $predicateInfo) {
    list($combinedBy, $predicate) = $predicateInfo;
    $op = ($predicate->op == '=~') ? 'like' : $predicate->op;
    if ($combinedBy === 'OR') {
        $modelQuery->orWhere((string)$predicate->left, $op, $predicate->right);
    } else {
        $modelQuery->where((string)$predicate->left, $op, $predicate->right);
    }
}
```