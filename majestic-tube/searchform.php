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
		<span aria-hidden="true">⌕</span>
	</button>
</form>
