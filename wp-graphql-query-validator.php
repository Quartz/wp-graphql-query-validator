<?php
/**
 * Plugin Name: WPGraphQL Query Validator
 * Plugin URI: https://github.com/Quartz/wp-graphql-query-validator
 * Description: Provides tools to prevent WPGraphQL from being abused with expensive queries.
 * Author: Chris Zarate, Quartz
 * Version: 1.0.2
 * Author URI: https://qz.com/
 *
 * @package wp-graphql-query-validator
 */

namespace WPGraphQL\Extensions\QueryValidator;

require_once( __DIR__ . '/src/schema.php' );
require_once( __DIR__ . '/src/validator.php' );

add_action( 'graphql_init', array( new Schema(), 'init' ), 10, 0 );
add_action( 'graphql_init', array( new Validator(), 'init' ), 10, 0 );
