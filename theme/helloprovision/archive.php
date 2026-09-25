<?php
/**
 * Topic hubs (/insights/{topic}/) and other archives.
 *
 * @package HelloProVision
 */

defined( 'ABSPATH' ) || exit;

get_header();

$hpv_term    = get_queried_object();
$hpv_is_cat  = is_category();
$hpv_service = $hpv_is_cat ? hpv_topic_service( $hpv_term->slug ) : null;
?>
<section class="hero hero--inner" id="hero">
	<div class="wrap grid">
		<div class="hero__text col-9">
			<?php hpv_breadcrumbs(); ?>
			<span class="eyebrow"><?php echo $hpv_is_cat ? esc_html__( 'Insights', 'hpv' ) : esc_html__( 'Archive', 'hpv' ); ?></span>
			<h1 class="display"><?php echo esc_html( single_term_title( '', false ) ? single_term_title( '', false ) : get_the_archive_title() ); ?></h1>
			<?php if ( term_description() ) : ?>
				<div class="lead"><?php echo wp_kses_post( term_description() ); ?></div>
			<?php endif; ?>
			<?php if ( $hpv_service ) : ?>
				<p><?php esc_html_e( 'Related service:', 'hpv' ); ?> <a class="link" href="<?php echo esc_url( $hpv_service[1] ); ?>"><?php echo esc_html( $hpv_service[0] ); ?></a></p>
			<?php endif; ?>
		</div>
	</div>
</section>

<section class="section section--flush-top">
	<div class="wrap">
		<?php
		if ( $hpv_is_cat ) {
			echo hpv_render_insights( array( 'topic' => $hpv_term->slug, 'count' => 12, 'fill' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
		} elseif ( have_posts() ) {
			echo '<div class="insights">';
			while ( have_posts() ) {
				the_post();
				echo hpv_insight_card( get_post() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
			}
			echo '</div>';
			the_posts_pagination();
		}
		?>
	</div>
</section>
<?php
echo hpv_render_pattern( 'hpv/scorecard-band' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered blocks.
get_footer();
