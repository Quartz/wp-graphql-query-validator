<?php
/**
 * Schema
 *
 * @package wp-graphql-query-validator
 */

namespace WPGraphQL\Extensions\QueryValidator;

/**
 * Adjust our schema to allow only the query types we want to support. By
 * default, we only allow queries of post types and taxonomies that have been
 * registered with WPGraphQL. This excludes types we definitely don't want to
 * expose (like "plugins") and ensures that new query types added in subsequent
 * versions of WPGraphQL won't catch us by surprise.
 *
 * We also completely disable mutations by default.
 */
class Schema {
	/**
	 * Add hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'graphql_schema_config', array( $this, 'disable_mutations' ), 10, 1 );

		// Increase the priority to be more than 10 since WPGraphQL itself filters
		// this at the default priority via register_graphql_object_type.
		add_filter( 'graphql_RootQuery_fields', array( $this, 'disable_queries' ), 20, 1 );
	}

	/**
	 * Disable GraphQL mutations. This also communicates that we don't allow
	 * mutations to anyone who might be crawling schemas.
	 *
	 * @param array $schema Computed GraphQL schema.
	 * @return array
	 */
	public function disable_mutations( $schema ) {
		if ( apply_filters( 'graphql_disable_mutations', true ) ) {
			unset( $schema['mutation'] );
		}

		return $schema;
	}

	/**
	 * Disable query types that are not in our list of allowed types. This
	 * removes them from the schema.
	 *
	 * @param array $root_queries GraphQL root queries.
	 * @return array
	 */
	public function disable_queries( $root_queries ) {
		// Get post types and taxonomies that have been registered in GraphQL.
		$filter = array( 'show_in_graphql' => true );
		$post_types = array_values( array_map( 'get_post_type_object', get_post_types( $filter ) ) );
		$taxonomies = array_values( array_map( 'get_taxonomy', get_taxonomies( $filter ) ) );

		// Post types have a special "by" query, e.g., "postBy" or "mediaItemBy".
		$post_by_queries = array_map( function( $post_type ) {
			return $post_type->graphql_single_name . 'By';
		}, $post_types );

		// Hardocding the queries for WordPress built-ins (singular and plural).
		$wp_built_ins = array( 'menu', 'menuItem', 'user' );
		foreach ( $wp_built_ins as $built_in ) {
			$wp_built_ins[] = "{$built_in}s";
		}

		// Merge post type, taxonomy, and user queries. This intentionally excludes
		// queries for all other data types, e.g., plugins and themes.
		$all_queries = array_merge(
			wp_list_pluck( $post_types, 'graphql_single_name' ),
			wp_list_pluck( $post_types, 'graphql_plural_name' ),
			wp_list_pluck( $taxonomies, 'graphql_single_name' ),
			wp_list_pluck( $taxonomies, 'graphql_plural_name' ),
			$post_by_queries,
			$wp_built_ins
		);

		// Allow user to filter this list.
		$allowed_queries = apply_filters( 'graphql_allowed_queries', $all_queries );

		foreach ( array_keys( $root_queries ) as $type ) {
			if ( ! in_array( $type, $allowed_queries, true ) ) {
				unset( $root_queries[ $type ] );
			}
		}

		return $root_queries;
	}
}
