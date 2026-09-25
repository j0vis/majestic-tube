<?php
/**
 * Logo watermark overlaid on the single video player.
 *
 * Desktop only, exactly like the original theme's
 * template-parts/content-logo-watermark.php: mobile visitors never get the
 * overlay, the watermark image option wins over the main logo, and the
 * position comes from logo-position-video-player.
 *
 * @package Majestic Tube
 * @version 2.0.0
 */

if ( majestic_tube_is_mobile() || ! majestic_tube_option_is_on( 'logo-watermark-video-player' ) ) {
	return;
}

$watermark = (string) majestic_tube_get_option( 'wpst-options', 'image-logo-watermark-file', '' );

if ( '' === $watermark ) {
	$watermark = (string) majestic_tube_get_option( 'wpst-options', 'image-logo-file', '' );
}

if ( '' === $watermark ) {
	return;
}

$position = (string) majestic_tube_get_option( 'wpst-options', 'logo-position-video-player', 'top-left' );
$allowed  = array( 'top-left', 'top-right', 'bottom-left', 'bottom-right' );

if ( ! in_array( $position, $allowed, true ) ) {
	$position = 'top-left';
}

$classes = 'logo-watermark-img ' . $position;

if ( majestic_tube_option_is_on( 'logo-watermark-grayscale' ) ) {
	$classes .= ' grayscale';
}
?>
<div id="logo-watermark">
	<img class="<?php echo esc_attr( $classes ); ?>" src="<?php echo esc_url( $watermark ); ?>" alt="" aria-hidden="true" />
</div>
