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
function hpvf_meta_fields() {
	$seo_types = array( 'page', 'post', 'case_study' );
	return array(
		// SEO (all content).
		'hpvf_seo_title'       => array( $seo_types, 'string', 'SEO title' ),
		'hpvf_seo_description' => array( $seo_types, 'string', 'Meta description' ),
		'hpvf_noindex'         => array( $seo_types, 'boolean', 'Hide from search engines' ),
		// Page role (drives schema + analytics page_type).
		'hpvf_page_type'       => array( array( 'page' ), 'string', 'Page role' ),
		'hpvf_service_name'    => array( array( 'page' ), 'string', 'Service name (schema)' ),
		// Case study snapshot.
		'hpvf_client'          => array( array( 'case_study' ), 'string', 'Client' ),
		'hpvf_location'        => array( array( 'case_study' ), 'string', 'Location' ),
		'hpvf_services'        => array( array( 'case_study' ), 'string', 'Services delivered' ),
		'hpvf_timeline'        => array( array( 'case_study' ), 'string', 'Timeline' ),
		'hpvf_website'         => array( array( 'case_study' ), 'string', 'Client website' ),
		'hpvf_result_value'    => array( array( 'case_study' ), 'string', 'Headline result (number)' ),
		'hpvf_result_context'  => array( array( 'case_study' ), 'string', 'Headline result (context + timeframe)' ),
		'hpvf_result_source'   => array( array( 'case_study' ), 'string', 'Headline result source' ),
		'hpvf_card_title'      => array( array( 'case_study' ), 'string', 'Card headline (result, not client name)' ),
		'hpvf_featured'        => array( array( 'case_study' ), 'boolean', 'Featured case study' ),
	);
}

/**
 * Register all meta for REST so the editor can read and write it.
 */
function hpvf_register_meta() {
	foreach ( hpvf_meta_fields() as $key => $def ) {
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
add_action( 'init', 'hpvf_register_meta' );
