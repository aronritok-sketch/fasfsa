<?php
/**
 * 404.
 *
 * @package HelloProVision
 */

defined( 'ABSPATH' ) || exit;

get_header();
echo hpv_render_pattern( 'hpv/page-404' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered blocks.
get_footer();
