# WPGraphQL Query Validator

Uses GraphQL's query cost API to help prevent WPGraphQL from being abused with
expensive queries. This is not a "set-it-and-forget-it" plugin! You should think
about the kind of queries you want to allow and review the source code to
determine how you want to use query cost analysis.

## Tests

```sh
docker run -v $(pwd):/app --rm phpunit/phpunit tests/test-wp-graphql-query-validator.php
```
