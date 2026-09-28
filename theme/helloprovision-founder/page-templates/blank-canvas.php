<?php
/**
 * Template Name: Blank canvas (no header or footer)
 * Template Post Type: page
 *
 * For campaign landing pages: only the page's blocks, still with the theme's styles and tracking.
 *
 * @package HelloProVision
 */

defined( 'ABSPATH' ) || exit;

get_header();
while ( have_posts() ) {
	the_post();
	the_content();
}
get_footer();
