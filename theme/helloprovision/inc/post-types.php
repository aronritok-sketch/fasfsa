<?php
/**
 * Content types: case studies (+ industries) and private leads.
 *
 * Note: post types registered by a theme disappear from the admin if the theme is switched.
 * The content stays in the database. Copy this file into wp-content/mu-plugins/ to keep it
 * independent of the theme.
 *
 * @package HelloProVision
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register post types and taxonomies.
 */
function hpv_register_content_types() {
	if ( post_type_exists( 'case_study' ) ) {
		return; // Already provided by an mu-plugin copy.
	}

	register_post_type(
		'case_study',
		array(
			'labels'        => array(
				'name'               => __( 'Case studies', 'hpv' ),
				'singular_name'      => __( 'Case study', 'hpv' ),
				'add_new_item'       => __( 'Add case study', 'hpv' ),
				'edit_item'          => __( 'Edit case study', 'hpv' ),
				'all_items'          => __( 'All case studies', 'hpv' ),
				'view_item'          => __( 'View case study', 'hpv' ),
				'search_items'       => __( 'Search case studies', 'hpv' ),
				'not_found'          => __( 'No case studies yet.', 'hpv' ),
				'featured_image'     => __( 'Project screenshot (hero)', 'hpv' ),
				'set_featured_image' => __( 'Set project screenshot', 'hpv' ),
			),
			'public'        => true,
			'show_in_rest'  => true,
			'menu_icon'     => 'dashicons-portfolio',
			'menu_position' => 21,
			'has_archive'   => 'case-studies',
			'rewrite'       => array( 'slug' => 'case-studies', 'with_front' => false ),
			'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions', 'page-attributes' ),
			'template'      => array( array( 'core/pattern', array( 'slug' => 'hpv/case-study-body' ) ) ),
		)
	);

	register_taxonomy(
		'industry',
		array( 'case_study' ),
		array(
			'labels'            => array(
				'name'          => __( 'Industries', 'hpv' ),
				'singular_name' => __( 'Industry', 'hpv' ),
			),
			// Used for the filter chips on the case study hub; no thin archive pages of its own.
			'public'            => false,
			'show_ui'           => true,
			'hierarchical'      => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => false,
			'query_var'         => false,
		)
	);

	register_post_type(
		'hpv_lead',
		array(
			'labels'          => array(
				'name'          => __( 'Leads', 'hpv' ),
				'singular_name' => __( 'Lead', 'hpv' ),
				'edit_item'     => __( 'Lead', 'hpv' ),
				'all_items'     => __( 'All leads', 'hpv' ),
				'search_items'  => __( 'Search leads', 'hpv' ),
				'not_found'     => __( 'No leads yet. They appear here as soon as someone books a call or completes the scorecard.', 'hpv' ),
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'show_in_rest'    => false,
			'menu_icon'       => 'dashicons-groups',
			'menu_position'   => 3,
			'supports'        => array( 'title' ),
			'capability_type' => 'post',
			'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap'    => true,
		)
	);
}
add_action( 'init', 'hpv_register_content_types', 5 );

/**
 * Flush rewrite rules once after the theme is activated or updated.
 */
function hpv_maybe_flush_rewrites() {
	if ( get_option( 'hpv_rewrite_version' ) !== HPV_VERSION ) {
		flush_rewrite_rules( false );
		update_option( 'hpv_rewrite_version', HPV_VERSION, false );
	}
}
add_action( 'init', 'hpv_maybe_flush_rewrites', 99 );
add_action( 'after_switch_theme', 'flush_rewrite_rules' );
