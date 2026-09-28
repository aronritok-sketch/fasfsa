<?php
/**
 * HelloProVision Founder theme bootstrap.
 *
 * Everything the site needs is theme-native; no plugin is required.
 *
 * @package HelloProVision
 */

defined( 'ABSPATH' ) || exit;

define( 'HPVF_VERSION', '1.1.0' );
define( 'HPVF_DIR', get_template_directory() );
define( 'HPVF_URI', get_template_directory_uri() );

$hpvf_includes = array(
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
	'crm-bridge',    // Forward new leads to the HelloProVision CRM.
	'demo-import',   // One-click demo content (admin + WP-CLI).
);

foreach ( $hpvf_includes as $hpvf_file ) {
	require_once HPVF_DIR . '/inc/' . $hpvf_file . '.php';
}
unset( $hpvf_includes, $hpvf_file );
