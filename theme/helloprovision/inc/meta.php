<?php
/**
 * Registered post meta (edited in the block editor sidebar, see assets/js/editor/blocks.js).
 *
 * @package HelloProVision
 */

defined( 'ABSPATH' ) || exit;

/**
 * Meta definitions: key => array( post types, type, label ).
 */
function hpv_meta_fields() {
	$seo_types = array( 'page', 'post', 'case_study' );
	return array(
		// SEO (all content).
		'hpv_seo_title'       => array( $seo_types, 'string', 'SEO title' ),
		'hpv_seo_description' => array( $seo_types, 'string', 'Meta description' ),
		'hpv_noindex'         => array( $seo_types, 'boolean', 'Hide from search engines' ),
		// Page role (drives schema + analytics page_type).
		'hpv_page_type'       => array( array( 'page' ), 'string', 'Page role' ),
		'hpv_service_name'    => array( array( 'page' ), 'string', 'Service name (schema)' ),
		// Case study snapshot.
		'hpv_client'          => array( array( 'case_study' ), 'string', 'Client' ),
		'hpv_location'        => array( array( 'case_study' ), 'string', 'Location' ),
		'hpv_services'        => array( array( 'case_study' ), 'string', 'Services delivered' ),
		'hpv_timeline'        => array( array( 'case_study' ), 'string', 'Timeline' ),
		'hpv_website'         => array( array( 'case_study' ), 'string', 'Client website' ),
		'hpv_result_value'    => array( array( 'case_study' ), 'string', 'Headline result (number)' ),
		'hpv_result_context'  => array( array( 'case_study' ), 'string', 'Headline result (context + timeframe)' ),
		'hpv_result_source'   => array( array( 'case_study' ), 'string', 'Headline result source' ),
		'hpv_card_title'      => array( array( 'case_study' ), 'string', 'Card headline (result, not client name)' ),
		'hpv_featured'        => array( array( 'case_study' ), 'boolean', 'Featured case study' ),
	);
}

/**
 * Register all meta for REST so the editor can read and write it.
 */
function hpv_register_meta() {
	foreach ( hpv_meta_fields() as $key => $def ) {
		foreach ( $def[0] as $type ) {
			register_post_meta(
				$type,
				$key,
				array(
					'type'              => $def[1],
					'description'       => $def[2],
					'single'            => true,
					'show_in_rest'      => true,
					'default'           => 'boolean' === $def[1] ? false : '',
					'sanitize_callback' => 'boolean' === $def[1] ? 'rest_sanitize_boolean' : 'sanitize_text_field',
					'auth_callback'     => static function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}
}
add_action( 'init', 'hpv_register_meta' );
