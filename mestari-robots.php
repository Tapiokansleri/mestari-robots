<?php
/**
 * Plugin Name: Mestari Robots
 * Description: Minimal robots.txt editor. The field lives under Settings > Reading and overrides any other robots.txt output (Yoast, etc.).
 * Version:     1.0.0
 * Author:      Mestari
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mestari_Robots {

	const OPTION = 'mestari_robots_txt';

	public static function default_content() {
		return "User-agent: *\n"
			. "Disallow: /wp-admin/\n"
			. "Disallow: /wp-login.php\n"
			. "Disallow: /wp-json/\n"
			. "Disallow: /feed/\n"
			. "Disallow: /paged/\n"
			. "Disallow: /page/\n"
			. "Disallow: /?s=\n"
			. "Disallow: /*?\n"
			. "Allow: /wp-admin/admin-ajax.php\n"
			. "\n"
			. "Sitemap: " . self::detect_sitemap_url() . "\n";
	}

	public static function detect_sitemap_url() {
		// Yoast SEO
		if ( defined( 'WPSEO_VERSION' ) ) {
			return home_url( '/sitemap_index.xml' );
		}
		// Rank Math
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			return home_url( '/sitemap_index.xml' );
		}
		// SEOPress
		if ( defined( 'SEOPRESS_VERSION' ) ) {
			return home_url( '/sitemaps.xml' );
		}
		// All in One SEO
		if ( defined( 'AIOSEO_VERSION' ) ) {
			return home_url( '/sitemap.xml' );
		}
		// Google XML Sitemaps (Arne Brachhold)
		if ( defined( 'XMLSF_VERSION' ) || class_exists( 'GoogleSitemapGenerator' ) ) {
			return home_url( '/sitemap.xml' );
		}
		// WordPress core (5.5+)
		return home_url( '/wp-sitemap.xml' );
	}

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_filter( 'robots_txt', array( __CLASS__, 'filter_output' ), PHP_INT_MAX, 2 );
	}

	public static function register() {
		register_setting(
			'reading',
			self::OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => '',
				'show_in_rest'      => false,
			)
		);

		add_settings_field(
			self::OPTION,
			__( 'robots.txt', 'mestari-robots' ),
			array( __CLASS__, 'render_field' ),
			'reading'
		);
	}

	public static function sanitize( $input ) {
		$input = (string) $input;
		$input = str_replace( array( "\r\n", "\r" ), "\n", $input );
		return $input;
	}

	public static function render_field() {
		$value = get_option( self::OPTION, '' );
		if ( $value === '' ) {
			$value = self::default_content();
		}
		printf(
			'<textarea name="%1$s" id="%1$s" rows="16" cols="60" class="large-text code" style="font-family:monospace;">%2$s</textarea>',
			esc_attr( self::OPTION ),
			esc_textarea( $value )
		);
		echo '<p class="description">'
			. esc_html__( 'This content is served as /robots.txt and overrides any output from Yoast or other plugins. Leave empty to restore the default.', 'mestari-robots' )
			. '</p>';
	}

	public static function filter_output( $output, $public ) {
		if ( ! $public ) {
			return "User-agent: *\nDisallow: /\n";
		}
		$value = get_option( self::OPTION, '' );
		if ( $value === '' ) {
			$value = self::default_content();
		}
		return $value;
	}
}

Mestari_Robots::init();
