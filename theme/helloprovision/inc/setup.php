<?php
/**
 * Theme setup, assets and head cleanup.
 *
 * @package HelloProVision
 */

defined( 'ABSPATH' ) || exit;

/**
 * Theme supports and menus.
 */
function hpv_setup() {
	load_theme_textdomain( 'hpv', HPV_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'wp-block-styles' );
	remove_theme_support( 'core-block-patterns' );

	add_editor_style( array( 'assets/css/main.css', 'assets/css/editor.css' ) );

	register_nav_menus(
		array(
			'primary'          => __( 'Primary navigation', 'hpv' ),
			'footer_services'  => __( 'Footer: Services', 'hpv' ),
			'footer_explore'   => __( 'Footer: Explore', 'hpv' ),
			'legal'            => __( 'Footer: Legal', 'hpv' ),
		)
	);

	add_image_size( 'hpv-shot', 1600, 1200, false );
	add_image_size( 'hpv-portrait', 1000, 1250, true );
}
add_action( 'after_setup_theme', 'hpv_setup' );

/**
 * Load only the CSS of core blocks actually used on a page.
 */
add_filter( 'should_load_separate_core_block_assets', '__return_true' );

/**
 * Front-end assets.
 */
function hpv_assets() {
	$ver = static function ( $rel ) {
		$file = HPV_DIR . '/' . $rel;
		return file_exists( $file ) ? (string) filemtime( $file ) : HPV_VERSION;
	};

	wp_enqueue_style( 'hpv-main', HPV_URI . '/assets/css/main.css', array(), $ver( 'assets/css/main.css' ) );

	wp_enqueue_script( 'hpv-site', HPV_URI . '/assets/js/site.js', array(), $ver( 'assets/js/site.js' ), array( 'strategy' => 'defer', 'in_footer' => true ) );
	wp_localize_script(
		'hpv-site',
		'hpvConfig',
		array(
			'rest'       => esc_url_raw( rest_url( 'hpv/v1/' ) ),
			'bookingUrl' => esc_url_raw( hpv_opt( 'booking_url' ) ),
			'thankYou'   => esc_url_raw( hpv_page_url( 'strategy-call/thank-you' ) ),
			'insights'   => esc_url_raw( get_permalink( get_option( 'page_for_posts' ) ) ? get_permalink( get_option( 'page_for_posts' ) ) : home_url( '/insights/' ) ),
			'articles'   => hpv_scorecard_articles(),
		)
	);

	if ( is_singular() && has_block( 'hpv/strategy-call-form' ) ) {
		wp_enqueue_script( 'hpv-forms', HPV_URI . '/assets/js/forms.js', array( 'hpv-site' ), $ver( 'assets/js/forms.js' ), array( 'strategy' => 'defer', 'in_footer' => true ) );
	}
	if ( is_singular() && has_block( 'hpv/scorecard' ) ) {
		wp_enqueue_script( 'hpv-scorecard', HPV_URI . '/assets/js/scorecard.js', array( 'hpv-site' ), $ver( 'assets/js/scorecard.js' ), array( 'strategy' => 'defer', 'in_footer' => true ) );
	}
}
add_action( 'wp_enqueue_scripts', 'hpv_assets' );

/**
 * Scorecard: recommended reading per bottleneck area (URL + title), resolved to real posts when they exist.
 */
function hpv_scorecard_articles() {
	$map = array(
		'positioning' => 'website-traffic-no-calls',
		'website'     => 'website-traffic-no-calls',
		'search'      => '',
		'conversion'  => '',
		'followup'    => '',
	);
	$out = array();
	foreach ( $map as $area => $slug ) {
		$post = $slug ? get_page_by_path( $slug, OBJECT, 'post' ) : null;
		if ( $post && 'publish' === $post->post_status ) {
			$out[ $area ] = array( get_permalink( $post ), get_the_title( $post ) );
		}
	}
	return $out;
}

/**
 * Preload the poster font used above the fold.
 */
function hpv_preload_fonts() {
	printf(
		'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
		esc_url( HPV_URI . '/assets/fonts/anton-latin-400-normal.woff2' )
	);
}
add_action( 'wp_head', 'hpv_preload_fonts', 1 );

/**
 * Lean head: no emoji scripts, RSD, WLW, shortlink or generator tags.
 */
function hpv_head_cleanup() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
	remove_action( 'wp_head', 'wp_generator' );
}
add_action( 'init', 'hpv_head_cleanup' );

/**
 * html element classes: editors see prototype marks.
 */
function hpv_html_class() {
	return hpv_is_editor_view() ? 'hpv-edit' : '';
}

/**
 * Body data attributes for analytics.
 */
function hpv_body_attrs() {
	printf( ' data-page-type="%s"', esc_attr( hpv_page_type() ) );
}

/**
 * Page template "Blank canvas" gets no header/footer (landing pages).
 */
function hpv_is_blank_canvas() {
	return is_page_template( 'page-templates/blank-canvas.php' );
}

/**
 * Comments are not part of this site: close them everywhere.
 */
add_filter( 'comments_open', '__return_false', 20 );
add_filter( 'pings_open', '__return_false', 20 );
function hpv_disable_comments_ui() {
	foreach ( array( 'post', 'page' ) as $type ) {
		remove_post_type_support( $type, 'comments' );
		remove_post_type_support( $type, 'trackbacks' );
	}
}
add_action( 'init', 'hpv_disable_comments_ui', 100 );
