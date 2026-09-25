<?php
/**
 * Insights URL structure (blueprint §3):
 *   /insights/                 posts page (hub)
 *   /insights/{post-slug}/     articles
 *   /insights/{topic}/         topic hubs (categories)
 *
 * The demo importer sets the permalink structure to /insights/%postname%/. Pages and case
 * studies are unaffected (pages have no front, case studies use with_front = false).
 *
 * @package HelloProVision
 */

defined( 'ABSPATH' ) || exit;

/**
 * Topic hub rules, placed before the article rule so /insights/websites/ is a topic.
 */
function hpv_topic_rewrites() {
	$slugs = get_terms(
		array(
			'taxonomy'   => 'category',
			'hide_empty' => false,
			'fields'     => 'slugs',
		)
	);
	if ( is_wp_error( $slugs ) || ! $slugs ) {
		return;
	}
	$pattern = implode( '|', array_map( 'preg_quote', $slugs ) );
	add_rewrite_rule( '^insights/(' . $pattern . ')/page/?([0-9]{1,})/?$', 'index.php?category_name=$matches[1]&paged=$matches[2]', 'top' );
	add_rewrite_rule( '^insights/(' . $pattern . ')/?$', 'index.php?category_name=$matches[1]', 'top' );
}
add_action( 'init', 'hpv_topic_rewrites', 20 );

/**
 * Category links point to /insights/{topic}/.
 *
 * @param string  $url  Term link.
 * @param WP_Term $term Term.
 */
function hpv_topic_link( $url, $term ) {
	if ( 'category' === $term->taxonomy && get_option( 'permalink_structure' ) ) {
		return home_url( user_trailingslashit( 'insights/' . $term->slug ) );
	}
	return $url;
}
add_filter( 'term_link', 'hpv_topic_link', 10, 2 );

/**
 * New or renamed topics need fresh rules.
 */
function hpv_flush_on_topic_change() {
	update_option( 'hpv_rewrite_version', '' );
}
add_action( 'created_category', 'hpv_flush_on_topic_change' );
add_action( 'edited_category', 'hpv_flush_on_topic_change' );
add_action( 'delete_category', 'hpv_flush_on_topic_change' );
