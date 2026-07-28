<?php
/**
 * Main plugin controller.
 *
 * @package ArticleInsightsForGEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AIG_Plugin {
	const OPTION_KEY       = 'aig_settings';
	const META_TLDR        = '_aig_tldr';
	const META_TLDR_FORMAT = '_aig_tldr_format';
	const META_DETAILS     = '_aig_show_details';
	const META_SHOW_TLDR   = '_aig_show_tldr';
	const META_PLACEMENT   = '_aig_placement';
	const META_WORD_COUNT  = '_aig_word_count';
	const META_MINUTES     = '_aig_reading_minutes';

	/**
	 * Singleton instance.
	 *
	 * @var AIG_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Return the singleton.
	 *
	 * @return AIG_Plugin
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
		add_option( self::OPTION_KEY, self::defaults() );
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
			'published_label' => __( 'Published on', 'article-insights-for-geo' ),
			'modified_label'  => __( 'Last updated on', 'article-insights-for-geo' ),
			'read_label'      => __( '%s min read', 'article-insights-for-geo' ),
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
		add_action( 'init', array( $this, 'register_meta_and_blocks' ) );
		add_action( 'init', array( $this, 'register_assets' ), 5 );
		add_action( 'save_post', array( $this, 'cache_reading_time' ), 20, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'localize_editor' ) );

		add_filter( 'the_content', array( $this, 'prepend_insights' ), 8 );
		add_filter( 'wpseo_schema_article', array( $this, 'filter_article_schema' ) );
		add_filter( 'rank_math/snippet/rich_snippet_article_entity', array( $this, 'filter_article_schema' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( AIG_PLUGIN_FILE ), array( $this, 'settings_link' ) );

		if ( is_admin() ) {
			require_once AIG_PLUGIN_DIR . 'includes/class-aig-settings.php';
			new AIG_Settings( $this );
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

		return array_values( array_filter( array_unique( $types ), 'post_type_exists' ) );
	}

	/**
	 * Register scripts and styles.
	 *
	 * @return void
	 */
	public function register_assets() {
		wp_register_style(
			'aig-frontend',
			AIG_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			AIG_VERSION
		);
		wp_register_style(
			'aig-editor',
			AIG_PLUGIN_URL . 'assets/css/editor.css',
			array( 'wp-edit-blocks' ),
			AIG_VERSION
		);
		wp_register_script(
			'aig-editor',
			AIG_PLUGIN_URL . 'assets/js/editor.js',
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
			AIG_VERSION,
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
						'description'       => __( 'Editor-approved article summary.', 'article-insights-for-geo' ),
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

		register_block_type(
			'article-insights/details',
			array(
				'api_version'     => 2,
				'editor_script'   => 'aig-editor',
				'editor_style'    => 'aig-editor',
				'style'           => 'aig-frontend',
				'render_callback' => array( $this, 'render_details_block' ),
				'supports'        => array(
					'html'     => false,
					'multiple' => false,
				),
			)
		);

		register_block_type(
			'article-insights/tldr',
			array(
				'api_version'     => 2,
				'editor_script'   => 'aig-editor',
				'editor_style'    => 'aig-editor',
				'style'           => 'aig-frontend',
				'render_callback' => array( $this, 'render_tldr_block' ),
				'supports'        => array(
					'html'     => false,
					'multiple' => false,
				),
			)
		);
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
		return current_user_can( 'edit_post', $object_id );
	}

	/**
	 * Sanitize TL;DR markup to the intentionally small rich-text subset.
	 *
	 * @param string $value Submitted HTML.
	 * @return string
	 */
	public function sanitize_tldr( $value ) {
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
		return apply_filters( 'aig_sanitized_tldr', trim( $value ) );
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
			'aig-editor',
			'aigEditor',
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

		wp_enqueue_style( 'aig-frontend' );
		$settings = $this->settings();
		$padding  = 'compact' === $settings['spacing'] ? '14px 18px' : '18px 22px';
		$css      = sprintf(
			':root{--aig-background:%1$s;--aig-accent:%2$s;--aig-text:%3$s;--aig-radius:%4$dpx;--aig-padding:%5$s;}',
			esc_html( $settings['background'] ),
			esc_html( $settings['accent'] ),
			esc_html( $settings['text_color'] ),
			(int) $settings['border_radius'],
			esc_html( $padding )
		);
		wp_add_inline_style( 'aig-frontend', $css );
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
			|| 'attachment' === $post->post_type
		) {
			return;
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
		$wpm      = max( 1, (int) apply_filters( 'aig_words_per_minute', $wpm, $post_id ) );
		$minutes  = max( 1, (int) ceil( $word_count / $wpm ) );

		return max( 1, (int) apply_filters( 'aig_reading_minutes', $minutes, $word_count, $post_id ) );
	}

	/**
	 * Get reading time from cached words, recalculating for older content once.
	 *
	 * @param int $post_id Post ID.
	 * @return int
	 */
	public function get_reading_minutes( $post_id ) {
		$cached = get_post_meta( $post_id, self::META_WORD_COUNT, true );
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
			|| ! in_the_loop()
			|| ! is_main_query()
		) {
			return $content;
		}

		$post_id = get_the_ID();
		if ( ! $post_id || 'manual' === get_post_meta( $post_id, self::META_PLACEMENT, true ) ) {
			return $content;
		}

		static $rendered = array();
		if ( isset( $rendered[ $post_id ] ) ) {
			return $content;
		}
		$rendered[ $post_id ] = true;

		$settings = $this->settings();
		$output   = '';

		if (
			! empty( $settings['auto_details'] )
			&& $this->component_is_visible( $post_id, self::META_DETAILS, 'show_details' )
			&& ! has_block( 'article-insights/details', $content )
		) {
			$output .= $this->render_details( $post_id );
		}

		if (
			! empty( $settings['auto_tldr'] )
			&& $this->component_is_visible( $post_id, self::META_SHOW_TLDR, 'show_tldr' )
			&& ! has_block( 'article-insights/tldr', $content )
		) {
			$output .= $this->render_tldr( $post_id );
		}

		return $output . $content;
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
		$override = get_post_meta( $post_id, $meta_key, true );
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
		$settings       = $this->settings();
		$published_time = (int) get_post_time( 'U', true, $post_id );
		$modified_time  = (int) get_post_modified_time( 'U', true, $post_id );
		$is_modified    = $modified_time > $published_time;
		$label          = $is_modified ? $settings['modified_label'] : $settings['published_label'];
		$label          = apply_filters( 'aig_date_label', $label, $is_modified, $post_id );
		$date           = $is_modified ? get_the_modified_date( '', $post_id ) : get_the_date( '', $post_id );
		$iso            = $is_modified
			? get_post_modified_time( DATE_W3C, false, $post_id )
			: get_post_time( DATE_W3C, false, $post_id );
		$minutes        = $this->get_reading_minutes( $post_id );
		$read_label     = str_replace( '%s', number_format_i18n( $minutes ), $settings['read_label'] );
		$read_label     = apply_filters( 'aig_reading_label', $read_label, $minutes, $post_id );

		$clock_icon = '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg>';
		$book_icon  = '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H11v16H6.5A2.5 2.5 0 0 0 4 21.5z"></path><path d="M20 5.5A2.5 2.5 0 0 0 17.5 3H13v16h4.5A2.5 2.5 0 0 1 20 21.5z"></path></svg>';

		$html  = '<aside class="aig-article-details" aria-label="' . esc_attr__( 'Article details', 'article-insights-for-geo' ) . '">';
		$html .= '<div class="aig-article-details__item"><span class="aig-article-details__icon">' . $clock_icon . '</span>';
		$html .= '<span><strong>' . esc_html( $label ) . '</strong> <time datetime="' . esc_attr( $iso ) . '">' . esc_html( $date ) . '</time></span></div>';
		$html .= '<span class="aig-article-details__divider" aria-hidden="true"></span>';
		$html .= '<div class="aig-article-details__item"><span class="aig-article-details__icon">' . $book_icon . '</span>';
		$html .= '<strong>' . esc_html( $read_label ) . '</strong></div></aside>';

		return apply_filters( 'aig_article_details_html', $html, $post_id );
	}

	/**
	 * Render an editor-approved TL;DR.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public function render_tldr( $post_id ) {
		$tldr = $this->sanitize_tldr( get_post_meta( $post_id, self::META_TLDR, true ) );
		if ( '' === trim( wp_strip_all_tags( $tldr ) ) ) {
			return '';
		}

		$format   = get_post_meta( $post_id, self::META_TLDR_FORMAT, true );
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

		$html  = '<aside class="aig-tldr" aria-labelledby="aig-tldr-title-' . (int) $post_id . '">';
		$html .= '<h2 class="aig-tldr__title" id="aig-tldr-title-' . (int) $post_id . '">' . esc_html__( 'TL;DR', 'article-insights-for-geo' ) . '</h2>';
		$html .= '<div class="aig-tldr__content">' . $tldr . '</div></aside>';

		return apply_filters( 'aig_tldr_html', $html, $post_id, $tldr );
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

		$post_id = get_queried_object_id();
		if ( $post_id ) {
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
			'<a href="' . esc_url( admin_url( 'options-general.php?page=article-insights-for-geo' ) ) . '">' .
			esc_html__( 'Settings', 'article-insights-for-geo' ) .
			'</a>'
		);
		return $links;
	}
}
