<?php
/**
 * Plugin Name: Mestari Robots
 * Plugin URI:  https://github.com/Tapiokansleri/mestari-robots
 * Description: Minimal robots.txt editor. The field lives under Settings > Reading and overrides any other robots.txt output (Yoast, etc.).
 * Version:     1.1.1
 * Author:      Mestari
 * Update URI:  https://github.com/Tapiokansleri/mestari-robots
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MESTARI_ROBOTS_FILE', __FILE__ );
define( 'MESTARI_ROBOTS_VERSION', '1.1.1' );
define( 'MESTARI_ROBOTS_REPO', 'Tapiokansleri/mestari-robots' );

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
		add_filter( 'plugin_action_links_' . plugin_basename( MESTARI_ROBOTS_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function action_links( $links ) {
		$settings = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-reading.php#' . self::OPTION ) ),
			esc_html__( 'Settings', 'mestari-robots' )
		);
		$check = sprintf(
			'<a href="%s">%s</a>',
			esc_url(
				wp_nonce_url(
					admin_url( 'admin-post.php?action=mestari_robots_flush' ),
					'mestari_robots_flush'
				)
			),
			esc_html__( 'Check for updates', 'mestari-robots' )
		);
		array_unshift( $links, $settings, $check );
		return $links;
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

class Mestari_Robots_Updater {

	const CACHE_KEY = 'mestari_robots_gh_release';
	const CACHE_TTL = 6 * HOUR_IN_SECONDS;

	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_information' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_source_dir' ), 10, 4 );
		add_action( 'load-update-core.php', array( __CLASS__, 'flush_on_force_check' ) );
		add_action( 'load-plugins.php', array( __CLASS__, 'flush_on_force_check' ) );
		add_action( 'admin_post_mestari_robots_flush', array( __CLASS__, 'handle_manual_flush' ) );
	}

	public static function flush_on_force_check() {
		if ( ! empty( $_GET['force-check'] ) ) {
			delete_site_transient( self::CACHE_KEY );
		}
	}

	public static function handle_manual_flush() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'mestari-robots' ) );
		}
		check_admin_referer( 'mestari_robots_flush' );
		delete_site_transient( self::CACHE_KEY );
		delete_site_transient( 'update_plugins' );
		wp_safe_redirect( admin_url( 'update-core.php?force-check=1' ) );
		exit;
	}

	private static function plugin_basename() {
		return plugin_basename( MESTARI_ROBOTS_FILE );
	}

	private static function plugin_slug() {
		return dirname( self::plugin_basename() );
	}

	private static function gh_get( $path ) {
		return wp_remote_get(
			'https://api.github.com/repos/' . MESTARI_ROBOTS_REPO . $path,
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'mestari-robots-updater',
				),
			)
		);
	}

	private static function fetch_release( $force = false ) {
		if ( ! $force ) {
			$cached = get_site_transient( self::CACHE_KEY );
			if ( is_array( $cached ) ) {
				return empty( $cached['error'] ) ? $cached : false;
			}
		}

		// 1. Try published releases.
		$response = self::gh_get( '/releases/latest' );
		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$data = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( is_array( $data ) && ! empty( $data['tag_name'] ) ) {
				$zip = '';
				if ( ! empty( $data['assets'] ) && is_array( $data['assets'] ) ) {
					foreach ( $data['assets'] as $asset ) {
						if ( isset( $asset['browser_download_url'] ) && substr( $asset['browser_download_url'], -4 ) === '.zip' ) {
							$zip = $asset['browser_download_url'];
							break;
						}
					}
				}
				if ( ! $zip ) {
					$zip = ! empty( $data['zipball_url'] ) ? $data['zipball_url'] : '';
				}
				$release = array(
					'version'   => ltrim( $data['tag_name'], 'vV' ),
					'zip'       => $zip,
					'changelog' => isset( $data['body'] ) ? (string) $data['body'] : '',
					'published' => isset( $data['published_at'] ) ? $data['published_at'] : '',
					'html_url'  => isset( $data['html_url'] ) ? $data['html_url'] : ( 'https://github.com/' . MESTARI_ROBOTS_REPO ),
				);
				set_site_transient( self::CACHE_KEY, $release, self::CACHE_TTL );
				return $release;
			}
		}

		// 2. Fall back to tags (works for plain `git tag && git push --tags`).
		$response = self::gh_get( '/tags' );
		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$tags = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( is_array( $tags ) && ! empty( $tags[0]['name'] ) ) {
				$tag     = $tags[0]['name'];
				$release = array(
					'version'   => ltrim( $tag, 'vV' ),
					'zip'       => 'https://github.com/' . MESTARI_ROBOTS_REPO . '/archive/refs/tags/' . rawurlencode( $tag ) . '.zip',
					'changelog' => '',
					'published' => '',
					'html_url'  => 'https://github.com/' . MESTARI_ROBOTS_REPO . '/releases/tag/' . rawurlencode( $tag ),
				);
				set_site_transient( self::CACHE_KEY, $release, self::CACHE_TTL );
				return $release;
			}
		}

		set_site_transient( self::CACHE_KEY, array( 'error' => true ), HOUR_IN_SECONDS );
		return false;
	}

	public static function inject_update( $transient ) {
		if ( empty( $transient ) || ! is_object( $transient ) ) {
			return $transient;
		}

		$release = self::fetch_release();
		if ( ! $release || empty( $release['zip'] ) ) {
			return $transient;
		}

		$basename = self::plugin_basename();

		if ( version_compare( $release['version'], MESTARI_ROBOTS_VERSION, '>' ) ) {
			$transient->response[ $basename ] = (object) array(
				'id'          => 'mestari-robots/' . $basename,
				'slug'        => self::plugin_slug(),
				'plugin'      => $basename,
				'new_version' => $release['version'],
				'url'         => $release['html_url'],
				'package'     => $release['zip'],
				'icons'       => array(),
				'banners'     => array(),
				'tested'      => '',
				'requires'    => '',
				'requires_php' => '',
			);
		} else {
			$transient->no_update[ $basename ] = (object) array(
				'id'          => 'mestari-robots/' . $basename,
				'slug'        => self::plugin_slug(),
				'plugin'      => $basename,
				'new_version' => MESTARI_ROBOTS_VERSION,
				'url'         => $release['html_url'],
				'package'     => '',
				'icons'       => array(),
				'banners'     => array(),
			);
		}

		return $transient;
	}

	public static function plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}
		if ( empty( $args->slug ) || $args->slug !== self::plugin_slug() ) {
			return $result;
		}

		$release = self::fetch_release();
		if ( ! $release ) {
			return $result;
		}

		return (object) array(
			'name'          => 'Mestari Robots',
			'slug'          => self::plugin_slug(),
			'version'       => $release['version'],
			'author'        => '<a href="https://github.com/Tapiokansleri">Tapio Kansleri</a>',
			'homepage'      => $release['html_url'],
			'download_link' => $release['zip'],
			'last_updated'  => $release['published'],
			'sections'      => array(
				'description' => 'Minimal robots.txt editor for WordPress. Overrides output from Yoast and other plugins, with a textarea under Settings > Reading.',
				'changelog'   => wp_kses_post( wpautop( $release['changelog'] ) ),
			),
		);
	}

	public static function fix_source_dir( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== self::plugin_basename() ) {
			return $source;
		}

		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			return $source;
		}

		$desired = trailingslashit( $remote_source ) . self::plugin_slug();
		$source  = untrailingslashit( $source );

		if ( $source === $desired ) {
			return trailingslashit( $source );
		}

		if ( $wp_filesystem->move( $source, $desired, true ) ) {
			return trailingslashit( $desired );
		}

		return new WP_Error( 'mestari_robots_rename_failed', 'Could not rename GitHub source directory.' );
	}
}

Mestari_Robots_Updater::init();
