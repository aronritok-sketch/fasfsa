<?php
/**
 * JSON-LD graph (blueprint §8): one Person @id at the centre, the studio as ProfessionalService,
 * plus page-specific nodes. Only true, visible facts: no ratings, no invented awards.
 *
 * @package HelloProVision
 */

defined( 'ABSPATH' ) || exit;

/**
 * Build the graph for this request.
 */
function hpvf_schema_graph() {
	$home   = home_url( '/' );
	$person = $home . '#person';
	$org    = $home . '#org';
	$about  = get_page_by_path( 'about' );
	$area   = array(
		array( '@type' => 'City', 'name' => 'Fort Myers' ),
		array( '@type' => 'City', 'name' => 'Naples' ),
		array( '@type' => 'City', 'name' => 'Cape Coral' ),
		array( '@type' => 'State', 'name' => 'Florida' ),
	);

	$person_node = array(
		'@type'      => 'Person',
		'@id'        => $person,
		'name'       => get_bloginfo( 'name' ),
		'jobTitle'   => get_bloginfo( 'description' ),
		'url'        => $about ? get_permalink( $about ) : $home,
		'worksFor'   => array( '@id' => $org ),
		'knowsAbout' => array( 'Digital growth strategy', 'Website design', 'Local SEO', 'Conversion optimization' ),
	);
	if ( hpvf_theme_image( 'people/aron-headshot-square' ) ) {
		$person_node['image'] = hpvf_theme_image( 'people/aron-headshot-square' );
	}
	if ( hpvf_opt( 'linkedin' ) ) {
		$person_node['sameAs'] = array( hpvf_opt( 'linkedin' ) );
	}

	$org_node = array(
		'@type'      => 'ProfessionalService',
		'@id'        => $org,
		'name'       => 'HelloProVision',
		'founder'    => array( '@id' => $person ),
		'url'        => $home,
		'areaServed' => $area,
	);
	if ( hpvf_opt( 'legal_name' ) ) {
		$org_node['legalName'] = hpvf_opt( 'legal_name' );
	}
	if ( hpvf_opt( 'phone' ) ) {
		$org_node['telephone'] = hpvf_opt( 'phone' );
	}
	if ( hpvf_opt( 'email' ) ) {
		$org_node['email'] = hpvf_opt( 'email' );
	}
	if ( hpvf_opt( 'address' ) ) {
		$org_node['address'] = hpvf_opt( 'address' );
	}

	$graph = array(
		$person_node,
		$org_node,
		array( '@type' => 'WebSite', '@id' => $home . '#website', 'url' => $home, 'name' => get_bloginfo( 'name' ), 'publisher' => array( '@id' => $person ) ),
	);

	$url  = hpvf_canonical();
	$url  = is_wp_error( $url ) || ! $url ? home_url( add_query_arg( array() ) ) : $url;
	$role = is_page() ? get_post_meta( get_queried_object_id(), 'hpvf_page_type', true ) : '';
	$page = array(
		'@type'       => 'about' === $role ? 'ProfilePage' : ( ( is_home() || is_archive() ) ? 'CollectionPage' : 'WebPage' ),
		'@id'         => $url . '#webpage',
		'url'         => $url,
		'name'        => wp_get_document_title(),
		'description' => hpvf_meta_description(),
		'isPartOf'    => array( '@id' => $home . '#website' ),
	);
	if ( 'about' === $role ) {
		$page['mainEntity'] = array( '@id' => $person );
	}
	$graph[] = $page;

	$crumbs = hpvf_breadcrumb_items();
	if ( $crumbs ) {
		$items = array();
		foreach ( $crumbs as $i => $c ) {
			$items[] = array_filter( array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0], 'item' => $c[1] ? $c[1] : $url ) );
		}
		$graph[] = array( '@type' => 'BreadcrumbList', '@id' => $url . '#breadcrumbs', 'itemListElement' => $items );
	}

	if ( 'service' === $role ) {
		$name    = get_post_meta( get_queried_object_id(), 'hpvf_service_name', true );
		$graph[] = array(
			'@type'       => 'Service',
			'@id'         => $url . '#service',
			'name'        => $name ? $name : get_the_title(),
			'serviceType' => $name ? $name : get_the_title(),
			'url'         => $url,
			'provider'    => array( '@id' => $person ),
			'areaServed'  => $area,
		);
	}

	if ( is_singular( 'post' ) ) {
		$graph[] = array_filter(
			array(
				'@type'            => 'BlogPosting',
				'@id'              => $url . '#article',
				'headline'         => get_the_title(),
				'description'      => hpvf_meta_description(),
				'datePublished'    => get_the_date( 'c' ),
				'dateModified'     => get_the_modified_date( 'c' ),
				'author'           => array( '@id' => $person ),
				'publisher'        => array( '@id' => $person ),
				'mainEntityOfPage' => array( '@id' => $url . '#webpage' ),
				'image'            => has_post_thumbnail() ? get_the_post_thumbnail_url( null, 'large' ) : null,
			)
		);
	}

	if ( is_singular( 'case_study' ) ) {
		$graph[] = array_filter(
			array(
				'@type'         => 'Article',
				'@id'           => $url . '#article',
				'headline'      => wp_strip_all_tags( get_the_title() ),
				'about'         => get_post_meta( get_the_ID(), 'hpvf_client', true ) ? array( '@type' => 'Organization', 'name' => get_post_meta( get_the_ID(), 'hpvf_client', true ) ) : null,
				'author'        => array( '@id' => $person ),
				'publisher'     => array( '@id' => $org ),
				'datePublished' => get_the_date( 'c' ),
				'image'         => has_post_thumbnail() ? get_the_post_thumbnail_url( null, 'large' ) : null,
			)
		);
	}

	return apply_filters( 'hpvf_schema_graph', $graph );
}

/**
 * Print the graph.
 */
function hpvf_schema_print() {
	if ( hpvf_seo_plugin_active() || is_404() ) {
		return;
	}
	$json = wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => hpvf_schema_graph() ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	echo '<script type="application/ld+json">' . str_replace( '</', '<\/', $json ) . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded.
}
add_action( 'wp_head', 'hpvf_schema_print', 20 );
