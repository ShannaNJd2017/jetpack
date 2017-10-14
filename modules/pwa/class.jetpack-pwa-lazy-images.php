<?php

/**
 * Props to batmoo for https://wordpress.org/plugins/lazy-load/
 * https://profiles.wordpress.org/batmoo
 */
class Jetpack_PWA_Lazy_Images {
	private static $__instance = null;
	/**
	 * Singleton implementation
	 *
	 * @return object
	 */
	public static function instance() {
		if ( ! is_a( self::$__instance, 'Jetpack_PWA_Lazy_Images' ) ) {
			self::$__instance = new Jetpack_PWA_Lazy_Images();
		}

		return self::$__instance;
	}

	/**
	 * Registers actions
	 */
	private function __construct() {

		// modify content
		add_action( 'wp_head', array( $this, 'setup_filters' ), 9999 ); // we don't really want to modify anything in <head> since it's mostly all metadata

		// js to do lazy loading
		add_action( 'init', array( $this, 'register_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function setup_filters() {
		add_filter( 'the_content', array( $this, 'add_image_placeholders' ), 99 ); // run this later, so other content filters have run, including image_add_wh on WP.com
		add_filter( 'post_thumbnail_html', array( $this, 'add_image_placeholders' ), 11 );
		add_filter( 'get_avatar', array( $this, 'add_image_placeholders' ), 11 );
	}

	public function add_image_placeholders( $content ) {
		// might be more performant than check below... but also more likely to go wrong? e.g. if post-processed data got saved in DB
		// if ( $this->has_run_on_this_request ) {
		// 	return $content;
		// }

		// Don't lazyload for feeds, previews, mobile
		if( is_feed() || is_preview() )
			return $content;

		// Don't lazy-load if the content has already been run through previously
		if ( false !== strpos( $content, 'data-lazy-src' ) )
			return $content;

		// This is a pretty simple regex, but it works
		$content = preg_replace_callback( '#<(img)([^>]+?)(>(.*?)</\\1>|[\/]?>)#si', array( __CLASS__, 'process_image' ), $content );

		return $content;
	}

 	function process_image( $matches ) {
		// In case you want to change the placeholder image
		// $placeholder_image = apply_filters( 'jetpack_pwa_lazy_load_image', plugins_url( 'assets/images/1x1.trans.gif', __FILE__ ) );

		$old_attributes_str = $matches[2];
		$old_attributes = wp_kses_hair( $old_attributes_str, wp_allowed_protocols() );

		if ( empty( $old_attributes['src'] ) ) {
			return $matches[0];
		}

		$image_src = $old_attributes['src']['value'];

		// Remove src and lazy-src since we manually add them
		$new_attributes = $old_attributes;
		unset( $new_attributes['src'], $new_attributes['data-lazy-src'] );

		$new_attributes_str = $this->build_attributes_string( $new_attributes );

		return sprintf( '<img data-lazy-src="%1$s" %2$s><noscript>%3$s</noscript>', esc_url( $image_src ), $new_attributes_str, $matches[0] );
	}

	private function build_attributes_string( $attributes ) {
		$string = array();
		foreach ( $attributes as $name => $attribute ) {
			$value = $attribute['value'];
			if ( '' === $value ) {
				$string[] = sprintf( '%s', $name );
			} else {
				$string[] = sprintf( '%s="%s"', $name, esc_attr( $value ) );
			}
		}
		return implode( ' ', $string );
	}

	public function register_assets() {
		Jetpack_PWA_Optimize_Assets::instance()->register_inline_script( 'jetpack-pwa-lazy-images', 'assets/js/lazy-images.js', __FILE__, array('jquery'), '1.5' );
		// Jetpack_PWA_Optimize_Assets::instance()->register_inline_style( 'jetpack-pwa-lazy-images', 'assets/css/show-network-status.css', __FILE__ );
	}

	public function enqueue_assets() {
		wp_enqueue_script( 'jetpack-pwa-lazy-images' );
		// wp_enqueue_style( 'jetpack-pwa-lazy-images' );
	}
}