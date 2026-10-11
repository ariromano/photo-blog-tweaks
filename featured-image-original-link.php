<?php
/**
 * Plugin Name: Featured Image Original Link
 * Description: Adds a dynamic "Featured image (original)" option to the block editor's link picker. It resolves to the original file URL of the current post's featured image. Ideal for making photo blog pages where the featured image is the main image and a button allows the user to view the full size image.
 * Version: 1.0.0
 * Requires at least: 7.1
 * Requires PHP: 7.4
 * License: MIT
 * Author: Ari Romano McBride
 */


defined( 'ABSPATH' ) || exit;

/**
 * Placeholder URL stored in the content when the magic link is chosen.
 */
const FIOL_PLACEHOLDER = '#featured-image-original';

/**
 * Add the magic link to the link picker.
 */
add_filter( 'rest_post_dispatch', function ( $response, $server, $request ) {
	if ( '/wp/v2/search' !== $request->get_route() ) {
		return $response;
	}

	if ( 'post' !== $request->get_param( 'type' ) || (int) $request->get_param( 'page' ) > 1 ) {
		return $response;
	}

	if ( ! current_user_can( 'edit_posts' ) || ! $response instanceof WP_HTTP_Response || $response->is_error() ) {
		return $response;
	}

	$label  = __( 'Featured image (original)', 'featured-image-original-link' );
	$search = trim( (string) $request->get_param( 'search' ) );

	if ( '' !== $search && false === stripos( $label . ' featured image original dynamic', $search ) ) {
		return $response;
	}

	$data = $response->get_data();
	if ( ! is_array( $data ) ) {
		return $response;
	}

	array_unshift( $data, array(
		// Don't do this at home: Hacking the result into the link picker by making the ID negative so it doesn't collide with something else
		'id'      => -1,
		'title'   => $label,
		'url'     => FIOL_PLACEHOLDER,
		'type'    => 'post',
		'subtype' => 'page',
	) );

	$response->set_data( $data );

	return $response;
}, 10, 3 );

/**
 * Resolve the placeholder on the front end.
 */
add_filter( 'render_block', function ( $block_content, $block, $instance = null ) {
	if ( '' === $block_content || false === strpos( $block_content, FIOL_PLACEHOLDER ) ) {
		return $block_content;
	}

	$post_id = 0;
	if ( $instance instanceof WP_Block && ! empty( $instance->context['postId'] ) ) {
		$post_id = (int) $instance->context['postId'];
	}
	if ( ! $post_id ) {
		$post_id = (int) get_the_ID();
	}

	$url = '';
	if ( $post_id ) {
		$thumb_id = get_post_thumbnail_id( $post_id );
		if ( $thumb_id ) {
			$url = (string) wp_get_attachment_url( $thumb_id ); // Original uploaded file.
		}
	}

	return str_replace(
		'href="' . FIOL_PLACEHOLDER . '"',
		'href="' . ( $url ? esc_url( $url ) : '#' ) . '"',
		$block_content
	);
}, 10, 3 );
