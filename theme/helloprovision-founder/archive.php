<?php
/**
 * Topic hubs (/insights/{topic}/) and other archives.
 *
 * @package HelloProVision
 */

defined( 'ABSPATH' ) || exit;

get_header();

$hpvf_term    = get_queried_object();
$hpvf_is_cat  = is_category();
$hpvf_service = $hpvf_is_cat ? hpvf_topic_service( $hpvf_term->slug ) : null;
?>
<section class="hero hero--inner" id="hero">
	<div class="wrap grid">
		<div class="hero__text col-9">
			<?php hpvf_breadcrumbs(); ?>
			<span class="eyebrow"><?php echo $hpvf_is_cat ? esc_html__( 'Insights', 'hpv' ) : esc_html__( 'Archive', 'hpv' ); ?></span>
			<h1 class="display"><?php echo esc_html( single_term_title( '', false ) ? single_term_title( '', false ) : get_the_archive_title() ); ?></h1>
			<?php if ( term_description() ) : ?>
				<div class="lead"><?php echo wp_kses_post( term_description() ); ?></div>
			<?php endif; ?>
			<?php if ( $hpvf_service ) : ?>
				<p><?php esc_html_e( 'Related service:', 'hpv' ); ?> <a class="link" href="<?php echo esc_url( $hpvf_service[1] ); ?>"><?php echo esc_html( $hpvf_service[0] ); ?></a></p>
			<?php endif; ?>
		</div>
	</div>
</section>

<section class="section section--flush-top">
	<div class="wrap">
		<?php
		if ( $hpvf_is_cat ) {
			echo hpvf_render_insights( array( 'topic' => $hpvf_term->slug, 'count' => 12, 'fill' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
		} elseif ( have_posts() ) {
			echo '<div class="insights">';
			while ( have_posts() ) {
				the_post();
				echo hpvf_insight_card( get_post() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
			}
			echo '</div>';
			the_posts_pagination();
		}
		?>
	</div>
</section>
<?php
echo hpvf_render_pattern( 'hpv/scorecard-band' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered blocks.
get_footer();
