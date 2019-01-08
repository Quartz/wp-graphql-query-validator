<?php
/**
 * Validator
 *
 * @package wp-graphql-query-validator
 */

namespace WPGraphQL\Extensions\QueryValidator;

use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\Rules\QueryDepth;

/**
 * Adds some query validation rules (based on GraphQL's cost analysis) that
 * prevent WPGraphQL from being abused with expensive query args, extremely deep
 * queries, or very large field sets.
 *
 * Our approach to query args is simple: if it is an approved query arg, it
 * carries a cost of zero. Otherwise it carries a prohibitive cost (max cost *
 * 100).
 *
 * Query depth is limited using GraphQL's internal setMaxQueryDepth.
 *
 * Field sets are limited with a primitive max cost, where by default each field
 * that is queried carries a cost of 1.
 */
class Validator {
	/**
	 * Max query cost. This corresponds to the total cost of all fields at all
	 * query levels. The default cost to query a field is 1 which includes all
	 * type names, edges, nodes, etc.
	 *
	 * @var int
	 */
	private $max_cost = 1000;

	/**
	 * Max query depth. Query depth of 11 accommodates all of our queries and
	 * the schema query.
	 *
	 * @var int
	 */
	private $max_depth = 11;

	/**
	 * We will allow ONE of these where args. All other where args (besides those
	 * in whitelist) are completely disallowed.
	 *
	 * @var array
	 */
	private $restricted_where_args = array(
		'location',
		'name',
		'orderby',
		'search',
		'slug',
		'tagSlugIn',
	);

	/**
	 * We will ALWAYS allow these where args, either alone or in combination.
	 *
	 * @var array
	 */
	private $whitelisted_where_args = array();

	/**
	 * Add hooks on graphql_init.
	 *
	 * @return void
	 */
	public function init() {
		$this->add_query_validation_rules();

		// New action "graphql_get_schema" as of WPGraphQL 0.0.26 (previously was
		// "graphql_generate_schema").
		add_action( 'graphql_get_schema', array( $this, 'amend_where_args' ), 10, 0 );
		add_filter( 'graphql_RootQuery_fields', array( $this, 'add_cost_definition' ), 99, 1 );
	}

	/**
	 * Add baseline query validation rules to GraphQL.
	 *
	 * @return void
	 */
	public function add_query_validation_rules() {
		$depth_rule = new QueryDepth( $this->max_depth );
		DocumentValidator::addRule( $depth_rule );

		$complexity_rule = new QueryComplexity( $this->max_cost );
		DocumentValidator::addRule( $complexity_rule );
	}

	/**
	 * Add cost definition to schema, allowing us to short-circuit expensive
	 * queries.
	 *
	 * @param array $fields GraphQL root fields.
	 *
	 * @return array
	 */
	public function add_cost_definition( $fields ) {
		foreach ( $fields as &$field ) {
			$field['complexity'] = array( $this, 'calculate_query_cost' );
		}

		return $fields;
	}

	/**
	 * Allow whitelisted and restricted where args to be amended.
	 *
	 * @return void
	 */
	public function amend_where_args() {
		$this->restricted_where_args = apply_filters( 'graphql_restricted_where_args', $this->restricted_where_args );
		$this->whitelisted_where_args = apply_filters( 'graphql_whitelisted_where_args', $this->whitelisted_where_args );
	}

	/**
	 * Calculate the cost of the query based on its query arguments.
	 *
	 * @param  int   $children_cost Calculated cost of the query children.
	 * @param  array $args          Query args.
	 * @return int
	 */
	public function calculate_query_cost( $children_cost, $args ) {
		// If any query arg carries a cost of more than zero, return a prohibitive
		// cost.
		foreach ( $args as $name => $arg ) {
			if ( $this->calculate_query_arg_cost( $name, $arg ) > 0 ) {
				return $this->max_cost * 100;
			}
		}

		return $children_cost;
	}

	/**
	 * Calculate the cost of a query argument. Known-good queries carry no cost.
	 *
	 * @param  string $arg_name  Argument name.
	 * @param  mixed  $arg_value Argument value.
	 * @return int
	 */
	private function calculate_query_arg_cost( $arg_name, $arg_value ) {
		switch ( $arg_name ) {
			// Pagination markers.
			case 'after':
			case 'before':
				return 0;

			// Pagination limts of 50 or less.
			case 'first':
			case 'last':
				return floor( intval( $arg_value ) / 51 );

			// Getting a resource by ID.
			case 'id':
			case 'mediaItemId':
			case 'pageId':
			case 'postId':
				return 0;

			// Getting a resource by a whitelisted property.
			case 'slug':
			case 'uri':
				return 0;

			// The "where" arg requires more inspection to determine cost.
			case 'where':
				return $this->calculate_where_cost( array_keys( $arg_value ) );

			// Unknown or unvetted query args carry a disqualifying cost.
			default:
				return 100;
		}
	}

	/**
	 * Calculate the cost of a "where" argument. The where clause is extremely
	 * powerful and also therefore potentially exploitable to produce costly
	 * queries. Therefore, we want to carefully limit where arguments.
	 *
	 * @param  array $arg_names Where arg names.
	 * @return int
	 */
	private function calculate_where_cost( $arg_names ) {
		// Remove whitelisted args from the array; we allow them to be combined
		// with other where args at no cost.
		$arg_names = array_values( array_diff( $arg_names, $this->whitelisted_where_args ) );

		// No remaining query args, no problem.
		if ( 0 === count( $arg_names ) ) {
			return 0;
		}

		// If there is one remaining arg and it's in the list of allowed where
		// args, no problem.
		if ( 1 === count( $arg_names ) && in_array( $arg_names[0], $this->restricted_where_args, true ) ) {
			return 0;
		}

		// Otherwise, the query is too complex; return a disqualifying cost.
		return 100;
	}
}
