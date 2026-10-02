<?php
/**
 * Admin settings screen.
 *
 * @package SEOblox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SEOblox_Settings {
	/**
	 * Plugin controller.
	 *
	 * @var SEOblox_Plugin
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param SEOblox_Plugin $plugin Plugin controller.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Load the real component styles and live-preview behavior on this page only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'settings_page_seoblox' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style( 'seoblox-frontend' );
		wp_enqueue_style(
			'seoblox-settings',
			SEOBLOX_PLUGIN_URL . 'assets/css/settings.css',
			array( 'seoblox-frontend' ),
			SEOBLOX_VERSION
		);
		wp_enqueue_script(
			'seoblox-settings',
			SEOBLOX_PLUGIN_URL . 'assets/js/settings.js',
			array(),
			SEOBLOX_VERSION,
			true
		);
	}

	/**
	 * Add the settings page.
	 *
	 * @return void
	 */
	public function add_page() {
		add_options_page(
			__( 'SEOblox', 'seoblox' ),
			__( 'SEOblox', 'seoblox' ),
			'manage_options',
			'seoblox',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register the option and its sections.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'seoblox_settings_group',
			SEOblox_Plugin::OPTION_KEY,
			array(
				'type'              => 'array',
				'default'           => SEOblox_Plugin::defaults(),
				'sanitize_callback' => array( $this, 'sanitize' ),
			)
		);

		add_settings_section(
			'seoblox_content',
			__( 'Content and placement', 'seoblox' ),
			static function () {
				echo '<p>' . esc_html__( 'Choose where SEOblox is available and which elements are inserted automatically.', 'seoblox' ) . '</p>';
			},
			'seoblox'
		);

		$this->add_field( 'post_types', __( 'Content types', 'seoblox' ), array( $this, 'render_post_types' ), 'seoblox_content' );
		$this->add_field( 'components', __( 'Visible elements', 'seoblox' ), array( $this, 'render_components' ), 'seoblox_content' );
		$this->add_field( 'automatic', __( 'Automatic placement', 'seoblox' ), array( $this, 'render_automatic' ), 'seoblox_content' );
		$this->add_field( 'wpm', __( 'Reading speed', 'seoblox' ), array( $this, 'render_wpm' ), 'seoblox_content' );

		add_settings_section( 'seoblox_labels', __( 'Labels', 'seoblox' ), '__return_false', 'seoblox' );
		$this->add_field( 'published_label', __( 'Published label', 'seoblox' ), array( $this, 'render_text' ), 'seoblox_labels', 'published_label' );
		$this->add_field( 'modified_label', __( 'Modified label', 'seoblox' ), array( $this, 'render_text' ), 'seoblox_labels', 'modified_label' );
		$this->add_field( 'read_label', __( 'Reading-time label', 'seoblox' ), array( $this, 'render_read_label' ), 'seoblox_labels' );

		add_settings_section( 'seoblox_appearance', __( 'Appearance', 'seoblox' ), '__return_false', 'seoblox' );
		$this->add_field( 'colors', __( 'Colors', 'seoblox' ), array( $this, 'render_colors' ), 'seoblox_appearance' );
		$this->add_field( 'radius', __( 'Border radius', 'seoblox' ), array( $this, 'render_radius' ), 'seoblox_appearance' );
		$this->add_field( 'spacing', __( 'Spacing', 'seoblox' ), array( $this, 'render_spacing' ), 'seoblox_appearance' );
		$this->add_field( 'preview', __( 'Live preview', 'seoblox' ), array( $this, 'render_preview' ), 'seoblox_appearance' );

		add_settings_section( 'seoblox_schema', __( 'Structured data', 'seoblox' ), '__return_false', 'seoblox' );
		$this->add_field( 'schema_mode', __( 'Compatibility mode', 'seoblox' ), array( $this, 'render_schema' ), 'seoblox_schema' );
	}

	/**
	 * Add a settings field with optional callback argument.
	 *
	 * @param string   $id       Field ID.
	 * @param string   $label    Label.
	 * @param callable $callback Renderer.
	 * @param string   $section  Section ID.
	 * @param mixed    $arg      Optional renderer argument.
	 * @return void
	 */
	private function add_field( $id, $label, $callback, $section, $arg = null ) {
		add_settings_field( $id, $label, $callback, 'seoblox', $section, array( 'key' => $arg ) );
	}

	/**
	 * Sanitize all settings.
	 *
	 * @param mixed $input Submitted settings.
	 * @return array<string,mixed>
	 */
	public function sanitize( $input ) {
		$defaults = SEOblox_Plugin::defaults();
		$input    = is_array( $input ) ? $input : array();
		$public   = get_post_types( array( 'public' => true ), 'names' );
		$public = array_filter( $public, array( 'SEOblox_Plugin', 'is_content_post_type' ) );

		$types = isset( $input['post_types'] ) && is_array( $input['post_types'] )
			? array_intersect( array_map( 'sanitize_key', $input['post_types'] ), array_values( $public ) )
			: array();

		$read_label = isset( $input['read_label'] ) ? sanitize_text_field( $input['read_label'] ) : $defaults['read_label'];
		if ( false === strpos( $read_label, '%s' ) ) {
			$read_label = $defaults['read_label'];
			add_settings_error(
				SEOblox_Plugin::OPTION_KEY,
				'seoblox_read_label',
				__( 'The reading-time label must contain %s for the number of minutes.', 'seoblox' )
			);
		}

		$background = $this->sanitize_hex_six( $input['background'] ?? '', $defaults['background'] );
		$accent     = $this->sanitize_hex_six( $input['accent'] ?? '', $defaults['accent'] );
		$text_color = $this->sanitize_hex_six( $input['text_color'] ?? '', $defaults['text_color'] );

		if ( $this->contrast_ratio( $background, $text_color ) < 4.5 ) {
			$background = $defaults['background'];
			$text_color = $defaults['text_color'];
			add_settings_error(
				SEOblox_Plugin::OPTION_KEY,
				'seoblox_text_contrast',
				__( 'Background and text colors were reset because they did not meet accessible contrast requirements.', 'seoblox' )
			);
		}

		if ( $this->contrast_ratio( '#ffffff', $accent ) < 3 ) {
			$accent = $defaults['accent'];
			add_settings_error(
				SEOblox_Plugin::OPTION_KEY,
				'seoblox_accent_contrast',
				__( 'The accent color was reset because it did not have enough contrast against the icon background.', 'seoblox' )
			);
		}

		return array(
			'post_types'       => array_values( $types ),
			'show_details'     => empty( $input['show_details'] ) ? 0 : 1,
			'show_tldr'        => empty( $input['show_tldr'] ) ? 0 : 1,
			'auto_details'     => empty( $input['auto_details'] ) ? 0 : 1,
			'auto_tldr'        => empty( $input['auto_tldr'] ) ? 0 : 1,
			'words_per_minute' => min( 600, max( 50, absint( $input['words_per_minute'] ?? $defaults['words_per_minute'] ) ) ),
			'published_label'  => sanitize_text_field( $input['published_label'] ?? $defaults['published_label'] ),
			'modified_label'   => sanitize_text_field( $input['modified_label'] ?? $defaults['modified_label'] ),
			'read_label'       => $read_label,
			'background'       => $background,
			'accent'           => $accent,
			'text_color'       => $text_color,
			'border_radius'    => min( 40, max( 0, absint( $input['border_radius'] ?? $defaults['border_radius'] ) ) ),
			'spacing'          => in_array( $input['spacing'] ?? '', array( 'compact', 'comfortable' ), true ) ? $input['spacing'] : $defaults['spacing'],
			'schema_mode'      => 'off' === ( $input['schema_mode'] ?? '' ) ? 'off' : 'auto',
		);
	}

	/**
	 * Calculate the WCAG contrast ratio between two six-digit hex colors.
	 *
	 * @param string $first  First hex color.
	 * @param string $second Second hex color.
	 * @return float
	 */
	private function contrast_ratio( $first, $second ) {
		$first_luminance  = $this->relative_luminance( $first );
		$second_luminance = $this->relative_luminance( $second );
		$lighter          = max( $first_luminance, $second_luminance );
		$darker           = min( $first_luminance, $second_luminance );

		return ( $lighter + 0.05 ) / ( $darker + 0.05 );
	}

	/**
	 * Accept and normalize a six-digit HEX color.
	 *
	 * @param string $value   Submitted color.
	 * @param string $default Default color.
	 * @return string
	 */
	private function sanitize_hex_six( $value, $default ) {
		$value = trim( (string) $value );
		return preg_match( '/^#[0-9A-Fa-f]{6}$/', $value ) ? strtoupper( $value ) : strtoupper( $default );
	}

	/**
	 * Convert a six-digit hex color to relative luminance.
	 *
	 * @param string $hex Hex color.
	 * @return float
	 */
	private function relative_luminance( $hex ) {
		$hex      = ltrim( $hex, '#' );
		$channels = array(
			hexdec( substr( $hex, 0, 2 ) ) / 255,
			hexdec( substr( $hex, 2, 2 ) ) / 255,
			hexdec( substr( $hex, 4, 2 ) ) / 255,
		);
		$channels = array_map(
			static function ( $channel ) {
				return $channel <= 0.03928
					? $channel / 12.92
					: pow( ( $channel + 0.055 ) / 1.055, 2.4 );
			},
			$channels
		);

		return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
	}

	/**
	 * Current merged settings.
	 *
	 * @return array<string,mixed>
	 */
	private function values() {
		return $this->plugin->settings();
	}

	/**
	 * Render the content type checklist.
	 *
	 * @return void
	 */
	public function render_post_types() {
		$values = $this->values();
		$types  = get_post_types( array( 'public' => true ), 'objects' );
		$types = array_filter( $types, static function ( $type ) {
			return SEOblox_Plugin::is_content_post_type( $type->name );
		} );

		foreach ( $types as $type ) {
			printf(
				'<label style="display:block;margin-bottom:6px"><input type="checkbox" name="%1$s[post_types][]" value="%2$s" %3$s> %4$s</label>',
				esc_attr( SEOblox_Plugin::OPTION_KEY ),
				esc_attr( $type->name ),
				checked( in_array( $type->name, $values['post_types'], true ), true, false ),
				esc_html( $type->labels->name )
			);
		}
	}

	/**
	 * Render component visibility settings.
	 *
	 * @return void
	 */
	public function render_components() {
		$this->checkbox( 'show_details', __( 'Show published/updated date and reading time', 'seoblox' ) );
		$this->checkbox( 'show_tldr', __( 'Show TL;DR when an approved summary exists', 'seoblox' ) );
	}

	/**
	 * Render automatic placement settings.
	 *
	 * @return void
	 */
	public function render_automatic() {
		$this->checkbox( 'auto_details', __( 'Insert article details before content', 'seoblox' ) );
		$this->checkbox( 'auto_tldr', __( 'Insert TL;DR directly below article details', 'seoblox' ) );
		echo '<p class="description">' . esc_html__( 'Editors can switch a post to manual placement and insert the plugin blocks.', 'seoblox' ) . '</p>';
	}

	/**
	 * Render a checkbox.
	 *
	 * @param string $key   Option key.
	 * @param string $label Label.
	 * @return void
	 */
	private function checkbox( $key, $label ) {
		$values = $this->values();
		printf(
			'<label style="display:block;margin-bottom:6px"><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s> %4$s</label>',
			esc_attr( SEOblox_Plugin::OPTION_KEY ),
			esc_attr( $key ),
			checked( ! empty( $values[ $key ] ), true, false ),
			esc_html( $label )
		);
	}

	/**
	 * Render reading speed.
	 *
	 * @return void
	 */
	public function render_wpm() {
		$values = $this->values();
		printf(
			'<input class="small-text" type="number" min="50" max="600" step="1" name="%1$s[words_per_minute]" value="%2$d"> %3$s',
			esc_attr( SEOblox_Plugin::OPTION_KEY ),
			(int) $values['words_per_minute'],
			esc_html__( 'words per minute', 'seoblox' )
		);
	}

	/**
	 * Render a text label input.
	 *
	 * @param array<string,mixed> $args Field arguments.
	 * @return void
	 */
	public function render_text( $args ) {
		$key    = $args['key'];
		$values = $this->values();
		printf(
			'<input class="regular-text" type="text" name="%1$s[%2$s]" value="%3$s">',
			esc_attr( SEOblox_Plugin::OPTION_KEY ),
			esc_attr( $key ),
			esc_attr( $values[ $key ] )
		);
	}

	/**
	 * Render the reading label input.
	 *
	 * @return void
	 */
	public function render_read_label() {
		$this->render_text( array( 'key' => 'read_label' ) );
		echo '<p class="description">' . esc_html__( 'Use %s where the number of minutes should appear.', 'seoblox' ) . '</p>';
	}

	/**
	 * Render color controls.
	 *
	 * @return void
	 */
	public function render_colors() {
		$values = $this->values();
		foreach (
			array(
				'background' => __( 'Background', 'seoblox' ),
				'accent'     => __( 'Accent', 'seoblox' ),
				'text_color' => __( 'Text', 'seoblox' ),
			) as $key => $label
		) {
			printf(
				'<label style="display:inline-flex;align-items:center;gap:8px;margin:0 18px 8px 0">' .
				'<span style="width:22px;height:22px;border:1px solid #8c8f94;border-radius:3px;background:%3$s" data-seoblox-swatch="%1$s[%2$s]" aria-hidden="true"></span>' .
				'<span>%4$s</span>' .
				'<input class="regular-text code" style="width:9ch" type="text" inputmode="text" maxlength="7" pattern="#[0-9A-Fa-f]{6}" ' .
				'name="%1$s[%2$s]" value="%3$s" aria-label="%4$s %5$s" title="%6$s">' .
				'</label>',
				esc_attr( SEOblox_Plugin::OPTION_KEY ),
				esc_attr( $key ),
				esc_attr( $values[ $key ] ),
				esc_html( $label ),
				esc_attr__( 'HEX color', 'seoblox' ),
				esc_attr__( 'Enter a six-digit HEX color, including the # symbol.', 'seoblox' )
			);
		}
		echo '<p class="description">' . esc_html__( 'Use six-digit HEX values in #RRGGBB format.', 'seoblox' ) . '</p>';
	}

	/**
	 * Render radius control.
	 *
	 * @return void
	 */
	public function render_radius() {
		$values = $this->values();
		printf(
			'<input class="small-text" type="number" min="0" max="40" name="%1$s[border_radius]" value="%2$d"> px',
			esc_attr( SEOblox_Plugin::OPTION_KEY ),
			(int) $values['border_radius']
		);
	}

	/**
	 * Render spacing select.
	 *
	 * @return void
	 */
	public function render_spacing() {
		$values = $this->values();
		echo '<select name="' . esc_attr( SEOblox_Plugin::OPTION_KEY ) . '[spacing]">';
		echo '<option value="comfortable" ' . selected( $values['spacing'], 'comfortable', false ) . '>' . esc_html__( 'Comfortable', 'seoblox' ) . '</option>';
		echo '<option value="compact" ' . selected( $values['spacing'], 'compact', false ) . '>' . esc_html__( 'Compact', 'seoblox' ) . '</option>';
		echo '</select>';
	}

	/**
	 * Render a live preview using the same classes as the front-end components.
	 *
	 * @return void
	 */
	public function render_preview() {
		$values  = $this->values();
		$padding = 'compact' === $values['spacing'] ? '14px 18px' : '18px 22px';
		$style   = sprintf(
			'--seoblox-background:%1$s;--seoblox-accent:%2$s;--seoblox-text:%3$s;--seoblox-radius:%4$dpx;--seoblox-padding:%5$s;',
			esc_attr( $values['background'] ),
			esc_attr( $values['accent'] ),
			esc_attr( $values['text_color'] ),
			(int) $values['border_radius'],
			esc_attr( $padding )
		);
		$read_label = str_replace( '%s', '8', $values['read_label'] );
		$clock_icon = '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg>';
		$book_icon  = '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H11v16H6.5A2.5 2.5 0 0 0 4 21.5z"></path><path d="M20 5.5A2.5 2.5 0 0 0 17.5 3H13v16h4.5A2.5 2.5 0 0 1 20 21.5z"></path></svg>';
		?>
		<div class="seoblox-settings-preview-frame aig-settings-preview-frame">
			<div class="seoblox-settings-preview aig-settings-preview" style="<?php echo esc_attr( $style ); ?>">
				<aside class="seoblox-article-details aig-article-details" aria-label="<?php esc_attr_e( 'Article details preview', 'seoblox' ); ?>">
					<div class="seoblox-article-details__item aig-article-details__item">
						<span class="seoblox-article-details__icon aig-article-details__icon"><?php echo $clock_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?></span>
						<span>
							<strong data-seoblox-preview-modified><?php echo esc_html( $values['modified_label'] ); ?></strong>
							<time datetime="2026-07-28">July 28, 2026</time>
						</span>
					</div>
					<span class="seoblox-article-details__divider aig-article-details__divider" aria-hidden="true"></span>
					<div class="seoblox-article-details__item aig-article-details__item">
						<span class="seoblox-article-details__icon aig-article-details__icon"><?php echo $book_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?></span>
						<strong data-seoblox-preview-read><?php echo esc_html( $read_label ); ?></strong>
					</div>
				</aside>
				<aside class="seoblox-tldr aig-tldr" aria-labelledby="seoblox-settings-preview-title">
					<h3 class="seoblox-tldr__title aig-tldr__title" id="seoblox-settings-preview-title"><?php esc_html_e( 'TL;DR', 'seoblox' ); ?></h3>
					<div class="seoblox-tldr__content aig-tldr__content">
						<p><?php esc_html_e( 'This preview uses the same styles readers will see at the beginning of an article.', 'seoblox' ); ?></p>
					</div>
				</aside>
			</div>
		</div>
		<p class="description"><?php esc_html_e( 'Changes appear here immediately and are applied to articles after you save.', 'seoblox' ); ?></p>
		<?php
	}

	/**
	 * Render schema mode select.
	 *
	 * @return void
	 */
	public function render_schema() {
		$values = $this->values();
		echo '<select name="' . esc_attr( SEOblox_Plugin::OPTION_KEY ) . '[schema_mode]">';
		echo '<option value="auto" ' . selected( $values['schema_mode'], 'auto', false ) . '>' . esc_html__( 'Auto — update supported SEO plugin Article data', 'seoblox' ) . '</option>';
		echo '<option value="off" ' . selected( $values['schema_mode'], 'off', false ) . '>' . esc_html__( 'Off — semantic HTML only', 'seoblox' ) . '</option>';
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Auto updates dateModified in existing Yoast or Rank Math Article schema. It never creates a competing schema graph.', 'seoblox' ) . '</p>';
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'SEOblox', 'seoblox' ); ?></h1>
			<p><?php esc_html_e( 'Give readers and crawlers clear, visible signals about article freshness, length, and purpose.', 'seoblox' ); ?></p>
			<?php settings_errors(); ?>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'seoblox_settings_group' );
				do_settings_sections( 'seoblox' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
