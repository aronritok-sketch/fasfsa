<?php
/**
 * HelloProVision Founder theme bootstrap.
 *
 * Everything the site needs is theme-native; no plugin is required.
 *
 * @package HelloProVision
 */

defined( 'ABSPATH' ) || exit;

define( 'HPV_VERSION', '1.0.0' );
define( 'HPV_DIR', get_template_directory() );
define( 'HPV_URI', get_template_directory_uri() );

$hpv_includes = array(
	'helpers',       // Small shared helpers (options, links, icons).
	'setup',         // Theme supports, menus, assets, head cleanup.
	'settings',      // Customizer: business details, booking, analytics.
	'post-types',    // Case studies, industries, leads.
	'meta',          // Registered post meta (SEO, case study snapshot).
	'permalinks',    // /insights/ structure for posts and topics.
	'blocks',        // Dynamic blocks, formats, pattern categories.
	'seo',           // Title, description, canonical, Open Graph, robots.
	'schema',        // JSON-LD graph.
	'tracking',      // GA4 / GTM and consent.
	'leads',         // Lead storage, REST endpoints, notifications.
	'lead-admin',    // Lead manager screens, pipeline, export, dashboard.
	'demo-import',   // One-click demo content (admin + WP-CLI).
);

foreach ( $hpv_includes as $hpv_file ) {
	require_once HPV_DIR . '/inc/' . $hpv_file . '.php';
}
unset( $hpv_includes, $hpv_file );
