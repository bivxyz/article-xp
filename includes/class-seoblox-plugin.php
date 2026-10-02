<?php
/**
 * Main plugin controller.
 *
 * @package SEOblox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SEOblox_Plugin {
	const OPTION_KEY       = 'seoblox_settings';
	const MIGRATION_KEY    = 'seoblox_migration_version';
	const META_TLDR        = '_seoblox_tldr';
	const META_TLDR_FORMAT = '_seoblox_tldr_format';
	const META_DETAILS     = '_seoblox_show_details';
	const META_SHOW_TLDR   = '_seoblox_show_tldr';
	const META_PLACEMENT   = '_seoblox_placement';
	const META_WORD_COUNT  = '_seoblox_word_count';
	const META_MINUTES     = '_seoblox_reading_minutes';

	/**
	 * Singleton instance.
	 *
	 * @var SEOblox_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Whether the singular page has already received automatic or explicit output.
	 *
	 * @var bool
	 */
	private $page_output_rendered = false;

	/** Copying existing storage must not run custom sanitization a second time. */
	private $migrating_meta = false;

	/**
	 * Return the singleton.
	 *
	 * @return SEOblox_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Add default settings without overwriting existing configuration.
	 *
	 * @return void
	 */
	public static function activate() {
		self::maybe_upgrade();
	}

	/**
	 * Copy legacy settings once, before defaults can shadow them.
	 * Runs on activation and normal plugin loads (updates need not reactivate).
	 */
	public static function maybe_upgrade() {
		if ( '2.0.0' === get_option( self::MIGRATION_KEY ) ) {
			return;
		}
		$missing = new stdClass();
		if ( $missing === get_option( self::OPTION_KEY, $missing ) ) {
			$legacy = get_option( 'aig_settings', $missing );
			$value  = is_array( $legacy ) ? $legacy : self::defaults();
			if ( ! add_option( self::OPTION_KEY, $value ) ) {
				return; // Retry next load if persistence failed. Never delete legacy data.
			}
		}
		update_option( self::MIGRATION_KEY, '2.0.0', false );
	}

	/** Content-layer only: never read, write, or render commerce records. */
	public static function is_content_post_type( $post_type ) {
		return ! in_array( $post_type, array( 'attachment', 'product', 'product_variation', 'shop_order', 'shop_order_refund', 'shop_order_placehold' ), true );
	}

	/** New key => retained legacy key. */
	public static function legacy_meta_keys() {
		return array(
			self::META_TLDR        => '_aig_tldr',
			self::META_TLDR_FORMAT => '_aig_tldr_format',
			self::META_DETAILS     => '_aig_show_details',
			self::META_SHOW_TLDR   => '_aig_show_tldr',
			self::META_PLACEMENT   => '_aig_placement',
			self::META_WORD_COUNT  => '_aig_word_count',
			self::META_MINUTES     => '_aig_reading_minutes',
		);
	}

	/** An explicitly empty new value wins over legacy content. */
	public function post_meta( $post_id, $key ) {
		if ( ! $this->eligible_post( $post_id ) ) {
			return '';
		}
		$legacy = self::legacy_meta_keys();
		if ( ! metadata_exists( 'post', $post_id, $key ) && isset( $legacy[ $key ] ) && metadata_exists( 'post', $post_id, $legacy[ $key ] ) ) {
			return get_post_meta( $post_id, $legacy[ $key ], true );
		}
		return get_post_meta( $post_id, $key, true );
	}

	private function eligible_post( $post_id ) {
		return in_array( get_post_type( $post_id ), $this->enabled_post_types(), true );
	}

	/** Make the editor see effective legacy values without writing on reads. */
	public function prepare_rest_meta( $response, $post ) {
		if ( ! $this->eligible_post( $post->ID ) ) {
			return $response;
		}
		$data = $response->get_data();
		if ( isset( $data['meta'] ) && is_array( $data['meta'] ) ) {
			foreach ( self::legacy_meta_keys() as $key => $legacy ) {
				if ( array_key_exists( $key, $data['meta'] ) && ! metadata_exists( 'post', $post->ID, $key ) && metadata_exists( 'post', $post->ID, $legacy ) ) {
					$data['meta'][ $key ] = $this->post_meta( $post->ID, $key );
				}
			}
			$response->set_data( $data );
		}
		return $response;
	}

	/** Preserve old hook arguments, then let the new hook make the final decision. */
	private function filter( $suffix, $value, ...$args ) {
		$value = apply_filters_deprecated( 'aig_' . $suffix, array_merge( array( $value ), $args ), '2.0.0', 'seoblox_' . $suffix );
		return apply_filters( 'seoblox_' . $suffix, $value, ...$args );
	}

	/**
	 * Default plugin settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'post_types'      => array( 'post' ),
			'show_details'    => 1,
			'show_tldr'       => 1,
			'auto_details'    => 1,
			'auto_tldr'       => 1,
			'words_per_minute'=> 225,
			'published_label' => __( 'Published on', 'seoblox' ),
			'modified_label'  => __( 'Last updated on', 'seoblox' ),
			'read_label'      => __( '%s min read', 'seoblox' ),
			'background'      => '#EEF3FF',
			'accent'          => '#315EFB',
			'text_color'      => '#14213D',
			'border_radius'   => 12,
			'spacing'         => 'comfortable',
			'schema_mode'     => 'auto',
		);
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 0 );
		add_action( 'init', array( $this, 'register_meta_and_blocks' ) );
		add_action( 'init', array( $this, 'register_assets' ), 5 );
		add_action( 'save_post', array( $this, 'cache_reading_time' ), 20, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'localize_editor' ) );
		add_action( 'wp_footer', array( $this, 'render_builder_fallback' ), 5 );

		add_filter( 'the_content', array( $this, 'prepend_insights' ), 8 );
		add_filter( 'wpseo_schema_article', array( $this, 'filter_article_schema' ) );
		add_filter( 'rank_math/snippet/rich_snippet_article_entity', array( $this, 'filter_article_schema' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SEOBLOX_PLUGIN_FILE ), array( $this, 'settings_link' ) );

		add_shortcode( 'seoblox', array( $this, 'render_combined_shortcode' ) );
		add_shortcode( 'article_xp', array( $this, 'render_combined_shortcode' ) );
		add_shortcode( 'seoblox_details', array( $this, 'render_details_shortcode' ) );
		add_shortcode( 'article_xp_details', array( $this, 'render_details_shortcode' ) );
		add_shortcode( 'seoblox_tldr', array( $this, 'render_tldr_shortcode' ) );
		add_shortcode( 'article_xp_tldr', array( $this, 'render_tldr_shortcode' ) );

		if ( is_admin() ) {
			require_once SEOBLOX_PLUGIN_DIR . 'includes/class-seoblox-settings.php';
			new SEOblox_Settings( $this );
		}
	}

	/**
	 * Get merged and normalized settings.
	 *
	 * @return array<string,mixed>
	 */
	public function settings() {
		$settings = get_option( self::OPTION_KEY, array() );
		return wp_parse_args( is_array( $settings ) ? $settings : array(), self::defaults() );
	}

	/**
	 * Public post types selected in plugin settings.
	 *
	 * @return string[]
	 */
	public function enabled_post_types() {
		$settings = $this->settings();
		$types    = isset( $settings['post_types'] ) && is_array( $settings['post_types'] )
			? array_map( 'sanitize_key', $settings['post_types'] )
			: array( 'post' );

		return array_values( array_filter( array_unique( $types ), static function ( $type ) {
			return post_type_exists( $type ) && self::is_content_post_type( $type );
		} ) );
	}

	/**
	 * Register scripts and styles.
	 *
	 * @return void
	 */
	public function register_assets() {
		wp_register_style(
			'seoblox-frontend',
			SEOBLOX_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			SEOBLOX_VERSION
		);
		wp_register_style(
			'seoblox-editor',
			SEOBLOX_PLUGIN_URL . 'assets/css/editor.css',
			array( 'wp-edit-blocks' ),
			SEOBLOX_VERSION
		);
		wp_register_script(
			'seoblox-editor',
			SEOBLOX_PLUGIN_URL . 'assets/js/editor.js',
			array(
				'wp-block-editor',
				'wp-blocks',
				'wp-components',
				'wp-compose',
				'wp-data',
				'wp-edit-post',
				'wp-element',
				'wp-i18n',
				'wp-plugins',
				'wp-server-side-render',
			),
			SEOBLOX_VERSION,
			true
		);
		wp_register_script(
			'seoblox-frontend-script',
			SEOBLOX_PLUGIN_URL . 'assets/js/frontend.js',
			array(),
			SEOBLOX_VERSION,
			true
		);
	}

	/**
	 * Register post metadata and server-rendered blocks.
	 *
	 * @return void
	 */
	public function register_meta_and_blocks() {
		foreach ( $this->enabled_post_types() as $post_type ) {
			add_filter( 'rest_prepare_' . $post_type, array( $this, 'prepare_rest_meta' ), 10, 2 );
			if ( ! post_type_supports( $post_type, 'custom-fields' ) ) {
				add_post_type_support( $post_type, 'custom-fields' );
			}

			$common = array(
				'single'        => true,
				'type'          => 'string',
				'show_in_rest'  => true,
				'auth_callback' => array( $this, 'can_edit_meta' ),
			);

			register_post_meta(
				$post_type,
				self::META_TLDR,
				array_merge(
					$common,
					array(
						'description'       => __( 'Editor-approved article summary.', 'seoblox' ),
						'sanitize_callback' => array( $this, 'sanitize_tldr' ),
						'default'           => '',
					)
				)
			);

			$this->register_choice_meta( $post_type, self::META_TLDR_FORMAT, array( 'paragraph', 'list' ), 'paragraph' );
			$this->register_choice_meta( $post_type, self::META_DETAILS, array( 'default', 'show', 'hide' ), 'default' );
			$this->register_choice_meta( $post_type, self::META_SHOW_TLDR, array( 'default', 'show', 'hide' ), 'default' );
			$this->register_choice_meta( $post_type, self::META_PLACEMENT, array( 'auto', 'manual' ), 'auto' );
		}

		$blocks = array(
			'seoblox/article-details'   => 'render_details_block',
			'seoblox/tldr'              => 'render_tldr_block',
			// Deprecated aliases: leave existing serialized post content intact.
			'article-insights/details' => 'render_details_block',
			'article-insights/tldr'    => 'render_tldr_block',
		);
		foreach ( $blocks as $name => $callback ) {
			register_block_type( $name, array(
				'api_version'     => 2,
				'editor_script'   => 'seoblox-editor',
				'editor_style'    => 'seoblox-editor',
				'style'           => 'seoblox-frontend',
				'render_callback' => array( $this, $callback ),
				'supports'        => array(
					'html'     => false,
					'multiple' => false,
					'inserter' => 0 !== strpos( $name, 'article-insights/' ),
				),
			) );
		}
	}

	/**
	 * Register a REST-visible string meta field with a fixed set of values.
	 *
	 * @param string   $post_type Post type.
	 * @param string   $meta_key  Meta key.
	 * @param string[] $allowed   Allowed values.
	 * @param string   $default   Default value.
	 * @return void
	 */
	private function register_choice_meta( $post_type, $meta_key, $allowed, $default ) {
		register_post_meta(
			$post_type,
			$meta_key,
			array(
				'single'            => true,
				'type'              => 'string',
				'show_in_rest'      => array(
					'schema' => array(
						'type'    => 'string',
						'enum'    => $allowed,
						'default' => $default,
					),
				),
				'default'           => $default,
				'auth_callback'     => array( $this, 'can_edit_meta' ),
				'sanitize_callback' => static function ( $value ) use ( $allowed, $default ) {
					$value = sanitize_key( $value );
					return in_array( $value, $allowed, true ) ? $value : $default;
				},
			)
		);
	}

	/**
	 * Authorize post meta updates.
	 *
	 * @param bool   $allowed   Existing permission.
	 * @param string $meta_key  Meta key.
	 * @param int    $object_id Post ID.
	 * @return bool
	 */
	public function can_edit_meta( $allowed, $meta_key, $object_id ) {
		unset( $allowed, $meta_key );
		return $this->eligible_post( $object_id ) && current_user_can( 'edit_post', $object_id );
	}

	/**
	 * Sanitize TL;DR markup to the intentionally small rich-text subset.
	 *
	 * @param string $value Submitted HTML.
	 * @return string
	 */
	public function sanitize_tldr( $value ) {
		if ( $this->migrating_meta ) {
			// Preserve stored legacy markup verbatim; normal reads and edits still sanitize.
			return (string) $value;
		}
		$allowed = array(
			'p'      => array(),
			'br'     => array(),
			'ul'     => array(),
			'ol'     => array(),
			'li'     => array(),
			'strong' => array(),
			'b'      => array(),
			'em'     => array(),
			'i'      => array(),
			'a'      => array(
				'href'  => true,
				'title' => true,
				'rel'   => true,
			),
		);

		$value = wp_kses( (string) $value, $allowed );
		return $this->filter( 'sanitized_tldr', trim( $value ) );
	}

	/**
	 * Pass settings and editor labels to the block editor.
	 *
	 * @return void
	 */
	public function localize_editor() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->post_type, $this->enabled_post_types(), true ) ) {
			return;
		}

		wp_localize_script(
			'seoblox-editor',
			'seobloxEditor',
			array(
				'enabledPostTypes' => $this->enabled_post_types(),
				'meta'             => array(
					'tldr'       => self::META_TLDR,
					'format'     => self::META_TLDR_FORMAT,
					'details'    => self::META_DETAILS,
					'showTldr'   => self::META_SHOW_TLDR,
					'placement'  => self::META_PLACEMENT,
				),
			)
		);
	}

	/**
	 * Enqueue frontend styling on eligible singular views.
	 *
	 * @return void
	 */
	public function enqueue_frontend_assets() {
		if ( ! is_singular( $this->enabled_post_types() ) ) {
			return;
		}

		wp_enqueue_style( 'seoblox-frontend' );
		wp_enqueue_script( 'seoblox-frontend-script' );
		$settings = $this->settings();
		$padding  = 'compact' === $settings['spacing'] ? '14px 18px' : '18px 22px';
		$css      = sprintf(
			':root{--seoblox-background:%1$s;--seoblox-accent:%2$s;--seoblox-text:%3$s;--seoblox-radius:%4$dpx;--seoblox-padding:%5$s;}',
			esc_html( $settings['background'] ),
			esc_html( $settings['accent'] ),
			esc_html( $settings['text_color'] ),
			(int) $settings['border_radius'],
			esc_html( $padding )
		);
		wp_add_inline_style( 'seoblox-frontend', $css );
	}

	/**
	 * Cache reading metrics after content saves.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public function cache_reading_time( $post_id, $post ) {
		if (
			wp_is_post_revision( $post_id )
			|| wp_is_post_autosave( $post_id )
			|| ! $post instanceof WP_Post
			|| ! $this->eligible_post( $post_id )
		) {
			return;
		}

		// save_post precedes REST meta updates: seed old fields first, then let
		// submitted new values (including an empty TL;DR) overwrite them.
		foreach ( self::legacy_meta_keys() as $key => $legacy ) {
			if ( ! metadata_exists( 'post', $post_id, $key ) && metadata_exists( 'post', $post_id, $legacy ) ) {
				$this->migrating_meta = true;
				try {
					update_post_meta( $post_id, $key, wp_slash( get_post_meta( $post_id, $legacy, true ) ) );
				} finally {
					$this->migrating_meta = false;
				}
			}
		}

		$word_count = $this->count_words( $post->post_content );
		$minutes    = $this->minutes_from_word_count( $word_count, $post_id );

		update_post_meta( $post_id, self::META_WORD_COUNT, $word_count );
		update_post_meta( $post_id, self::META_MINUTES, $minutes );
	}

	/**
	 * Count readable Unicode words in saved block or classic content.
	 *
	 * @param string $content Saved post content.
	 * @return int
	 */
	public function count_words( $content ) {
		$content = strip_shortcodes( (string) $content );
		$content = preg_replace( '/<!--[\s\S]*?-->/', ' ', $content );
		$content = wp_strip_all_tags( (string) $content );
		$content = html_entity_decode( $content, ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) ?: 'UTF-8' );

		/*
		 * Count CJK characters individually, then remove them before matching
		 * space-delimited words. This avoids treating a complete CJK paragraph
		 * as one word while preserving Unicode words in other writing systems.
		 */
		$cjk_count = preg_match_all( '/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}]/u', $content, $cjk );
		$content   = preg_replace( '/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}]/u', ' ', $content );
		$matched   = preg_match_all( "/[\p{L}\p{N}]+(?:['\x{2019}-][\p{L}\p{N}]+)*/u", (string) $content, $words );

		return ( false === $matched ? 0 : (int) $matched ) + ( false === $cjk_count ? 0 : (int) $cjk_count );
	}

	/**
	 * Convert a word count to reading minutes.
	 *
	 * @param int $word_count Word count.
	 * @param int $post_id    Post ID.
	 * @return int
	 */
	private function minutes_from_word_count( $word_count, $post_id ) {
		$settings = $this->settings();
		$wpm      = max( 1, (int) $settings['words_per_minute'] );
		$wpm      = max( 1, (int) $this->filter( 'words_per_minute', $wpm, $post_id ) );
		$minutes  = max( 1, (int) ceil( $word_count / $wpm ) );

		return max( 1, (int) $this->filter( 'reading_minutes', $minutes, $word_count, $post_id ) );
	}

	/**
	 * Get reading time from cached words, recalculating for older content once.
	 *
	 * @param int $post_id Post ID.
	 * @return int
	 */
	public function get_reading_minutes( $post_id ) {
		if ( ! $this->eligible_post( $post_id ) ) {
			return 0;
		}

		$cached = $this->post_meta( $post_id, self::META_WORD_COUNT );
		if ( '' === $cached ) {
			$cached = $this->count_words( (string) get_post_field( 'post_content', $post_id ) );
		}

		return $this->minutes_from_word_count( (int) $cached, $post_id );
	}

	/**
	 * Automatically prepend enabled components to the main singular content.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function prepend_insights( $content ) {
		if (
			is_admin()
			|| is_feed()
			|| ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() )
			|| ! is_singular( $this->enabled_post_types() )
			|| ! did_action( 'wp_head' )
			|| $this->page_output_rendered
		) {
			return $content;
		}

		$post_id = get_the_ID();
		if (
			! $post_id
			|| (int) $post_id !== (int) get_queried_object_id()
			|| 'manual' === $this->post_meta( $post_id, self::META_PLACEMENT )
		) {
			return $content;
		}

		$output = $this->automatic_output( $post_id, $content );
		if ( '' !== $output ) {
			$this->page_output_rendered = true;
		}

		return $output . $content;
	}

	/**
	 * Build the components allowed by global settings, post overrides, and blocks.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $content Saved or filtered post content.
	 * @return string
	 */
	private function automatic_output( $post_id, $content ) {
		$settings = $this->settings();
		$output   = '';

		if (
			! empty( $settings['auto_details'] )
			&& $this->component_is_visible( $post_id, self::META_DETAILS, 'show_details' )
			&& ! has_block( 'seoblox/article-details', $content )
			&& ! has_block( 'article-insights/details', $content )
		) {
			$output .= $this->render_details( $post_id );
		}

		if (
			! empty( $settings['auto_tldr'] )
			&& $this->component_is_visible( $post_id, self::META_SHOW_TLDR, 'show_tldr' )
			&& ! has_block( 'seoblox/tldr', $content )
			&& ! has_block( 'article-insights/tldr', $content )
		) {
			$output .= $this->render_tldr( $post_id );
		}

		return $output;
	}

	/**
	 * Render a server-side fallback when a page builder bypasses the_content.
	 *
	 * The wrapper is printed in the initial HTML and moved beside the builder's
	 * article content by the small front-end script. Standard themes never use it.
	 *
	 * @return void
	 */
	public function render_builder_fallback() {
		if (
			is_admin()
			|| is_feed()
			|| $this->page_output_rendered
			|| ! is_singular( $this->enabled_post_types() )
		) {
			return;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id || 'manual' === $this->post_meta( $post_id, self::META_PLACEMENT ) ) {
			return;
		}

		$content = (string) get_post_field( 'post_content', $post_id );
		$output  = $this->automatic_output( $post_id, $content );
		if ( '' === $output ) {
			return;
		}

		$this->page_output_rendered = true;
		echo '<div class="seoblox-builder-fallback aig-builder-fallback" data-seoblox-builder-fallback>' . $output . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Component renderers escape dynamic values.
	}

	/**
	 * Render both components through a page-builder shortcode element.
	 *
	 * @return string
	 */
	public function render_combined_shortcode() {
		$post_id = $this->shortcode_post_id();
		if ( ! $post_id ) {
			return '';
		}

		$output = '';
		if ( $this->component_is_visible( $post_id, self::META_DETAILS, 'show_details' ) ) {
			$output .= $this->render_details( $post_id );
		}
		if ( $this->component_is_visible( $post_id, self::META_SHOW_TLDR, 'show_tldr' ) ) {
			$output .= $this->render_tldr( $post_id );
		}
		if ( '' !== $output ) {
			$this->page_output_rendered = true;
		}

		return $output;
	}

	/**
	 * Render only article details through a shortcode.
	 *
	 * @return string
	 */
	public function render_details_shortcode() {
		$post_id = $this->shortcode_post_id();
		if ( ! $post_id || ! $this->component_is_visible( $post_id, self::META_DETAILS, 'show_details' ) ) {
			return '';
		}
		$this->page_output_rendered = true;
		return $this->render_details( $post_id );
	}

	/**
	 * Render only the TL;DR through a shortcode.
	 *
	 * @return string
	 */
	public function render_tldr_shortcode() {
		$post_id = $this->shortcode_post_id();
		if ( ! $post_id || ! $this->component_is_visible( $post_id, self::META_SHOW_TLDR, 'show_tldr' ) ) {
			return '';
		}
		$output = $this->render_tldr( $post_id );
		if ( '' !== $output ) {
			$this->page_output_rendered = true;
		}
		return $output;
	}

	/**
	 * Resolve and validate the current post for shortcode output.
	 *
	 * @return int
	 */
	private function shortcode_post_id() {
		$post_id = get_the_ID() ?: get_queried_object_id();
		return $post_id && in_array( get_post_type( $post_id ), $this->enabled_post_types(), true )
			? (int) $post_id
			: 0;
	}

	/**
	 * Resolve global and per-post component visibility.
	 *
	 * @param int    $post_id         Post ID.
	 * @param string $meta_key        Override meta key.
	 * @param string $settings_option Global setting.
	 * @return bool
	 */
	private function component_is_visible( $post_id, $meta_key, $settings_option ) {
		$override = $this->post_meta( $post_id, $meta_key );
		if ( 'show' === $override ) {
			return true;
		}
		if ( 'hide' === $override ) {
			return false;
		}

		$settings = $this->settings();
		return ! empty( $settings[ $settings_option ] );
	}

	/**
	 * Dynamic article-details block callback.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Saved block content.
	 * @param WP_Block $block      Block instance.
	 * @return string
	 */
	public function render_details_block( $attributes = array(), $content = '', $block = null ) {
		unset( $attributes, $content );
		$post_id = $this->block_post_id( $block );
		if (
			! $post_id
			|| ! in_array( get_post_type( $post_id ), $this->enabled_post_types(), true )
			|| ! $this->component_is_visible( $post_id, self::META_DETAILS, 'show_details' )
		) {
			return '';
		}

		return $this->render_details( $post_id );
	}

	/**
	 * Dynamic TL;DR block callback.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Saved block content.
	 * @param WP_Block $block      Block instance.
	 * @return string
	 */
	public function render_tldr_block( $attributes = array(), $content = '', $block = null ) {
		unset( $attributes, $content );
		$post_id = $this->block_post_id( $block );
		if (
			! $post_id
			|| ! in_array( get_post_type( $post_id ), $this->enabled_post_types(), true )
			|| ! $this->component_is_visible( $post_id, self::META_SHOW_TLDR, 'show_tldr' )
		) {
			return '';
		}

		return $this->render_tldr( $post_id );
	}

	/**
	 * Read a post ID from block context or the loop.
	 *
	 * @param WP_Block|null $block Block instance.
	 * @return int
	 */
	private function block_post_id( $block ) {
		if ( $block instanceof WP_Block && ! empty( $block->context['postId'] ) ) {
			return (int) $block->context['postId'];
		}
		return (int) get_the_ID();
	}

	/**
	 * Render the published/updated and reading-time bar.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public function render_details( $post_id ) {
		if ( ! $this->eligible_post( $post_id ) ) {
			return '';
		}

		$settings       = $this->settings();
		$published_time = (int) get_post_time( 'U', true, $post_id );
		$modified_time  = (int) get_post_modified_time( 'U', true, $post_id );
		$is_modified    = $modified_time > $published_time;
		$label          = $is_modified ? $settings['modified_label'] : $settings['published_label'];
		$label          = $this->filter( 'date_label', $label, $is_modified, $post_id );
		$date           = $is_modified ? get_the_modified_date( '', $post_id ) : get_the_date( '', $post_id );
		$iso            = $is_modified
			? get_post_modified_time( DATE_W3C, false, $post_id )
			: get_post_time( DATE_W3C, false, $post_id );
		$minutes        = $this->get_reading_minutes( $post_id );
		$read_label     = str_replace( '%s', number_format_i18n( $minutes ), $settings['read_label'] );
		$read_label     = $this->filter( 'reading_label', $read_label, $minutes, $post_id );

		$clock_icon = '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg>';
		$book_icon  = '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H11v16H6.5A2.5 2.5 0 0 0 4 21.5z"></path><path d="M20 5.5A2.5 2.5 0 0 0 17.5 3H13v16h4.5A2.5 2.5 0 0 1 20 21.5z"></path></svg>';

		$html  = '<aside class="seoblox-article-details aig-article-details" aria-label="' . esc_attr__( 'Article details', 'seoblox' ) . '">';
		$html .= '<div class="seoblox-article-details__item aig-article-details__item"><span class="seoblox-article-details__icon aig-article-details__icon">' . $clock_icon . '</span>';
		$html .= '<span><strong>' . esc_html( $label ) . '</strong> <time datetime="' . esc_attr( $iso ) . '">' . esc_html( $date ) . '</time></span></div>';
		$html .= '<span class="seoblox-article-details__divider aig-article-details__divider" aria-hidden="true"></span>';
		$html .= '<div class="seoblox-article-details__item aig-article-details__item"><span class="seoblox-article-details__icon aig-article-details__icon">' . $book_icon . '</span>';
		$html .= '<strong>' . esc_html( $read_label ) . '</strong></div></aside>';

		return $this->filter( 'article_details_html', $html, $post_id );
	}

	/**
	 * Render an editor-approved TL;DR.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public function render_tldr( $post_id ) {
		if ( ! $this->eligible_post( $post_id ) ) {
			return '';
		}

		$tldr = $this->sanitize_tldr( $this->post_meta( $post_id, self::META_TLDR ) );
		if ( '' === trim( wp_strip_all_tags( $tldr ) ) ) {
			return '';
		}

		$format   = $this->post_meta( $post_id, self::META_TLDR_FORMAT );
		$has_list = false !== stripos( $tldr, '<ul' ) || false !== stripos( $tldr, '<ol' );
		$has_item = false !== stripos( $tldr, '<li' );
		$has_para = false !== stripos( $tldr, '<p' );

		if ( ! $has_list && $has_item ) {
			$tldr = '<ul>' . $tldr . '</ul>';
		} elseif ( ! $has_list && ! $has_item && 'list' === $format ) {
			$tldr = '<ul><li>' . $tldr . '</li></ul>';
		} elseif ( ! $has_para && ! $has_list ) {
			$tldr = '<p>' . $tldr . '</p>';
		}

		$html  = '<aside class="seoblox-tldr aig-tldr" aria-labelledby="seoblox-tldr-title-' . (int) $post_id . '">';
		$html .= '<h2 class="seoblox-tldr__title aig-tldr__title" id="seoblox-tldr-title-' . (int) $post_id . '">' . esc_html__( 'TL;DR', 'seoblox' ) . '</h2>';
		$html .= '<div class="seoblox-tldr__content aig-tldr__content">' . $tldr . '</div></aside>';

		return $this->filter( 'tldr_html', $html, $post_id, $tldr );
	}

	/**
	 * Keep supported SEO plugins' existing Article node aligned with WordPress.
	 *
	 * @param array<string,mixed> $data Article schema data.
	 * @return array<string,mixed>
	 */
	public function filter_article_schema( $data ) {
		$settings = $this->settings();
		if ( 'auto' !== $settings['schema_mode'] || ! is_array( $data ) || ! is_singular( $this->enabled_post_types() ) ) {
			return $data;
		}

		// SEOblox never outputs Product, Offer, AggregateRating-on-product,
		// or Merchant schema. WooGEO owns commerce; only an existing Article
		// entity's dateModified may be changed here. Never emit a second graph.
		$types    = isset( $data['@type'] ) ? (array) $data['@type'] : array();
		$commerce = array( 'Product', 'ProductGroup', 'Offer', 'AggregateOffer', 'AggregateRating', 'Merchant', 'MerchantReturnPolicy', 'OnlineStore' );
		$articles = array(
			'Article', 'BlogPosting', 'NewsArticle', 'TechArticle', 'ScholarlyArticle',
			'MedicalScholarlyArticle', 'Report', 'SocialMediaPosting', 'LiveBlogPosting',
			'DiscussionForumPosting', 'AdvertiserContentArticle', 'SatiricalArticle',
			'AnalysisNewsArticle', 'AskPublicNewsArticle', 'BackgroundNewsArticle',
			'OpinionNewsArticle', 'ReportageNewsArticle', 'ReviewNewsArticle',
		);
		if ( array_intersect( $types, $commerce ) || ( $types && ! array_intersect( $types, $articles ) ) ) {
			return $data;
		}
		$post_id = get_queried_object_id();
		if ( $post_id && $this->eligible_post( $post_id ) ) {
			$data['dateModified'] = get_post_modified_time( DATE_W3C, false, $post_id );
		}

		return $data;
	}

	/**
	 * Add a Settings link on the Plugins screen.
	 *
	 * @param string[] $links Existing action links.
	 * @return string[]
	 */
	public function settings_link( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'options-general.php?page=seoblox' ) ) . '">' .
			esc_html__( 'Settings', 'seoblox' ) .
			'</a>'
		);
		return $links;
	}
}
