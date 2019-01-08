<?php
/**
 * Test WPGraphQL Query Validator.
 *
 * @package wp-graphql-query-validator
 */

require_once( __DIR__ . '/../src/validator.php' );

use PHPUnit\Framework\TestCase;
use WPGraphQL\Extensions\QueryValidator\Validator;

/**
 * Test WPGraphQL Query Validator.
 *
 * @class Test_WPGraphQL_QueryValidation_Validator
 */
class Test_WPGraphQL_QueryValidation_Validator extends TestCase {
	/**
	 * Data provider to generate failing queries.
	 */
	public function generate_failing_queries() {
		return [
			[ 0, [ 'first' => 100 ] ],
			[ 0, [ 'unknown' => true ] ],
			[ 0, [ 'where' => [ 'unknown' => true ] ] ],
			[ 0, [ 'where' => [ 'orderby' => 'test', 'name' => 'test' ] ] ],
			[ 10, [] ],
		];
	}

	/**
	 * Data provider to generate failing queries.
	 */
	public function generate_passing_queries() {
		return [
			[ 0, [] ],
			[ 0, [ 'after' => 'test', 'first' => 50, 'id' => 'test' ] ],
			[ 0, [ 'where' => [ 'orderby' => 'test' ] ] ],
			[ 0, [ 'after' => 'test', 'first' => 50, 'id' => 'test' ], 'where' => [ 'orderby' => 'test' ] ],
		];
	}

	/**
	 * Test queries that should generate a disqualifying query cost.
	 *
	 * @param int   $children_cost Already calculated cost of children queries.
	 * @param array $args          Array of args (name => value).
	 * @dataProvider generate_failing_queries
	 */
	public function test_calculate_query_cost_failure( $children_cost, $args ) {
		$validator = new Validator();
		$cost = $validator->calculate_query_cost( $children_cost, $args );
		$this->assertGreaterThan( 1, $cost );
	}

	/**
	 * Test queries that should generate a passing query cost.
	 *
	 * @param int   $children_cost Already calculated cost of children queries.
	 * @param array $args          Array of args (name => value).
	 * @dataProvider generate_passing_queries
	 */
	public function test_calculate_query_cost_success( $children_cost, $args ) {
		$validator = new Validator();
		$cost = $validator->calculate_query_cost( $children_cost, $args );
		$this->assertEquals( 0, $cost );
	}
}
