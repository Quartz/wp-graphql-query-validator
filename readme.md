# WPGraphQL Query Validator

Uses GraphQL's query cost API to help prevent WPGraphQL from being abused with
expensive queries. This is not a "set-it-and-forget-it" plugin! You should think
about the kind of queries you want to allow and review the source code to
determine how you want to use query cost analysis.

## Approach

This plugin works by creating a (filterable) list of allowed types and query arguments that
are considered "safe" (or performant). If a query uses non-allowed types or query args,
it receives a disqualifying query cost (enforced by `graphql-php`).

## Tests

```sh
docker run -v $(pwd):/app --rm phpunit/phpunit tests/test-wp-graphql-query-validator.php
```
