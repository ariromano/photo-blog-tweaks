<?php
/**
 * Plugin Name: Photo Blog Tweaks
 * Description: configure custom and full-size image links
 * Version: 1.0.0
 * Requires at least: 7.1
 * Requires PHP: 7.4
 * License: MIT
 * Author: Ari Romano McBride
 */

defined( 'ABSPATH' ) || exit;

const PBT_OPTION_NAME = 'pbt_max_image_dimension';

/** Set WordPress's big-image scaling threshold. */
function pbt_big_image_size_threshold( $threshold, $imagesize, $file, $attachment_id ) {
	$configured = get_option( PBT_OPTION_NAME, '3480' );

	if ( 'off' === $configured ) {
		return false;
	}

	$configured = absint( $configured );

	return $configured >= 1 ? $configured : 3480;
}
add_filter( 'big_image_size_threshold', 'pbt_big_image_size_threshold', 10, 4 );

/** Register the setting under settings - media. */
function pbt_register_media_setting() {
	register_setting(
		'media',
		PBT_OPTION_NAME,
		array(
			'type'              => 'string',
			'sanitize_callback' => 'pbt_sanitize_threshold',
			'default'           => '3480',
		)
	);

	add_settings_section(
		'pbt_photo_blog_section',
		'Photo Blog Tweaks',
		function () {
			echo '<p>Configure large-image scaling and links to original uploads.</p>';
		},
		'media'
	);

	add_settings_field(
		PBT_OPTION_NAME,
		'Maximum image dimension',
		'pbt_render_threshold_field',
		'media',
		'pbt_photo_blog_section'
	);
}
add_action( 'admin_init', 'pbt_register_media_setting' );

function pbt_sanitize_threshold( $value ) {
	if ( is_string( $value ) && 'off' === strtolower( trim( $value ) ) ) {
		return 'off';
	}

	$value = absint( $value );

	if ( $value < 1 ) {
		return '3480';
	}

	return (string) min( $value, 30000 );
}

function pbt_render_threshold_field() {
	$value = get_option( PBT_OPTION_NAME, '3480' );
	?>
	<input
		type="number"
		min="1"
		max="30000"
		step="1"
		name="<?php echo esc_attr( PBT_OPTION_NAME ); ?>"
		value="<?php echo esc_attr( 'off' === $value ? '' : $value ); ?>"
		class="small-text"
		<?php disabled( 'off' === $value ); ?>
	/>
	<p class="description">
		Maximum width or height in pixels before WordPress creates a scaled copy.
		Default: 3480 pixels.
	</p>
	<label>
		<input
			type="checkbox"
			name="<?php echo esc_attr( PBT_OPTION_NAME ); ?>"
			value="off"
			<?php checked( 'off' === $value ); ?>
		/>
		Disable big-image scaling entirely
	</label>
	<p class="description">
		Applies to new uploads. It does not restore originals for images already scaled.
	</p>
	<?php
}

/** For images linked to their media file, link to the original file */
function pbt_link_image_block_to_original( $block_content, $block ) {
	if ( empty( $block['blockName'] ) || 'core/image' !== $block['blockName'] ) {
		return $block_content;
	}

	$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();

	if ( empty( $attrs['id'] ) || ! isset( $attrs['linkDestination'] ) || 'media' !== $attrs['linkDestination'] ) {
		return $block_content;
	}

	$attachment_id = absint( $attrs['id'] );
	if ( ! $attachment_id ) {
		return $block_content;
	}

	$original_url = wp_get_original_image_url( $attachment_id );
	if ( ! $original_url ) {
		return $block_content;
	}

	$processor = new WP_HTML_Tag_Processor( $block_content );
	if ( $processor->next_tag( 'A' ) ) {
		$processor->set_attribute( 'href', esc_url( $original_url ) );
		return $processor->get_updated_html();
	}

	return $block_content;
}
add_filter( 'render_block', 'pbt_link_image_block_to_original', 10, 2 );
