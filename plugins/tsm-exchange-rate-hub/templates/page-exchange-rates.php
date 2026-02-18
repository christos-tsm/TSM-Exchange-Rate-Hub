<?php

/**
 * Custom page template: Exchange Rates.
 *
 * Selectable in the Page Attributes → Template dropdown.
 * Wraps the theme's header/footer around the shortcode output
 * so the page inherits the active theme's look and feel.
 *
 * @since      1.0.0
 * @package    Tsm_Exchange_Rate_Hub
 */

get_header();
?>

<main id="main" class="site-main tsm-erh-template">
	<div class="tsm-erh-template-container">
		<?php
		while ( have_posts() ) :
			the_post();
		?>
			<header class="entry-header">
				<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
			</header>

			<div class="entry-content">
				<?php
				the_content();
				echo do_shortcode( '[tsm_exchange_rates]' );
				?>
			</div>
		<?php endwhile; ?>
	</div>
</main>

<?php
get_footer();
