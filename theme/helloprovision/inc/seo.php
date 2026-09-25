<?php
/**
 * Theme-native SEO: titles, meta descriptions, canonicals, Open Graph, robots, sitemaps.
 * Steps aside automatically when an SEO plugin (Yoast, Rank Math, SEOPress, AIOSEO) is active.
 *
 * @package HelloProVision
 */

defined( 'ABSPATH' ) || exit;

/**
 * Is a dedicated SEO plugin handling this?
 */
function hpv_seo_plugin_active() {
	return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || defined( 'AIOSEO_VERSION' );
}

/**
 * The object whose SEO fields apply to this request (posts page for the Insights hub).
 */
function hpv_seo_object_id() {
	if ( is_home() && get_option( 'page_for_posts' ) ) {
		return (int) get_option( 'page_for_posts' );
	}
	if ( is_front_page() && get_option( 'page_on_front' ) ) {
		return (int) get_option( 'page_on_front' );
	}
	return is_singular() ? (int) get_queried_object_id() : 0;
}

/**
 * Title.
 *
 * @param string $title Title from core.
 */
function hpv_document_title( $title ) {
	if ( hpv_seo_plugin_active() ) {
		return $title;
	}
	$site = get_bloginfo( 'name' );
	$id   = hpv_seo_object_id();
	if ( $id && get_post_meta( $id, 'hpv_seo_title', true ) ) {
		return get_post_meta( $id, 'hpv_seo_title', true );
	}
	if ( is_post_type_archive( 'case_study' ) ) {
		/* translators: %s: site name */
		return sprintf( __( 'Case Studies — Digital Growth Results | %s', 'hpv' ), $site );
	}
	if ( is_category() ) {
		/* translators: 1: topic, 2: site name */
		return sprintf( __( '%1$s — Insights for Florida Business Owners | %2$s', 'hpv' ), single_cat_title( '', false ), $site );
	}
	return $title;
}
add_filter( 'pre_get_document_title', 'hpv_document_title', 20 );

/**
 * Separator.
 */
add_filter(
	'document_title_separator',
	static function () {
		return '—';
	}
);

/**
 * Meta description for this request.
 */
function hpv_meta_description() {
	$id = hpv_seo_object_id();
	if ( $id ) {
		$d = get_post_meta( $id, 'hpv_seo_description', true );
		if ( $d ) {
			return $d;
		}
		if ( has_excerpt( $id ) ) {
			return wp_strip_all_tags( get_the_excerpt( $id ) );
		}
	}
	if ( is_post_type_archive( 'case_study' ) ) {
		return __( 'Real projects, real numbers: websites, local SEO and growth strategy for service and hospitality businesses.', 'hpv' );
	}
	if ( is_category() && category_description() ) {
		return wp_strip_all_tags( category_description() );
	}
	return get_bloginfo( 'description' );
}

/**
 * Canonical URL for this request (core already prints it for singular content).
 */
function hpv_canonical() {
	if ( is_singular() ) {
		return wp_get_canonical_url();
	}
	if ( is_front_page() ) {
		return home_url( '/' );
	}
	if ( is_home() && get_option( 'page_for_posts' ) ) {
		return get_permalink( get_option( 'page_for_posts' ) );
	}
	if ( is_post_type_archive( 'case_study' ) ) {
		return get_post_type_archive_link( 'case_study' );
	}
	if ( is_category() || is_tax() ) {
		return get_term_link( get_queried_object() );
	}
	return '';
}

/**
 * Head tags.
 */
function hpv_seo_head() {
	if ( hpv_seo_plugin_active() ) {
		return;
	}
	$desc      = hpv_meta_description();
	$canonical = hpv_canonical();
	$title     = wp_get_document_title();
	$image     = '';
	if ( is_singular() && has_post_thumbnail() ) {
		$image = get_the_post_thumbnail_url( null, 'large' );
	} elseif ( hpv_theme_image( 'og-default' ) ) {
		$image = hpv_theme_image( 'og-default' );
	}

	if ( $desc ) {
		printf( '<meta name="description" content="%s">' . "\n", esc_attr( wp_trim_words( $desc, 40, '…' ) ) );
	}
	if ( $canonical && ! is_singular() && ! is_wp_error( $canonical ) ) {
		printf( '<link rel="canonical" href="%s">' . "\n", esc_url( $canonical ) );
	}
	$og = array(
		'og:type'        => is_singular( 'post' ) ? 'article' : 'website',
		'og:title'       => $title,
		'og:description' => $desc,
		'og:url'         => is_wp_error( $canonical ) ? '' : $canonical,
		'og:site_name'   => get_bloginfo( 'name' ),
		'og:locale'      => str_replace( '-', '_', get_bloginfo( 'language' ) ),
		'og:image'       => $image,
	);
	foreach ( $og as $prop => $value ) {
		if ( $value ) {
			printf( '<meta property="%s" content="%s">' . "\n", esc_attr( $prop ), esc_attr( $value ) );
		}
	}
	printf( '<meta name="twitter:card" content="%s">' . "\n", $image ? 'summary_large_image' : 'summary' );
	if ( is_singular( 'post' ) ) {
		printf( '<meta property="article:published_time" content="%s">' . "\n", esc_attr( get_the_date( 'c' ) ) );
		printf( '<meta property="article:modified_time" content="%s">' . "\n", esc_attr( get_the_modified_date( 'c' ) ) );
	}
}
add_action( 'wp_head', 'hpv_seo_head', 2 );

/**
 * Robots: per-page noindex, thank-you pages, internal search, and every non-production site.
 *
 * @param array $robots Directives.
 */
function hpv_robots( $robots ) {
	$id      = hpv_seo_object_id();
	$noindex = ( $id && get_post_meta( $id, 'hpv_noindex', true ) )
		|| ( $id && 'thank_you' === get_post_meta( $id, 'hpv_page_type', true ) )
		|| is_search()
		|| ( function_exists( 'wp_get_environment_type' ) && 'production' !== wp_get_environment_type() && ! defined( 'HPV_ALLOW_INDEXING' ) );
	if ( $noindex && ! hpv_seo_plugin_active() ) {
		$robots['noindex'] = true;
		$robots['follow']  = true;
		unset( $robots['max-image-preview'] );
	}
	return $robots;
}
add_filter( 'wp_robots', 'hpv_robots', 20 );

/**
 * Sitemaps: no users, no noindex content.
 *
 * @param array $args Query args.
 */
function hpv_sitemap_query( $args ) {
	$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		'relation' => 'OR',
		array( 'key' => 'hpv_noindex', 'compare' => 'NOT EXISTS' ),
		array( 'key' => 'hpv_noindex', 'value' => '1', 'compare' => '!=' ),
	);
	return $args;
}
add_filter( 'wp_sitemaps_posts_query_args', 'hpv_sitemap_query' );
add_filter(
	'wp_sitemaps_add_provider',
	static function ( $provider, $name ) {
		return 'users' === $name ? false : $provider;
	},
	10,
	2
);
