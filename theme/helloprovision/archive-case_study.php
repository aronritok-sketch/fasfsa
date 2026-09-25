<?php
/**
 * Case studies hub (/case-studies/) and industry archives.
 *
 * The hub layout is the "Case studies hub" pattern; the list itself is the dynamic
 * hpv/case-grid block, so new case studies appear automatically.
 *
 * @package HelloProVision
 */

defined( 'ABSPATH' ) || exit;

get_header();
echo hpv_render_pattern( 'hpv/page-case-studies' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered blocks.
get_footer();
