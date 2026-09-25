<?php
/**
 * Site header.
 *
 * @package HelloProVision
 */

defined( 'ABSPATH' ) || exit;

$hpv_blank = hpv_is_blank_canvas();
?><!doctype html>
<html <?php language_attributes(); ?> class="<?php echo esc_attr( hpv_html_class() ); ?>">
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#EFE9DC">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?><?php hpv_body_attrs(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main"><?php esc_html_e( 'Skip to content', 'hpv' ); ?></a>

<?php if ( ! $hpv_blank ) : ?>
<header class="site-header" id="site-header">
	<div class="wrap site-header__inner">
		<?php get_template_part( 'template-parts/wordmark' ); ?>
		<nav class="nav" aria-label="<?php esc_attr_e( 'Main', 'hpv' ); ?>">
			<ul class="nav__list">
				<?php hpv_menu_items( 'primary', 'nav' ); ?>
			</ul>
			<span class="local-time" aria-label="<?php esc_attr_e( 'Local time in Fort Myers', 'hpv' ); ?>"><i aria-hidden="true"></i>Fort Myers <b id="local-time">--:--</b></span>
			<a class="btn btn--sm" href="<?php echo esc_url( hpv_page_url( 'strategy-call' ) ); ?>" data-cta="header"><?php esc_html_e( 'Book a call', 'hpv' ); ?> <span aria-hidden="true">→</span></a>
			<a class="btn btn--sm header-call" href="<?php echo esc_url( hpv_page_url( 'strategy-call' ) ); ?>" data-cta="header-mobile"><?php esc_html_e( 'Call me', 'hpv' ); ?></a>
			<button class="nav-toggle" type="button" aria-expanded="false" aria-controls="mobile-menu" id="nav-toggle"><?php esc_html_e( 'Menu', 'hpv' ); ?></button>
		</nav>
	</div>
</header>

<div class="mobile-menu" id="mobile-menu" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Menu', 'hpv' ); ?>" hidden>
	<div class="mobile-menu__top">
		<?php get_template_part( 'template-parts/wordmark' ); ?>
		<button class="nav-toggle" type="button" id="nav-close"><?php esc_html_e( 'Close', 'hpv' ); ?></button>
	</div>
	<ul class="mobile-menu__list">
		<?php hpv_menu_items( 'primary', 'mobile' ); ?>
		<li><a href="<?php echo esc_url( hpv_page_url( 'growth-scorecard' ) ); ?>"><?php esc_html_e( 'Growth Scorecard', 'hpv' ); ?> <span aria-hidden="true">→</span></a></li>
	</ul>
	<div class="mobile-menu__foot">
		<a class="btn btn--block" href="<?php echo esc_url( hpv_page_url( 'strategy-call' ) ); ?>" data-cta="mobile-menu"><?php esc_html_e( 'Book a strategy call', 'hpv' ); ?> <span aria-hidden="true">→</span></a>
		<p class="small"><?php esc_html_e( 'Based in Southwest Florida', 'hpv' ); ?><?php if ( hpv_opt( 'phone' ) ) : ?> · <a href="<?php echo esc_attr( hpv_tel( hpv_opt( 'phone' ) ) ); ?>" data-track="click_to_call"><?php echo esc_html( hpv_opt( 'phone' ) ); ?></a><?php endif; ?></p>
	</div>
</div>
<?php endif; ?>

<main id="main">
