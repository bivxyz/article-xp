<?php
/**
 * Admin settings screen.
 *
 * @package ArticleInsightsForGEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AIG_Settings {
	/**
	 * Plugin controller.
	 *
	 * @var AIG_Plugin
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param AIG_Plugin $plugin Plugin controller.
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
		if ( 'settings_page_article-insights-for-geo' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style( 'aig-frontend' );
		wp_enqueue_style(
			'aig-settings',
			AIG_PLUGIN_URL . 'assets/css/settings.css',
			array( 'aig-frontend' ),
			AIG_VERSION
		);
		wp_enqueue_script(
			'aig-settings',
			AIG_PLUGIN_URL . 'assets/js/settings.js',
			array(),
			AIG_VERSION,
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
			__( 'Article Insights', 'article-insights-for-geo' ),
			__( 'Article Insights', 'article-insights-for-geo' ),
			'manage_options',
			'article-insights-for-geo',
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
			'aig_settings_group',
			AIG_Plugin::OPTION_KEY,
			array(
				'type'              => 'array',
				'default'           => AIG_Plugin::defaults(),
				'sanitize_callback' => array( $this, 'sanitize' ),
			)
		);

		add_settings_section(
			'aig_content',
			__( 'Content and placement', 'article-insights-for-geo' ),
			static function () {
				echo '<p>' . esc_html__( 'Choose where Article Insights is available and which elements are inserted automatically.', 'article-insights-for-geo' ) . '</p>';
			},
			'article-insights-for-geo'
		);

		$this->add_field( 'post_types', __( 'Content types', 'article-insights-for-geo' ), array( $this, 'render_post_types' ), 'aig_content' );
		$this->add_field( 'components', __( 'Visible elements', 'article-insights-for-geo' ), array( $this, 'render_components' ), 'aig_content' );
		$this->add_field( 'automatic', __( 'Automatic placement', 'article-insights-for-geo' ), array( $this, 'render_automatic' ), 'aig_content' );
		$this->add_field( 'wpm', __( 'Reading speed', 'article-insights-for-geo' ), array( $this, 'render_wpm' ), 'aig_content' );

		add_settings_section( 'aig_labels', __( 'Labels', 'article-insights-for-geo' ), '__return_false', 'article-insights-for-geo' );
		$this->add_field( 'published_label', __( 'Published label', 'article-insights-for-geo' ), array( $this, 'render_text' ), 'aig_labels', 'published_label' );
		$this->add_field( 'modified_label', __( 'Modified label', 'article-insights-for-geo' ), array( $this, 'render_text' ), 'aig_labels', 'modified_label' );
		$this->add_field( 'read_label', __( 'Reading-time label', 'article-insights-for-geo' ), array( $this, 'render_read_label' ), 'aig_labels' );

		add_settings_section( 'aig_appearance', __( 'Appearance', 'article-insights-for-geo' ), '__return_false', 'article-insights-for-geo' );
		$this->add_field( 'colors', __( 'Colors', 'article-insights-for-geo' ), array( $this, 'render_colors' ), 'aig_appearance' );
		$this->add_field( 'radius', __( 'Border radius', 'article-insights-for-geo' ), array( $this, 'render_radius' ), 'aig_appearance' );
		$this->add_field( 'spacing', __( 'Spacing', 'article-insights-for-geo' ), array( $this, 'render_spacing' ), 'aig_appearance' );
		$this->add_field( 'preview', __( 'Live preview', 'article-insights-for-geo' ), array( $this, 'render_preview' ), 'aig_appearance' );

		add_settings_section( 'aig_schema', __( 'Structured data', 'article-insights-for-geo' ), '__return_false', 'article-insights-for-geo' );
		$this->add_field( 'schema_mode', __( 'Compatibility mode', 'article-insights-for-geo' ), array( $this, 'render_schema' ), 'aig_schema' );
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
		add_settings_field( $id, $label, $callback, 'article-insights-for-geo', $section, array( 'key' => $arg ) );
	}

	/**
	 * Sanitize all settings.
	 *
	 * @param mixed $input Submitted settings.
	 * @return array<string,mixed>
	 */
	public function sanitize( $input ) {
		$defaults = AIG_Plugin::defaults();
		$input    = is_array( $input ) ? $input : array();
		$public   = get_post_types( array( 'public' => true ), 'names' );
		unset( $public['attachment'] );

		$types = isset( $input['post_types'] ) && is_array( $input['post_types'] )
			? array_intersect( array_map( 'sanitize_key', $input['post_types'] ), array_values( $public ) )
			: array();

		$read_label = isset( $input['read_label'] ) ? sanitize_text_field( $input['read_label'] ) : $defaults['read_label'];
		if ( false === strpos( $read_label, '%s' ) ) {
			$read_label = $defaults['read_label'];
			add_settings_error(
				AIG_Plugin::OPTION_KEY,
				'aig_read_label',
				__( 'The reading-time label must contain %s for the number of minutes.', 'article-insights-for-geo' )
			);
		}

		$background = $this->sanitize_hex_six( $input['background'] ?? '', $defaults['background'] );
		$accent     = $this->sanitize_hex_six( $input['accent'] ?? '', $defaults['accent'] );
		$text_color = $this->sanitize_hex_six( $input['text_color'] ?? '', $defaults['text_color'] );

		if ( $this->contrast_ratio( $background, $text_color ) < 4.5 ) {
			$background = $defaults['background'];
			$text_color = $defaults['text_color'];
			add_settings_error(
				AIG_Plugin::OPTION_KEY,
				'aig_text_contrast',
				__( 'Background and text colors were reset because they did not meet accessible contrast requirements.', 'article-insights-for-geo' )
			);
		}

		if ( $this->contrast_ratio( '#ffffff', $accent ) < 3 ) {
			$accent = $defaults['accent'];
			add_settings_error(
				AIG_Plugin::OPTION_KEY,
				'aig_accent_contrast',
				__( 'The accent color was reset because it did not have enough contrast against the icon background.', 'article-insights-for-geo' )
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
		unset( $types['attachment'] );

		foreach ( $types as $type ) {
			printf(
				'<label style="display:block;margin-bottom:6px"><input type="checkbox" name="%1$s[post_types][]" value="%2$s" %3$s> %4$s</label>',
				esc_attr( AIG_Plugin::OPTION_KEY ),
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
		$this->checkbox( 'show_details', __( 'Show published/updated date and reading time', 'article-insights-for-geo' ) );
		$this->checkbox( 'show_tldr', __( 'Show TL;DR when an approved summary exists', 'article-insights-for-geo' ) );
	}

	/**
	 * Render automatic placement settings.
	 *
	 * @return void
	 */
	public function render_automatic() {
		$this->checkbox( 'auto_details', __( 'Insert article details before content', 'article-insights-for-geo' ) );
		$this->checkbox( 'auto_tldr', __( 'Insert TL;DR directly below article details', 'article-insights-for-geo' ) );
		echo '<p class="description">' . esc_html__( 'Editors can switch a post to manual placement and insert the plugin blocks.', 'article-insights-for-geo' ) . '</p>';
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
			esc_attr( AIG_Plugin::OPTION_KEY ),
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
			esc_attr( AIG_Plugin::OPTION_KEY ),
			(int) $values['words_per_minute'],
			esc_html__( 'words per minute', 'article-insights-for-geo' )
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
			esc_attr( AIG_Plugin::OPTION_KEY ),
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
		echo '<p class="description">' . esc_html__( 'Use %s where the number of minutes should appear.', 'article-insights-for-geo' ) . '</p>';
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
				'background' => __( 'Background', 'article-insights-for-geo' ),
				'accent'     => __( 'Accent', 'article-insights-for-geo' ),
				'text_color' => __( 'Text', 'article-insights-for-geo' ),
			) as $key => $label
		) {
			printf(
				'<label style="display:inline-flex;align-items:center;gap:8px;margin:0 18px 8px 0">' .
				'<span style="width:22px;height:22px;border:1px solid #8c8f94;border-radius:3px;background:%3$s" data-aig-swatch="%1$s[%2$s]" aria-hidden="true"></span>' .
				'<span>%4$s</span>' .
				'<input class="regular-text code" style="width:9ch" type="text" inputmode="text" maxlength="7" pattern="#[0-9A-Fa-f]{6}" ' .
				'name="%1$s[%2$s]" value="%3$s" aria-label="%4$s %5$s" title="%6$s">' .
				'</label>',
				esc_attr( AIG_Plugin::OPTION_KEY ),
				esc_attr( $key ),
				esc_attr( $values[ $key ] ),
				esc_html( $label ),
				esc_attr__( 'HEX color', 'article-insights-for-geo' ),
				esc_attr__( 'Enter a six-digit HEX color, including the # symbol.', 'article-insights-for-geo' )
			);
		}
		echo '<p class="description">' . esc_html__( 'Use six-digit HEX values in #RRGGBB format.', 'article-insights-for-geo' ) . '</p>';
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
			esc_attr( AIG_Plugin::OPTION_KEY ),
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
		echo '<select name="' . esc_attr( AIG_Plugin::OPTION_KEY ) . '[spacing]">';
		echo '<option value="comfortable" ' . selected( $values['spacing'], 'comfortable', false ) . '>' . esc_html__( 'Comfortable', 'article-insights-for-geo' ) . '</option>';
		echo '<option value="compact" ' . selected( $values['spacing'], 'compact', false ) . '>' . esc_html__( 'Compact', 'article-insights-for-geo' ) . '</option>';
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
			'--aig-background:%1$s;--aig-accent:%2$s;--aig-text:%3$s;--aig-radius:%4$dpx;--aig-padding:%5$s;',
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
		<div class="aig-settings-preview-frame">
			<div class="aig-settings-preview" style="<?php echo esc_attr( $style ); ?>">
				<aside class="aig-article-details" aria-label="<?php esc_attr_e( 'Article details preview', 'article-insights-for-geo' ); ?>">
					<div class="aig-article-details__item">
						<span class="aig-article-details__icon"><?php echo $clock_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?></span>
						<span>
							<strong data-aig-preview-modified><?php echo esc_html( $values['modified_label'] ); ?></strong>
							<time datetime="2026-07-28">July 28, 2026</time>
						</span>
					</div>
					<span class="aig-article-details__divider" aria-hidden="true"></span>
					<div class="aig-article-details__item">
						<span class="aig-article-details__icon"><?php echo $book_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?></span>
						<strong data-aig-preview-read><?php echo esc_html( $read_label ); ?></strong>
					</div>
				</aside>
				<aside class="aig-tldr" aria-labelledby="aig-settings-preview-title">
					<h3 class="aig-tldr__title" id="aig-settings-preview-title"><?php esc_html_e( 'TL;DR', 'article-insights-for-geo' ); ?></h3>
					<div class="aig-tldr__content">
						<p><?php esc_html_e( 'This preview uses the same styles readers will see at the beginning of an article.', 'article-insights-for-geo' ); ?></p>
					</div>
				</aside>
			</div>
		</div>
		<p class="description"><?php esc_html_e( 'Changes appear here immediately and are applied to articles after you save.', 'article-insights-for-geo' ); ?></p>
		<?php
	}

	/**
	 * Render schema mode select.
	 *
	 * @return void
	 */
	public function render_schema() {
		$values = $this->values();
		echo '<select name="' . esc_attr( AIG_Plugin::OPTION_KEY ) . '[schema_mode]">';
		echo '<option value="auto" ' . selected( $values['schema_mode'], 'auto', false ) . '>' . esc_html__( 'Auto — update supported SEO plugin Article data', 'article-insights-for-geo' ) . '</option>';
		echo '<option value="off" ' . selected( $values['schema_mode'], 'off', false ) . '>' . esc_html__( 'Off — semantic HTML only', 'article-insights-for-geo' ) . '</option>';
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Auto updates dateModified in existing Yoast or Rank Math Article schema. It never creates a competing schema graph.', 'article-insights-for-geo' ) . '</p>';
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
			<h1><?php esc_html_e( 'Article Insights', 'article-insights-for-geo' ); ?></h1>
			<p><?php esc_html_e( 'Give readers and crawlers clear, visible signals about article freshness, length, and purpose.', 'article-insights-for-geo' ); ?></p>
			<?php settings_errors(); ?>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'aig_settings_group' );
				do_settings_sections( 'article-insights-for-geo' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
