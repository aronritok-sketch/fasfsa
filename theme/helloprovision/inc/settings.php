<?php
/**
 * Customizer: the business facts every page reuses (NAP, booking, analytics).
 *
 * @package HelloProVision
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register settings.
 *
 * @param WP_Customize_Manager $wp_customize Customizer.
 */
function hpv_customize_register( $wp_customize ) {
	$d = hpv_defaults();

	$wp_customize->add_panel(
		'hpv',
		array(
			'title'       => __( 'HelloProVision site', 'hpv' ),
			'description' => __( 'Business details, booking and analytics. Name, phone and address must match the Google Business Profile exactly.', 'hpv' ),
			'priority'    => 30,
		)
	);

	$sections = array(
		'hpv_business'  => __( 'Business details (NAP)', 'hpv' ),
		'hpv_leads'     => __( 'Strategy call & leads', 'hpv' ),
		'hpv_analytics' => __( 'Analytics & consent', 'hpv' ),
	);
	foreach ( $sections as $id => $title ) {
		$wp_customize->add_section( $id, array( 'title' => $title, 'panel' => 'hpv' ) );
	}

	$fields = array(
		// key => array( section, label, type, sanitize, description ).
		'legal_name'     => array( 'hpv_business', __( 'Legal business name', 'hpv' ), 'text', 'sanitize_text_field', __( 'e.g. HelloProVision LLC', 'hpv' ) ),
		'phone'          => array( 'hpv_business', __( 'Phone', 'hpv' ), 'text', 'sanitize_text_field', __( 'Local number, formatted as on Google Business Profile.', 'hpv' ) ),
		'email'          => array( 'hpv_business', __( 'Public email', 'hpv' ), 'email', 'sanitize_email', '' ),
		'address'        => array( 'hpv_business', __( 'Mailing address (optional)', 'hpv' ), 'text', 'sanitize_text_field', __( 'Leave empty for a service-area business.', 'hpv' ) ),
		'service_area'   => array( 'hpv_business', __( 'Service area line', 'hpv' ), 'text', 'sanitize_text_field', '' ),
		'positioning'    => array( 'hpv_business', __( 'Footer positioning line', 'hpv' ), 'text', 'sanitize_text_field', '' ),
		'linkedin'       => array( 'hpv_business', __( 'LinkedIn profile URL', 'hpv' ), 'url', 'esc_url_raw', '' ),
		'booking_url'    => array( 'hpv_leads', __( 'Booking page URL', 'hpv' ), 'url', 'esc_url_raw', __( 'Cal.com, Calendly or Google Calendar booking link. Name and email are passed along automatically.', 'hpv' ) ),
		'notify_email'   => array( 'hpv_leads', __( 'Send new leads to', 'hpv' ), 'email', 'sanitize_email', __( 'Defaults to the site admin email.', 'hpv' ) ),
		'reply_hours'    => array( 'hpv_leads', __( 'Reply promise (business hours)', 'hpv' ), 'number', 'absint', __( 'Used in confirmation emails and the lead manager’s "overdue" flag.', 'hpv' ) ),
		'ga4_id'         => array( 'hpv_analytics', __( 'GA4 measurement ID', 'hpv' ), 'text', 'hpv_sanitize_tracking_id', __( 'G-XXXXXXX. Leave empty to disable.', 'hpv' ) ),
		'ga4_api_secret' => array( 'hpv_analytics', __( 'GA4 Measurement Protocol API secret', 'hpv' ), 'text', 'sanitize_text_field', __( 'Optional. Lets the lead manager report “qualified_lead” and “close_convert_lead” to GA4 when you move a lead. GA4 → Admin → Data streams → Measurement Protocol.', 'hpv' ) ),
		'gtm_id'         => array( 'hpv_analytics', __( 'Google Tag Manager ID (alternative)', 'hpv' ), 'text', 'hpv_sanitize_tracking_id', __( 'GTM-XXXXXX. Use GA4 or GTM, not both.', 'hpv' ) ),
		'consent_banner' => array( 'hpv_analytics', __( 'Ask for cookie consent before analytics', 'hpv' ), 'checkbox', 'rest_sanitize_boolean', __( 'Uses Google Consent Mode v2. Required if you add advertising tags.', 'hpv' ) ),
		'show_tbd'       => array( 'hpv_analytics', __( 'Show “fact to verify” marks to logged-in editors', 'hpv' ), 'checkbox', 'rest_sanitize_boolean', __( 'Visitors never see them.', 'hpv' ) ),
	);

	foreach ( $fields as $key => $f ) {
		$wp_customize->add_setting(
			'hpv_' . $key,
			array(
				'default'           => $d[ $key ],
				'sanitize_callback' => $f[3],
				'transport'         => 'refresh',
			)
		);
		$wp_customize->add_control(
			'hpv_' . $key,
			array(
				'section'     => $f[0],
				'label'       => $f[1],
				'type'        => $f[2],
				'description' => $f[4],
			)
		);
	}
}
add_action( 'customize_register', 'hpv_customize_register' );

/**
 * Tracking IDs: letters, digits and dashes only.
 *
 * @param string $value Raw value.
 */
function hpv_sanitize_tracking_id( $value ) {
	return strtoupper( preg_replace( '/[^A-Za-z0-9-]/', '', (string) $value ) );
}
