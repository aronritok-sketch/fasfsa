<?php
/**
 * Insights hub (the posts page). Its layout is the content of the page assigned as
 * "Posts page" in Settings → Reading, so it stays editable; the article lists inside
 * are dynamic hpv/insights blocks.
 *
 * @package HelloProVision
 */

defined( 'ABSPATH' ) || exit;

get_header();

$hpvf_page_id = (int) get_option( 'page_for_posts' );
$hpvf_content = $hpvf_page_id ? get_post_field( 'post_content', $hpvf_page_id ) : '';

if ( $hpvf_content ) {
	echo apply_filters( 'the_content', $hpvf_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core filter output.
} else {
	echo hpvf_render_pattern( 'hpv/page-insights' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered blocks.
}

get_footer();
