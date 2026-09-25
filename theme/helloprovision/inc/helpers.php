<?php
/**
 * Shared helpers.
 *
 * @package HelloProVision
 */

defined( 'ABSPATH' ) || exit;

/**
 * Default values for every theme setting (Customizer). Empty means "not provided yet".
 */
function hpv_defaults() {
	return array(
		'legal_name'     => '',
		'phone'          => '',
		'email'          => '',
		'address'        => '',
		'service_area'   => 'Fort Myers · Naples · Cape Coral · Southwest Florida',
		'positioning'    => 'Digital growth systems for established Southwest Florida service businesses.',
		'linkedin'       => '',
		'booking_url'    => '',
		'notify_email'   => '',
		'reply_hours'    => '1',
		'ga4_id'         => '',
		'ga4_api_secret' => '',
		'gtm_id'         => '',
		'consent_banner' => false,
		'show_tbd'       => true,
	);
}

/**
 * Read a theme setting.
 *
 * @param string $key Setting key without prefix.
 * @return mixed
 */
function hpv_opt( $key ) {
	$defaults = hpv_defaults();
	$value    = get_theme_mod( 'hpv_' . $key, $defaults[ $key ] ?? '' );
	return is_string( $value ) ? trim( $value ) : $value;
}

/**
 * Whether the current viewer should see prototype "fact to verify" marks and the editor bar.
 */
function hpv_is_editor_view() {
	return is_user_logged_in() && current_user_can( 'edit_pages' ) && hpv_opt( 'show_tbd' );
}

/**
 * Print a value, or a visible placeholder for editors when it's still missing.
 *
 * @param string $value       Value to print (already safe for the context via $escape).
 * @param string $placeholder Placeholder text for editors, e.g. "[+1 (239) 000-0000]".
 */
function hpv_value_or_tbd( $value, $placeholder ) {
	if ( '' !== (string) $value ) {
		echo esc_html( $value );
	} elseif ( hpv_is_editor_view() ) {
		echo '<span class="tbd">' . esc_html( $placeholder ) . '</span>';
	}
}

/**
 * tel: link target from a human phone number.
 *
 * @param string $phone Phone as displayed.
 */
function hpv_tel( $phone ) {
	$digits = preg_replace( '/[^0-9+]/', '', (string) $phone );
	return 'tel:' . $digits;
}

/**
 * The page used for a given purpose, found by slug path (set by the demo importer).
 *
 * @param string $path Page path, e.g. "strategy-call".
 * @return string URL (falls back to home_url( $path )).
 */
function hpv_page_url( $path ) {
	$page = get_page_by_path( trim( $path, '/' ) );
	return $page ? get_permalink( $page ) : home_url( '/' . trim( $path, '/' ) . '/' );
}

/**
 * Current page type used by analytics and body data attributes.
 */
function hpv_page_type() {
	if ( is_front_page() ) {
		return 'home';
	}
	if ( is_singular( 'case_study' ) ) {
		return 'case_study';
	}
	if ( is_post_type_archive( 'case_study' ) || is_tax( 'industry' ) ) {
		return 'case_hub';
	}
	if ( is_singular( 'post' ) ) {
		return 'article';
	}
	if ( is_home() || is_category() ) {
		return 'insights_hub';
	}
	if ( is_404() ) {
		return 'error';
	}
	if ( is_page() ) {
		$type = get_post_meta( get_queried_object_id(), 'hpv_page_type', true );
		return $type ? $type : 'page';
	}
	return 'page';
}

/**
 * Planned Insights articles shown as "In writing" cards until they are published.
 * Filter "hpv_planned_articles" to change them.
 */
function hpv_planned_articles() {
	return apply_filters(
		'hpv_planned_articles',
		array(
			'websites'  => array( 'What a contractor website needs to convert', 'Website redesign cost in Southwest Florida: what drives the price', '12 things to check before hiring a web designer' ),
			'local-seo' => array( 'How to rank in the Google map pack in Fort Myers', 'Google Business Profile checklist for Florida service businesses', 'How many reviews do you need to compete locally?', 'Service area pages vs city pages: what works' ),
			'growth'    => array( 'Why more ads won’t fix a leaky funnel', 'The 5 numbers every owner should track monthly', 'Marketplace leads vs owning your pipeline', 'Speed-to-lead: why the first hour decides the job' ),
		)
	);
}

/**
 * Allowed inline HTML for short editable strings (headlines with marks).
 */
function hpv_inline_kses() {
	return array(
		'mark'   => array( 'class' => true ),
		'span'   => array( 'class' => true ),
		'em'     => array(),
		'strong' => array(),
		'br'     => array(),
		'a'      => array( 'href' => true, 'class' => true ),
	);
}

/**
 * Fallback links per menu location, used until menus are assigned (the demo importer assigns them).
 *
 * @param string $location Menu location.
 * @return array[] Each item: array( title, url ).
 */
function hpv_menu_fallback( $location ) {
	$cases    = get_post_type_archive_link( 'case_study' );
	$insights = get_option( 'page_for_posts' ) ? get_permalink( get_option( 'page_for_posts' ) ) : home_url( '/insights/' );
	$items    = array(
		'primary'         => array(
			array( __( 'Services', 'hpv' ), hpv_page_url( 'services' ) ),
			array( __( 'Case Studies', 'hpv' ), $cases ),
			array( __( 'About', 'hpv' ), hpv_page_url( 'about' ) ),
			array( __( 'Insights', 'hpv' ), $insights ),
		),
		'footer_services' => array(
			array( __( 'Digital Growth Strategy', 'hpv' ), hpv_page_url( 'services/digital-growth-strategy' ) ),
			array( __( 'Website Design & Development', 'hpv' ), hpv_page_url( 'services/website-design-development' ) ),
			array( __( 'Local SEO', 'hpv' ), hpv_page_url( 'services/local-seo' ) ),
		),
		'footer_explore'  => array(
			array( __( 'Case Studies', 'hpv' ), $cases ),
			array( __( 'About', 'hpv' ), hpv_page_url( 'about' ) ),
			array( __( 'Insights', 'hpv' ), $insights ),
			array( __( 'Growth Scorecard', 'hpv' ), hpv_page_url( 'growth-scorecard' ) ),
		),
		'legal'           => array(
			array( __( 'Privacy Policy', 'hpv' ), hpv_page_url( 'privacy-policy' ) ),
			array( __( 'Terms', 'hpv' ), hpv_page_url( 'terms' ) ),
			array( __( 'Accessibility', 'hpv' ), hpv_page_url( 'accessibility' ) ),
		),
	);
	return $items[ $location ] ?? array();
}

/**
 * Print the <li> items of a menu location in the theme's markup (no walker needed).
 *
 * @param string $location Menu location.
 * @param string $style    nav | mobile | plain.
 */
function hpv_menu_items( $location, $style = 'plain' ) {
	$items     = array();
	$locations = get_nav_menu_locations();
	if ( ! empty( $locations[ $location ] ) ) {
		$menu_items = wp_get_nav_menu_items( $locations[ $location ] );
		foreach ( (array) $menu_items as $mi ) {
			if ( 0 === (int) $mi->menu_item_parent ) {
				$items[] = array( $mi->title, $mi->url );
			}
		}
	}
	if ( ! $items ) {
		$items = hpv_menu_fallback( $location );
	}

	global $wp;
	$current = trailingslashit( wp_parse_url( home_url( $wp->request ?? '' ), PHP_URL_PATH ) ?? '/' );

	foreach ( $items as $item ) {
		list( $title, $url ) = $item;
		$path     = trailingslashit( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		$home     = trailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
		$is_cur   = 'nav' === $style && $path !== $home && 0 === strpos( $current, $path );
		$attrs    = $is_cur ? ' aria-current="page"' : '';
		$arrow    = 'mobile' === $style ? ' <span aria-hidden="true">→</span>' : '';
		printf( '<li><a href="%s"%s>%s%s</a></li>', esc_url( $url ), $attrs, esc_html( $title ), $arrow ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $attrs and $arrow are static.
	}
}

/**
 * Footer billboard: the site name with its hyphen in pink.
 */
function hpv_giant_wordmark() {
	$name = esc_html( get_bloginfo( 'name' ) );
	return preg_replace( '/-/', '<span>-</span>', $name, 1 );
}

/**
 * Swap the prototype's business-detail placeholders for the Customizer values, so the
 * email/phone/company written into page content stays in sync with the footer and schema.
 * Placeholders without a value stay marked for editors (and are listed on the setup screen).
 *
 * @param string $content Rendered content.
 */
function hpv_fill_business_tokens( $content ) {
	if ( false === strpos( $content, 'class="tbd"' ) && false === strpos( $content, 'hello@example.com' ) ) {
		return $content;
	}
	$email   = hpv_opt( 'email' );
	$phone   = hpv_opt( 'phone' );
	$company = trim( hpv_opt( 'legal_name' ) . ( hpv_opt( 'address' ) ? ', ' . hpv_opt( 'address' ) : '' ), ', ' );
	$map     = array();
	if ( $email ) {
		$map['mailto:hello@example.com']              = 'mailto:' . antispambot( $email );
		$map['<span class="tbd">[hello@domain]</span>'] = esc_html( antispambot( $email ) );
	}
	if ( $phone ) {
		$map['<span class="tbd">[+1 (239) 000-0000]</span>'] = '<a href="' . esc_url( hpv_tel( $phone ) ) . '" data-track="click_to_call">' . esc_html( $phone ) . '</a>';
	}
	if ( $company ) {
		$map['<span class="tbd">[HelloProVision LLC, mailing address]</span>'] = esc_html( $company );
	}
	return $map ? strtr( $content, $map ) : $content;
}
add_filter( 'the_content', 'hpv_fill_business_tokens', 20 );
