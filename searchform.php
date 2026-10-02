<?php
/**
 * The search form template.
 *
 * @package Majestic Tube
 * @version 1.0.0
 */
?>
<form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label>
		<span class="screen-reader-text"><?php esc_html_e( 'Search for:', 'majestic-tube' ); ?></span>
		<input type="search" class="search-field" placeholder="<?php esc_attr_e( 'Search videos&hellip;', 'majestic-tube' ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>" name="s" />
	</label>
	<button type="submit" class="search-submit">
		<span class="screen-reader-text"><?php esc_html_e( 'Search', 'majestic-tube' ); ?></span>
		<?php
		/*
		 * The magnifier used to be the Unicode character U+2315, which most
		 * systems render at a different weight and baseline than everything
		 * around it, and some not at all. A masked shape sits on the same
		 * optical grid as the rest of the set.
		 */
		majestic_tube_icon( 'search' );
		?>
	</button>
</form>
