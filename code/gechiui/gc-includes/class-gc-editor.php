<?php
/**
 * Facilitates adding of the GeChiUI editor as used on the Write and Edit screens.
 *
 * @package GeChiUI
 *
 *
 * Private, not included by default. See gc_editor() in gc-includes/general-template.php.
 */

final class _GC_Editors {
	public static $mce_locale;

	private static $mce_settings = array();
	private static $qt_settings  = array();
	private static $plugins      = array();
	private static $qt_buttons   = array();
	private static $ext_plugins;
	private static $baseurl;
	private static $first_init;
	private static $this_tinymce       = false;
	private static $this_quicktags     = false;
	private static $has_tinymce        = false;
	private static $has_quicktags      = false;
	private static $has_medialib       = false;
	private static $editor_buttons_css = true;
	private static $drag_drop_upload   = false;
	private static $translation;
	private static $tinymce_scripts_printed = false;
	private static $link_dialog_printed     = false;

	private function __construct() {}

	/**
	 * Parse default arguments for the editor instance.
	 *
	 *
	 * @param string $editor_id HTML ID for the textarea and TinyMCE and Quicktags instances.
	 *                          Should not contain square brackets.
	 * @param array  $settings {
	 *     Array of editor arguments.
	 *
	 *     @type bool       $gcautop           Whether to use gcautop(). Default true.
	 *     @type bool       $media_buttons     Whether to show the Add Media/other media buttons.
	 *     @type string     $default_editor    When both TinyMCE and Quicktags are used, set which
	 *                                         editor is shown on page load. Default empty.
	 *     @type bool       $drag_drop_upload  Whether to enable drag & drop on the editor uploading. Default false.
	 *                                         Requires the media modal.
	 *     @type string     $textarea_name     Give the textarea a unique name here. Square brackets
	 *                                         can be used here. Default $editor_id.
	 *     @type int        $textarea_rows     Number rows in the editor textarea. Default 20.
	 *     @type string|int $tabindex          Tabindex value to use. Default empty.
	 *     @type string     $tabfocus_elements The previous and next element ID to move the focus to
	 *                                         when pressing the Tab key in TinyMCE. Default ':prev,:next'.
	 *     @type string     $editor_css        Intended for extra styles for both Visual and Text editors.
	 *                                         Should include `<style>` tags, and can use "scoped". Default empty.
	 *     @type string     $editor_class      Extra classes to add to the editor textarea element. Default empty.
	 *     @type bool       $teeny             Whether to output the minimal editor config. Examples include
	 *                                         Press This and the Comment editor. Default false.
	 *     @type bool       $dfw               Deprecated in 4.1. Unused.
	 *     @type bool|array $tinymce           Whether to load TinyMCE. Can be used to pass settings directly to
	 *                                         TinyMCE using an array. Default true.
	 *     @type bool|array $quicktags         Whether to load Quicktags. Can be used to pass settings directly to
	 *                                         Quicktags using an array. Default true.
	 * }
	 * @return array Parsed arguments array.
	 */
	public static function parse_settings( $editor_id, $settings ) {

		/**
		 * Filters the gc_editor() settings.
		 *
		 *
		 * @see _GC_Editors::parse_settings()
		 *
		 * @param array  $settings  Array of editor arguments.
		 * @param string $editor_id Unique editor identifier, e.g. 'content'. Accepts 'classic-block'
		 *                          when called from block editor's Classic block.
		 */
		$settings = apply_filters( 'gc_editor_settings', $settings, $editor_id );

		$set = gc_parse_args(
			$settings,
			array(
				// Disable autop if the current post has blocks in it.
				'gcautop'             => ! has_blocks(),
				'media_buttons'       => true,
				'default_editor'      => '',
				'drag_drop_upload'    => false,
				'textarea_name'       => $editor_id,
				'textarea_rows'       => 20,
				'tabindex'            => '',
				'tabfocus_elements'   => ':prev,:next',
				'editor_css'          => '',
				'editor_class'        => '',
				'teeny'               => false,
				'_content_editor_dfw' => false,
				'tinymce'             => true,
				'quicktags'           => true,
			)
		);

		self::$this_tinymce = ( $set['tinymce'] && user_can_richedit() );

		if ( self::$this_tinymce ) {
			if ( false !== strpos( $editor_id, '[' ) ) {
				self::$this_tinymce = false;
				_deprecated_argument( 'gc_editor()', '3.9.0', 'TinyMCE editor IDs cannot have brackets.' );
			}
		}

		self::$this_quicktags = (bool) $set['quicktags'];

		if ( self::$this_tinymce ) {
			self::$has_tinymce = true;
		}

		if ( self::$this_quicktags ) {
			self::$has_quicktags = true;
		}

		if ( empty( $set['editor_height'] ) ) {
			return $set;
		}

		if ( 'content' === $editor_id && empty( $set['tinymce']['gc_autoresize_on'] ) ) {
			// A cookie (set when a user resizes the editor) overrides the height.
			$cookie = (int) get_user_setting( 'ed_size' );

			if ( $cookie ) {
				$set['editor_height'] = $cookie;
			}
		}

		if ( $set['editor_height'] < 50 ) {
			$set['editor_height'] = 50;
		} elseif ( $set['editor_height'] > 5000 ) {
			$set['editor_height'] = 5000;
		}

		return $set;
	}

	/**
	 * Outputs the HTML for a single instance of the editor.
	 *
	 *
	 * @param string $content   Initial content for the editor.
	 * @param string $editor_id HTML ID for the textarea and TinyMCE and Quicktags instances.
	 *                          Should not contain square brackets.
	 * @param array  $settings  See _GC_Editors::parse_settings() for description.
	 */
	public static function editor( $content, $editor_id, $settings = array() ) {
		$set            = self::parse_settings( $editor_id, $settings );
		$editor_class   = ' class="' . trim( esc_attr( $set['editor_class'] ) . ' gc-editor-area' ) . '"';
		$tabindex       = $set['tabindex'] ? ' tabindex="' . (int) $set['tabindex'] . '"' : '';
		$default_editor = 'html';
		$buttons        = '';
		$autocomplete   = '';
		$editor_id_attr = esc_attr( $editor_id );

		if ( $set['drag_drop_upload'] ) {
			self::$drag_drop_upload = true;
		}

		if ( ! empty( $set['editor_height'] ) ) {
			$height = ' style="height: ' . (int) $set['editor_height'] . 'px"';
		} else {
			$height = ' rows="' . (int) $set['textarea_rows'] . '"';
		}

		if ( ! current_user_can( 'upload_files' ) ) {
			$set['media_buttons'] = false;
		}

		if ( self::$this_tinymce ) {
			$autocomplete = ' autocomplete="off"';

			if ( self::$this_quicktags ) {
				$default_editor = $set['default_editor'] ? $set['default_editor'] : gc_default_editor();
				// 'html' is used for the "Text" editor tab.
				if ( 'html' !== $default_editor ) {
					$default_editor = 'tinymce';
				}

				$buttons .= '<button type="button" id="' . $editor_id_attr . '-tmce" class="gc-switch-editor switch-tmce"' .
					' data-gc-editor-id="' . $editor_id_attr . '">' . _x( '?????????', 'Name for the Visual editor tab' ) . "</button>\n";
				$buttons .= '<button type="button" id="' . $editor_id_attr . '-html" class="gc-switch-editor switch-html"' .
					' data-gc-editor-id="' . $editor_id_attr . '">' . _x( '??????', 'Name for the Text editor tab (formerly HTML)' ) . "</button>\n";
			} else {
				$default_editor = 'tinymce';
			}
		}

		$switch_class = 'html' === $default_editor ? 'html-active' : 'tmce-active';
		$wrap_class   = 'gc-core-ui gc-editor-wrap ' . $switch_class;

		if ( $set['_content_editor_dfw'] ) {
			$wrap_class .= ' has-dfw';
		}

		echo '<div id="gc-' . $editor_id_attr . '-wrap" class="' . $wrap_class . '">';

		if ( self::$editor_buttons_css ) {
			gc_print_styles( 'editor-buttons' );
			self::$editor_buttons_css = false;
		}

		if ( ! empty( $set['editor_css'] ) ) {
			echo $set['editor_css'] . "\n";
		}

		if ( ! empty( $buttons ) || $set['media_buttons'] ) {
			echo '<div id="gc-' . $editor_id_attr . '-editor-tools" class="gc-editor-tools hide-if-no-js">';

			if ( $set['media_buttons'] ) {
				self::$has_medialib = true;

				if ( ! function_exists( 'media_buttons' ) ) {
					require ABSPATH . 'gc-admin/includes/media.php';
				}

				echo '<div id="gc-' . $editor_id_attr . '-media-buttons" class="gc-media-buttons">';

				/**
				 * Fires after the default media button(s) are displayed.
				 *
			
				 *
				 * @param string $editor_id Unique editor identifier, e.g. 'content'.
				 */
				do_action( 'media_buttons', $editor_id );
				echo "</div>\n";
			}

			echo '<div class="gc-editor-tabs">' . $buttons . "</div>\n";
			echo "</div>\n";
		}

		$quicktags_toolbar = '';

		if ( self::$this_quicktags ) {
			if ( 'content' === $editor_id && ! empty( $GLOBALS['current_screen'] ) && 'post' === $GLOBALS['current_screen']->base ) {
				$toolbar_id = 'ed_toolbar';
			} else {
				$toolbar_id = 'qt_' . $editor_id_attr . '_toolbar';
			}

			$quicktags_toolbar = '<div id="' . $toolbar_id . '" class="quicktags-toolbar hide-if-no-js"></div>';
		}

		/**
		 * Filters the HTML markup output that displays the editor.
		 *
		 *
		 * @param string $output Editor's HTML markup.
		 */
		$the_editor = apply_filters(
			'the_editor',
			'<div id="gc-' . $editor_id_attr . '-editor-container" class="gc-editor-container">' .
			$quicktags_toolbar .
			'<textarea' . $editor_class . $height . $tabindex . $autocomplete . ' cols="40" name="' . esc_attr( $set['textarea_name'] ) . '" ' .
			'id="' . $editor_id_attr . '">%s</textarea></div>'
		);

		// Prepare the content for the Visual or Text editor, only when TinyMCE is used (back-compat).
		if ( self::$this_tinymce ) {
			add_filter( 'the_editor_content', 'format_for_editor', 10, 2 );
		}

		/**
		 * Filters the default editor content.
		 *
		 *
		 * @param string $content        Default editor content.
		 * @param string $default_editor The default editor for the current user.
		 *                               Either 'html' or 'tinymce'.
		 */
		$content = apply_filters( 'the_editor_content', $content, $default_editor );

		// Remove the filter as the next editor on the same page may not need it.
		if ( self::$this_tinymce ) {
			remove_filter( 'the_editor_content', 'format_for_editor' );
		}

		// Back-compat for the `htmledit_pre` and `richedit_pre` filters.
		if ( 'html' === $default_editor && has_filter( 'htmledit_pre' ) ) {
			/** This filter is documented in gc-includes/deprecated.php */
			$content = apply_filters_deprecated( 'htmledit_pre', array( $content ), '4.3.0', 'format_for_editor' );
		} elseif ( 'tinymce' === $default_editor && has_filter( 'richedit_pre' ) ) {
			/** This filter is documented in gc-includes/deprecated.php */
			$content = apply_filters_deprecated( 'richedit_pre', array( $content ), '4.3.0', 'format_for_editor' );
		}

		if ( false !== stripos( $content, 'textarea' ) ) {
			$content = preg_replace( '%</textarea%i', '&lt;/textarea', $content );
		}

		printf( $the_editor, $content );
		echo "\n</div>\n\n";

		self::editor_settings( $editor_id, $set );
	}

	/**
	 *
	 * @param string $editor_id Unique editor identifier, e.g. 'content'.
	 * @param array  $set       Array of editor arguments.
	 */
	public static function editor_settings( $editor_id, $set ) {
		if ( empty( self::$first_init ) ) {
			if ( is_admin() ) {
				add_action( 'admin_print_footer_scripts', array( __CLASS__, 'editor_js' ), 50 );
				add_action( 'admin_print_footer_scripts', array( __CLASS__, 'force_uncompressed_tinymce' ), 1 );
				add_action( 'admin_print_footer_scripts', array( __CLASS__, 'enqueue_scripts' ), 1 );
			} else {
				add_action( 'gc_print_footer_scripts', array( __CLASS__, 'editor_js' ), 50 );
				add_action( 'gc_print_footer_scripts', array( __CLASS__, 'force_uncompressed_tinymce' ), 1 );
				add_action( 'gc_print_footer_scripts', array( __CLASS__, 'enqueue_scripts' ), 1 );
			}
		}

		if ( self::$this_quicktags ) {

			$qtInit = array(
				'id'      => $editor_id,
				'buttons' => '',
			);

			if ( is_array( $set['quicktags'] ) ) {
				$qtInit = array_merge( $qtInit, $set['quicktags'] );
			}

			if ( empty( $qtInit['buttons'] ) ) {
				$qtInit['buttons'] = 'strong,em,link,block,del,ins,img,ul,ol,li,code,more,close';
			}

			if ( $set['_content_editor_dfw'] ) {
				$qtInit['buttons'] .= ',dfw';
			}

			/**
			 * Filters the Quicktags settings.
			 *
		
			 *
			 * @param array  $qtInit    Quicktags settings.
			 * @param string $editor_id Unique editor identifier, e.g. 'content'.
			 */
			$qtInit = apply_filters( 'quicktags_settings', $qtInit, $editor_id );

			self::$qt_settings[ $editor_id ] = $qtInit;

			self::$qt_buttons = array_merge( self::$qt_buttons, explode( ',', $qtInit['buttons'] ) );
		}

		if ( self::$this_tinymce ) {

			if ( empty( self::$first_init ) ) {
				$baseurl     = self::get_baseurl();
				$mce_locale  = self::get_mce_locale();
				$ext_plugins = '';

				if ( $set['teeny'] ) {

					/**
					 * Filters the list of teenyMCE plugins.
					 *
				
				
					 *
					 * @param array  $plugins   An array of teenyMCE plugins.
					 * @param string $editor_id Unique editor identifier, e.g. 'content'.
					 */
					$plugins = apply_filters(
						'teeny_mce_plugins',
						array(
							'colorpicker',
							'lists',
							'fullscreen',
							'image',
							'gechiui',
							'gceditimage',
							'gclink',
						),
						$editor_id
					);
				} else {

					/**
					 * Filters the list of TinyMCE external plugins.
					 *
					 * The filter takes an associative array of external plugins for
					 * TinyMCE in the form 'plugin_name' => 'url'.
					 *
					 * The url should be absolute, and should include the js filename
					 * to be loaded. For example:
					 * 'myplugin' => 'http://mysite.com/gc-content/plugins/myfolder/mce_plugin.js'.
					 *
					 * If the external plugin adds a button, it should be added with
					 * one of the 'mce_buttons' filters.
					 *
				
				
					 *
					 * @param array  $external_plugins An array of external TinyMCE plugins.
					 * @param string $editor_id        Unique editor identifier, e.g. 'content'. Accepts 'classic-block'
					 *                                 when called from block editor's Classic block.
					 */
					$mce_external_plugins = apply_filters( 'mce_external_plugins', array(), $editor_id );

					$plugins = array(
						'charmap',
						'colorpicker',
						'hr',
						'lists',
						'media',
						'paste',
						'tabfocus',
						'textcolor',
						'fullscreen',
						'gechiui',
						'gcautoresize',
						'gceditimage',
						'gcemoji',
						'gcgallery',
						'gclink',
						'gcdialogs',
						'gctextpattern',
						'gcview',
					);

					if ( ! self::$has_medialib ) {
						$plugins[] = 'image';
					}

					/**
					 * Filters the list of default TinyMCE plugins.
					 *
					 * The filter specifies which of the default plugins included
					 * in GeChiUI should be added to the TinyMCE instance.
					 *
				
				
					 *
					 * @param array  $plugins   An array of default TinyMCE plugins.
					 * @param string $editor_id Unique editor identifier, e.g. 'content'. Accepts 'classic-block'
					 *                          when called from block editor's Classic block.
					 */
					$plugins = array_unique( apply_filters( 'tiny_mce_plugins', $plugins, $editor_id ) );

					$key = array_search( 'spellchecker', $plugins, true );
					if ( false !== $key ) {
						// Remove 'spellchecker' from the internal plugins if added with 'tiny_mce_plugins' filter to prevent errors.
						// It can be added with 'mce_external_plugins'.
						unset( $plugins[ $key ] );
					}

					if ( ! empty( $mce_external_plugins ) ) {

						/**
						 * Filters the translations loaded for external TinyMCE 3.x plugins.
						 *
						 * The filter takes an associative array ('plugin_name' => 'path')
						 * where 'path' is the include path to the file.
						 *
						 * The language file should follow the same format as gc_mce_translation(),
						 * and should define a variable ($strings) that holds all translated strings.
						 *
					
					
						 *
						 * @param array  $translations Translations for external TinyMCE plugins.
						 * @param string $editor_id    Unique editor identifier, e.g. 'content'.
						 */
						$mce_external_languages = apply_filters( 'mce_external_languages', array(), $editor_id );

						$loaded_langs = array();
						$strings      = '';

						if ( ! empty( $mce_external_languages ) ) {
							foreach ( $mce_external_languages as $name => $path ) {
								if ( @is_file( $path ) && @is_readable( $path ) ) {
									include_once $path;
									$ext_plugins   .= $strings . "\n";
									$loaded_langs[] = $name;
								}
							}
						}

						foreach ( $mce_external_plugins as $name => $url ) {
							if ( in_array( $name, $plugins, true ) ) {
								unset( $mce_external_plugins[ $name ] );
								continue;
							}

							$url                           = set_url_scheme( $url );
							$mce_external_plugins[ $name ] = $url;
							$plugurl                       = dirname( $url );
							$strings                       = '';

							// Try to load langs/[locale].js and langs/[locale]_dlg.js.
							if ( ! in_array( $name, $loaded_langs, true ) ) {
								$path = str_replace( content_url(), '', $plugurl );
								$path = GC_CONTENT_DIR . $path . '/langs/';

								$path = trailingslashit( realpath( $path ) );

								if ( @is_file( $path . $mce_locale . '.js' ) ) {
									$strings .= @file_get_contents( $path . $mce_locale . '.js' ) . "\n";
								}

								if ( @is_file( $path . $mce_locale . '_dlg.js' ) ) {
									$strings .= @file_get_contents( $path . $mce_locale . '_dlg.js' ) . "\n";
								}

								if ( 'en' !== $mce_locale && empty( $strings ) ) {
									if ( @is_file( $path . 'en.js' ) ) {
										$str1     = @file_get_contents( $path . 'en.js' );
										$strings .= preg_replace( '/([\'"])en\./', '$1' . $mce_locale . '.', $str1, 1 ) . "\n";
									}

									if ( @is_file( $path . 'en_dlg.js' ) ) {
										$str2     = @file_get_contents( $path . 'en_dlg.js' );
										$strings .= preg_replace( '/([\'"])en\./', '$1' . $mce_locale . '.', $str2, 1 ) . "\n";
									}
								}

								if ( ! empty( $strings ) ) {
									$ext_plugins .= "\n" . $strings . "\n";
								}
							}

							$ext_plugins .= 'tinyMCEPreInit.load_ext("' . $plugurl . '", "' . $mce_locale . '");' . "\n";
						}
					}
				}

				self::$plugins     = $plugins;
				self::$ext_plugins = $ext_plugins;

				$settings            = self::default_settings();
				$settings['plugins'] = implode( ',', $plugins );

				if ( ! empty( $mce_external_plugins ) ) {
					$settings['external_plugins'] = gc_json_encode( $mce_external_plugins );
				}

				/** This filter is documented in gc-admin/includes/media.php */
				if ( apply_filters( 'disable_captions', '' ) ) {
					$settings['gceditimage_disable_captions'] = true;
				}

				$mce_css = $settings['content_css'];

				/*
				 * The `editor-style.css` added by the theme is generally intended for the editor instance on the Edit Post screen.
				 * Plugins that use gc_editor() on the front-end can decide whether to add the theme stylesheet
				 * by using `get_editor_stylesheets()` and the `mce_css` or `tiny_mce_before_init` filters, see below.
				 */
				if ( is_admin() ) {
					$editor_styles = get_editor_stylesheets();

					if ( ! empty( $editor_styles ) ) {
						// Force urlencoding of commas.
						foreach ( $editor_styles as $key => $url ) {
							if ( strpos( $url, ',' ) !== false ) {
								$editor_styles[ $key ] = str_replace( ',', '%2C', $url );
							}
						}

						$mce_css .= ',' . implode( ',', $editor_styles );
					}
				}

				/**
				 * Filters the comma-delimited list of stylesheets to load in TinyMCE.
				 *
			
				 *
				 * @param string $stylesheets Comma-delimited list of stylesheets.
				 */
				$mce_css = trim( apply_filters( 'mce_css', $mce_css ), ' ,' );

				if ( ! empty( $mce_css ) ) {
					$settings['content_css'] = $mce_css;
				} else {
					unset( $settings['content_css'] );
				}

				self::$first_init = $settings;
			}

			if ( $set['teeny'] ) {
				$mce_buttons = array(
					'bold',
					'italic',
					'underline',
					'blockquote',
					'strikethrough',
					'bullist',
					'numlist',
					'alignleft',
					'aligncenter',
					'alignright',
					'undo',
					'redo',
					'link',
					'fullscreen',
				);

				/**
				 * Filters the list of teenyMCE buttons (Text tab).
				 *
			
			
				 *
				 * @param array  $mce_buttons An array of teenyMCE buttons.
				 * @param string $editor_id   Unique editor identifier, e.g. 'content'.
				 */
				$mce_buttons   = apply_filters( 'teeny_mce_buttons', $mce_buttons, $editor_id );
				$mce_buttons_2 = array();
				$mce_buttons_3 = array();
				$mce_buttons_4 = array();
			} else {
				$mce_buttons = array(
					'formatselect',
					'bold',
					'italic',
					'bullist',
					'numlist',
					'blockquote',
					'alignleft',
					'aligncenter',
					'alignright',
					'link',
					'gc_more',
					'spellchecker',
				);

				if ( ! gc_is_mobile() ) {
					if ( $set['_content_editor_dfw'] ) {
						$mce_buttons[] = 'gc_adv';
						$mce_buttons[] = 'dfw';
					} else {
						$mce_buttons[] = 'fullscreen';
						$mce_buttons[] = 'gc_adv';
					}
				} else {
					$mce_buttons[] = 'gc_adv';
				}

				/**
				 * Filters the first-row list of TinyMCE buttons (Visual tab).
				 *
			
			
				 *
				 * @param array  $mce_buttons First-row list of buttons.
				 * @param string $editor_id   Unique editor identifier, e.g. 'content'. Accepts 'classic-block'
				 *                            when called from block editor's Classic block.
				 */
				$mce_buttons = apply_filters( 'mce_buttons', $mce_buttons, $editor_id );

				$mce_buttons_2 = array(
					'strikethrough',
					'hr',
					'forecolor',
					'pastetext',
					'removeformat',
					'charmap',
					'outdent',
					'indent',
					'undo',
					'redo',
				);

				if ( ! gc_is_mobile() ) {
					$mce_buttons_2[] = 'gc_help';
				}

				/**
				 * Filters the second-row list of TinyMCE buttons (Visual tab).
				 *
			
			
				 *
				 * @param array  $mce_buttons_2 Second-row list of buttons.
				 * @param string $editor_id     Unique editor identifier, e.g. 'content'. Accepts 'classic-block'
				 *                              when called from block editor's Classic block.
				 */
				$mce_buttons_2 = apply_filters( 'mce_buttons_2', $mce_buttons_2, $editor_id );

				/**
				 * Filters the third-row list of TinyMCE buttons (Visual tab).
				 *
			
			
				 *
				 * @param array  $mce_buttons_3 Third-row list of buttons.
				 * @param string $editor_id     Unique editor identifier, e.g. 'content'. Accepts 'classic-block'
				 *                              when called from block editor's Classic block.
				 */
				$mce_buttons_3 = apply_filters( 'mce_buttons_3', array(), $editor_id );

				/**
				 * Filters the fourth-row list of TinyMCE buttons (Visual tab).
				 *
			
			
				 *
				 * @param array  $mce_buttons_4 Fourth-row list of buttons.
				 * @param string $editor_id     Unique editor identifier, e.g. 'content'. Accepts 'classic-block'
				 *                              when called from block editor's Classic block.
				 */
				$mce_buttons_4 = apply_filters( 'mce_buttons_4', array(), $editor_id );
			}

			$body_class = $editor_id;

			$post = get_post();
			if ( $post ) {
				$body_class .= ' post-type-' . sanitize_html_class( $post->post_type ) . ' post-status-' . sanitize_html_class( $post->post_status );

				if ( post_type_supports( $post->post_type, 'post-formats' ) ) {
					$post_format = get_post_format( $post );
					if ( $post_format && ! is_gc_error( $post_format ) ) {
						$body_class .= ' post-format-' . sanitize_html_class( $post_format );
					} else {
						$body_class .= ' post-format-standard';
					}
				}

				$page_template = get_page_template_slug( $post );

				if ( false !== $page_template ) {
					$page_template = empty( $page_template ) ? 'default' : str_replace( '.', '-', basename( $page_template, '.php' ) );
					$body_class   .= ' page-template-' . sanitize_html_class( $page_template );
				}
			}

			$body_class .= ' locale-' . sanitize_html_class( strtolower( str_replace( '_', '-', get_user_locale() ) ) );

			if ( ! empty( $set['tinymce']['body_class'] ) ) {
				$body_class .= ' ' . $set['tinymce']['body_class'];
				unset( $set['tinymce']['body_class'] );
			}

			$mceInit = array(
				'selector'          => "#$editor_id",
				'gcautop'           => (bool) $set['gcautop'],
				'indent'            => ! $set['gcautop'],
				'toolbar1'          => implode( ',', $mce_buttons ),
				'toolbar2'          => implode( ',', $mce_buttons_2 ),
				'toolbar3'          => implode( ',', $mce_buttons_3 ),
				'toolbar4'          => implode( ',', $mce_buttons_4 ),
				'tabfocus_elements' => $set['tabfocus_elements'],
				'body_class'        => $body_class,
			);

			// Merge with the first part of the init array.
			$mceInit = array_merge( self::$first_init, $mceInit );

			if ( is_array( $set['tinymce'] ) ) {
				$mceInit = array_merge( $mceInit, $set['tinymce'] );
			}

			/*
			 * For people who really REALLY know what they're doing with TinyMCE
			 * You can modify $mceInit to add, remove, change elements of the config
			 * before tinyMCE.init. Setting "valid_elements", "invalid_elements"
			 * and "extended_valid_elements" can be done through this filter. Best
			 * is to use the default cleanup by not specifying valid_elements,
			 * as TinyMCE checks against the full set of HTML 5.0 elements and attributes.
			 */
			if ( $set['teeny'] ) {

				/**
				 * Filters the teenyMCE config before init.
				 *
			
			
				 *
				 * @param array  $mceInit   An array with teenyMCE config.
				 * @param string $editor_id Unique editor identifier, e.g. 'content'.
				 */
				$mceInit = apply_filters( 'teeny_mce_before_init', $mceInit, $editor_id );
			} else {

				/**
				 * Filters the TinyMCE config before init.
				 *
			
			
				 *
				 * @param array  $mceInit   An array with TinyMCE config.
				 * @param string $editor_id Unique editor identifier, e.g. 'content'. Accepts 'classic-block'
				 *                          when called from block editor's Classic block.
				 */
				$mceInit = apply_filters( 'tiny_mce_before_init', $mceInit, $editor_id );
			}

			if ( empty( $mceInit['toolbar3'] ) && ! empty( $mceInit['toolbar4'] ) ) {
				$mceInit['toolbar3'] = $mceInit['toolbar4'];
				$mceInit['toolbar4'] = '';
			}

			self::$mce_settings[ $editor_id ] = $mceInit;
		} // End if self::$this_tinymce.
	}

	/**
	 *
	 * @param array $init
	 * @return string
	 */
	private static function _parse_init( $init ) {
		$options = '';

		foreach ( $init as $key => $value ) {
			if ( is_bool( $value ) ) {
				$val      = $value ? 'true' : 'false';
				$options .= $key . ':' . $val . ',';
				continue;
			} elseif ( ! empty( $value ) && is_string( $value ) && (
				( '{' === $value[0] && '}' === $value[ strlen( $value ) - 1 ] ) ||
				( '[' === $value[0] && ']' === $value[ strlen( $value ) - 1 ] ) ||
				preg_match( '/^\(?function ?\(/', $value ) ) ) {

				$options .= $key . ':' . $value . ',';
				continue;
			}
			$options .= $key . ':"' . $value . '",';
		}

		return '{' . trim( $options, ' ,' ) . '}';
	}

	/**
	 *
	 * @param bool $default_scripts Optional. Whether default scripts should be enqueued. Default false.
	 */
	public static function enqueue_scripts( $default_scripts = false ) {
		if ( $default_scripts || self::$has_tinymce ) {
			gc_enqueue_script( 'editor' );
		}

		if ( $default_scripts || self::$has_quicktags ) {
			gc_enqueue_script( 'quicktags' );
			gc_enqueue_style( 'buttons' );
		}

		if ( $default_scripts || in_array( 'gclink', self::$plugins, true ) || in_array( 'link', self::$qt_buttons, true ) ) {
			gc_enqueue_script( 'gclink' );
			gc_enqueue_script( 'jquery-ui-autocomplete' );
		}

		if ( self::$has_medialib ) {
			add_thickbox();
			gc_enqueue_script( 'media-upload' );
			gc_enqueue_script( 'gc-embed' );
		} elseif ( $default_scripts ) {
			gc_enqueue_script( 'media-upload' );
		}

		/**
		 * Fires when scripts and styles are enqueued for the editor.
		 *
		 *
		 * @param array $to_load An array containing boolean values whether TinyMCE
		 *                       and Quicktags are being loaded.
		 */
		do_action(
			'gc_enqueue_editor',
			array(
				'tinymce'   => ( $default_scripts || self::$has_tinymce ),
				'quicktags' => ( $default_scripts || self::$has_quicktags ),
			)
		);
	}

	/**
	 * Enqueue all editor scripts.
	 * For use when the editor is going to be initialized after page load.
	 *
	 */
	public static function enqueue_default_editor() {
		// We are past the point where scripts can be enqueued properly.
		if ( did_action( 'gc_enqueue_editor' ) ) {
			return;
		}

		self::enqueue_scripts( true );

		// Also add gc-includes/css/editor.css.
		gc_enqueue_style( 'editor-buttons' );

		if ( is_admin() ) {
			add_action( 'admin_print_footer_scripts', array( __CLASS__, 'force_uncompressed_tinymce' ), 1 );
			add_action( 'admin_print_footer_scripts', array( __CLASS__, 'print_default_editor_scripts' ), 45 );
		} else {
			add_action( 'gc_print_footer_scripts', array( __CLASS__, 'force_uncompressed_tinymce' ), 1 );
			add_action( 'gc_print_footer_scripts', array( __CLASS__, 'print_default_editor_scripts' ), 45 );
		}
	}

	/**
	 * Print (output) all editor scripts and default settings.
	 * For use when the editor is going to be initialized after page load.
	 *
	 */
	public static function print_default_editor_scripts() {
		$user_can_richedit = user_can_richedit();

		if ( $user_can_richedit ) {
			$settings = self::default_settings();

			$settings['toolbar1']    = 'bold,italic,bullist,numlist,link';
			$settings['gcautop']     = false;
			$settings['indent']      = true;
			$settings['elementpath'] = false;

			if ( is_rtl() ) {
				$settings['directionality'] = 'rtl';
			}

			/*
			 * In production all plugins are loaded (they are in gc-editor.js.gz).
			 * The 'gcview', 'gcdialogs', and 'media' TinyMCE plugins are not initialized by default.
			 * Can be added from js by using the 'gc-before-tinymce-init' event.
			 */
			$settings['plugins'] = implode(
				',',
				array(
					'charmap',
					'colorpicker',
					'hr',
					'lists',
					'paste',
					'tabfocus',
					'textcolor',
					'fullscreen',
					'gechiui',
					'gcautoresize',
					'gceditimage',
					'gcemoji',
					'gcgallery',
					'gclink',
					'gctextpattern',
				)
			);

			$settings = self::_parse_init( $settings );
		} else {
			$settings = '{}';
		}

		?>
		<script type="text/javascript">
		window.gc = window.gc || {};
		window.gc.editor = window.gc.editor || {};
		window.gc.editor.getDefaultSettings = function() {
			return {
				tinymce: <?php echo $settings; ?>,
				quicktags: {
					buttons: 'strong,em,link,ul,ol,li,code'
				}
			};
		};

		<?php

		if ( $user_can_richedit ) {
			$suffix  = SCRIPT_DEBUG ? '' : '.min';
			$baseurl = self::get_baseurl();

			?>
			var tinyMCEPreInit = {
				baseURL: "<?php echo $baseurl; ?>",
				suffix: "<?php echo $suffix; ?>",
				mceInit: {},
				qtInit: {},
				load_ext: function(url,lang){var sl=tinymce.ScriptLoader;sl.markDone(url+'/langs/'+lang+'.js');sl.markDone(url+'/langs/'+lang+'_dlg.js');}
			};
			<?php
		}
		?>
		</script>
		<?php

		if ( $user_can_richedit ) {
			self::print_tinymce_scripts();
		}

		/**
		 * Fires when the editor scripts are loaded for later initialization,
		 * after all scripts and settings are printed.
		 *
		 */
		do_action( 'print_default_editor_scripts' );

		self::gc_link_dialog();
	}

	/**
	 * Returns the TinyMCE locale.
	 *
	 *
	 * @return string
	 */
	public static function get_mce_locale() {
		if ( empty( self::$mce_locale ) ) {
			$mce_locale       = get_user_locale();
			self::$mce_locale = empty( $mce_locale ) ? 'zh_CN' : strtolower( substr( $mce_locale, 0, 2 ) ); // ISO 639-1.
		}

		return self::$mce_locale;
	}

	/**
	 * Returns the TinyMCE base URL.
	 *
	 *
	 * @return string
	 */
	public static function get_baseurl() {
		if ( empty( self::$baseurl ) ) {
			self::$baseurl =  assets_url( '/vendors/tinymce' );
		}

		return self::$baseurl;
	}

	/**
	 * Returns the default TinyMCE settings.
	 * Doesn't include plugins, buttons, editor selector.
	 *
	 *
	 * @global string $tinymce_version
	 *
	 * @return array
	 */
	private static function default_settings() {
		global $tinymce_version;

		$shortcut_labels = array();

		foreach ( self::get_translation() as $name => $value ) {
			if ( is_array( $value ) ) {
				$shortcut_labels[ $name ] = $value[1];
			}
		}

		$settings = array(
			'theme'                        => 'modern',
			'skin'                         => 'lightgray',
			'language'                     => self::get_mce_locale(),
			'formats'                      => '{' .
				'alignleft: [' .
					'{selector: "p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li", styles: {textAlign:"left"}},' .
					'{selector: "img,table,dl.gc-caption", classes: "alignleft"}' .
				'],' .
				'aligncenter: [' .
					'{selector: "p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li", styles: {textAlign:"center"}},' .
					'{selector: "img,table,dl.gc-caption", classes: "aligncenter"}' .
				'],' .
				'alignright: [' .
					'{selector: "p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li", styles: {textAlign:"right"}},' .
					'{selector: "img,table,dl.gc-caption", classes: "alignright"}' .
				'],' .
				'strikethrough: {inline: "del"}' .
			'}',
			'relative_urls'                => false,
			'remove_script_host'           => false,
			'convert_urls'                 => false,
			'browser_spellcheck'           => true,
			'fix_list_elements'            => true,
			'entities'                     => '38,amp,60,lt,62,gt',
			'entity_encoding'              => 'raw',
			'keep_styles'                  => false,
			'cache_suffix'                 => 'gc-mce-' . $tinymce_version,
			'resize'                       => 'vertical',
			'menubar'                      => false,
			'branding'                     => false,

			// Limit the preview styles in the menu/toolbar.
			'preview_styles'               => 'font-family font-size font-weight font-style text-decoration text-transform',

			'end_container_on_empty_block' => true,
			'gceditimage_html5_captions'   => true,
			'gc_lang_attr'                 => get_bloginfo( 'language' ),
			'gc_keep_scroll_position'      => false,
			'gc_shortcut_labels'           => gc_json_encode( $shortcut_labels ),
		);

		$suffix  = SCRIPT_DEBUG ? '' : '.min';
		$version = 'ver=' . get_bloginfo( 'version' );

		// Default stylesheets.
		$settings['content_css'] = assets_url( "/css/dashicons$suffix.css?$version" ) . ',' .
		assets_url( "/vendors/tinymce/skins/gechiui/gc-content.css?$version" );

		return $settings;
	}

	/**
	 *
	 * @return array
	 */
	private static function get_translation() {
		if ( empty( self::$translation ) ) {
			self::$translation = array(
				// Default TinyMCE strings.
				'?????????'                         => __( '?????????' ),
				'Formats'                              => _x( '??????', 'TinyMCE' ),

				'Headings'                             => _x( '??????', 'TinyMCE' ),
				'????????????'                            => array( __( '????????????' ), 'access1' ),
				'????????????'                            => array( __( '????????????' ), 'access2' ),
				'????????????'                            => array( __( '????????????' ), 'access3' ),
				'????????????'                            => array( __( '????????????' ), 'access4' ),
				'????????????'                            => array( __( '????????????' ), 'access5' ),
				'????????????'                            => array( __( '????????????' ), 'access6' ),

				/* translators: Block tags. */
				'Blocks'                               => _x( '???', 'TinyMCE' ),
				'??????'                            => array( __( '??????' ), 'access7' ),
				'????????????'                           => array( __( '????????????' ), 'accessQ' ),
				'Div'                                  => _x( 'Div', 'HTML tag' ),
				'Pre'                                  => _x( 'Pre', 'HTML tag' ),
				'?????????'                         => _x( '?????????', 'HTML tag' ),
				'Address'                              => _x( '??????', 'HTML tag' ),

				'Inline'                               => _x( '??????', 'HTML elements' ),
				'?????????'                            => array( __( '?????????' ), 'metaU' ),
				'?????????'                        => array( __( '?????????' ), 'accessD' ),
				'??????'                            => __( '??????' ),
				'??????'                          => __( '??????' ),
				'????????????'                     => __( '????????????' ),
				'Bold'                                 => array( __( '??????' ), 'metaB' ),
				'Italic'                               => array( __( '??????' ), 'metaI' ),
				'Code'                                 => array( __( '??????' ), 'accessX' ),
				'?????????'                          => __( '?????????' ),
				'??????'                          => __( '??????' ),
				'??????'                           => __( '??????' ),

				'????????????'                         => array( __( '????????????' ), 'accessC' ),
				'?????????'                          => array( __( '?????????' ), 'accessR' ),
				'?????????'                           => array( __( '?????????' ), 'accessL' ),
				'Justify'                              => array( __( '????????????' ), 'accessJ' ),
				'???????????????'                      => __( '???????????????' ),
				'???????????????'                      => __( '???????????????' ),

				'Cut'                                  => array( __( '??????' ), 'metaX' ),
				'Copy'                                 => array( __( '??????' ), 'metaC' ),
				'Paste'                                => array( __( '??????' ), 'metaV' ),
				'??????'                           => array( __( '??????' ), 'metaA' ),
				'Undo'                                 => array( __( '??????' ), 'metaZ' ),
				'Redo'                                 => array( __( '??????' ), 'metaY' ),

				'Ok'                                   => __( '??????' ),
				'Cancel'                               => __( '??????' ),
				'Close'                                => __( '??????' ),
				'????????????'                          => __( '????????????' ),

				'Bullet list'                          => array( __( '??????????????????' ), 'accessU' ),
				'????????????'                        => array( __( '????????????' ), 'accessO' ),
				'Square'                               => _x( '????????????', 'list style' ),
				'Default'                              => _x( '??????', 'list style' ),
				'Circle'                               => _x( '??????', 'list style' ),
				'Disc'                                 => _x( '??????', 'list style' ),
				'??????????????????'                          => _x( '??????????????????', 'list style' ),
				'??????????????????'                          => _x( '??????????????????', 'list style' ),
				'??????????????????'                          => _x( '??????????????????', 'list style' ),
				'??????????????????'                          => _x( '??????????????????', 'list style' ),
				'??????????????????'                          => _x( '??????????????????', 'list style' ),

				// Anchor plugin.
				'Name'                                 => _x( '??????', 'Name of link anchor (TinyMCE)' ),
				'Anchor'                               => _x( '???', 'Link anchor (TinyMCE)' ),
				'Anchors'                              => _x( '???', 'Link anchors (TinyMCE)' ),
				'Id?????????????????????????????????????????????????????????????????????????????????????????????' =>
					__( 'Id?????????????????????????????????????????????????????????????????????????????????????????????' ),
				'Id'                                   => _x( 'Id', 'Id for link anchor (TinyMCE)' ),

				// Fullpage plugin.
				'????????????'                  => __( '????????????' ),
				'Robots'                               => __( '?????????' ),
				'Title'                                => __( '??????' ),
				'Keywords'                             => __( '?????????' ),
				'Encoding'                             => __( '??????' ),
				'description'                          => __( '??????' ),
				'Author'                               => __( '??????' ),

				// Media, image plugins.
				'Image'                                => __( '??????' ),
				'?????????????????????'                    => array( __( '?????????????????????' ), 'accessM' ),
				'General'                              => __( '??????' ),
				'Advanced'                             => __( '??????' ),
				'Source'                               => __( '???' ),
				'Border'                               => __( '??????' ),
				'???????????????'                => __( '???????????????' ),
				'????????????'                       => __( '????????????' ),
				'????????????'                    => __( '????????????' ),
				'Style'                                => __( '??????' ),
				'??????'                           => __( '??????' ),
				'????????????'                         => __( '????????????' ),
				'??????/??????'                            => __( '??????/??????' ),
				'?????????????????????'                     => __( '?????????????????????' ),
				'??????'                    => __( '??????' ),
				'Insert/Edit code sample'              => __( '??????/??????????????????' ),
				'Language'                             => __( '??????' ),
				'Media'                                => __( '??????' ),
				'??????/????????????'                    => __( '??????/????????????' ),
				'Poster'                               => __( '??????' ),
				'?????????'                   => __( '?????????' ),
				'?????????????????????????????????'         => __( '?????????????????????????????????' ),
				'????????????'                         => __( '????????????' ),
				'Embed'                                => __( '??????' ),

				// Each of these have a corresponding plugin.
				'????????????'                    => __( '????????????' ),
				'????????????'                        => _x( '????????????', 'editor button' ),
				'????????????'                        => _x( '????????????', 'editor button' ),
				'????????????'                            => __( '????????????' ),
				'???????????????'                    => __( '???????????????' ),
				'?????????'                           => __( '?????????' ),
				'???????????????'                        => __( '???????????????' ),
				'Preview'                              => __( '??????' ),
				'Print'                                => __( '??????' ),
				'Save'                                 => __( '??????' ),
				'??????'                           => __( '??????' ),
				'?????????'                      => __( '?????????' ),
				'????????????'                     => __( '????????????' ),
				'??????????????????'                   => __( '??????????????????' ),
				'?????????????????????'                     => array( __( '?????????????????????' ), 'metaK' ),
				'????????????'                          => array( __( '????????????' ), 'accessS' ),

				// Link plugin.
				'Link'                                 => __( '??????' ),
				'????????????'                          => __( '????????????' ),
				'Target'                               => __( '????????????' ),
				'?????????'                           => __( '?????????' ),
				'????????????'                      => __( '????????????' ),
				'Url'                                  => __( 'URL' ),
				'???????????????????????????????????????????????????????????????mailto:???????????????' =>
					__( '???????????????????????????????????????????????????????????????mailto:???????????????' ),
				'???????????????????????????????????????????????????????????????http://???????????????' =>
					__( '???????????????????????????????????????????????????????????????http://???????????????' ),

				'Color'                                => __( '??????' ),
				'???????????????'                         => __( '???????????????' ),
				'????????????'                            => _x( '????????????', 'label for custom color' ), // No ellipsis.
				'No color'                             => __( '?????????' ),
				'R'                                    => _x( 'R', 'Short for red in RGB' ),
				'G'                                    => _x( 'G', 'Short for green in RGB' ),
				'B'                                    => _x( 'B', 'Short for blue in RGB' ),

				// Spelling, search/replace plugins.
				'?????????????????????????????????' => __( '?????????????????????????????????' ),
				'Replace'                              => _x( '??????', 'find/replace' ),
				'Next'                                 => _x( '?????????', 'find/replace' ),
				/* translators: Previous. */
				'Prev'                                 => _x( '?????????', 'find/replace' ),
				'????????????'                          => _x( '????????????', 'find/replace' ),
				'???????????????'                     => __( '???????????????' ),
				'?????????'                         => _x( '?????????', 'find/replace' ),
				'Find'                                 => _x( '??????', 'find/replace' ),
				'????????????'                          => _x( '????????????', 'find/replace' ),
				'???????????????'                           => __( '???????????????' ),
				'Spellcheck'                           => __( '????????????' ),
				'Finish'                               => _x( '??????', 'spellcheck' ),
				'????????????'                           => _x( '????????????', 'spellcheck' ),
				'Ignore'                               => _x( '??????', 'spellcheck' ),
				'???????????????'                    => __( '???????????????' ),

				// TinyMCE tables.
				'????????????'                         => __( '????????????' ),
				'????????????'                         => __( '????????????' ),
				'????????????'                     => __( '????????????' ),
				'Row properties'                       => __( '???????????????' ),
				'Cell properties'                      => __( '???????????????' ),
				'????????????'                         => __( '????????????' ),

				'Row'                                  => __( '???' ),
				'Rows'                                 => __( '???' ),
				'Column'                               => __( '????????????' ),
				'Cols'                                 => __( '??????' ),
				'Cell'                                 => _x( '?????????', 'table cell' ),
				'???????????????'                          => __( '???????????????' ),
				'Header'                               => _x( '??????', 'table header' ),
				'Body'                                 => _x( '??????', 'table body' ),
				'Footer'                               => _x( '??????', 'table footer' ),

				'??????????????????'                    => __( '??????????????????' ),
				'??????????????????'                     => __( '??????????????????' ),
				'??????????????????'                 => __( '??????????????????' ),
				'??????????????????'                  => __( '??????????????????' ),
				'Paste row before'                     => __( '????????????????????????' ),
				'Paste row after'                      => __( '????????????????????????' ),
				'?????????'                           => __( '?????????' ),
				'?????????'                        => __( '?????????' ),
				'Cut row'                              => __( '????????????' ),
				'Copy row'                             => __( '????????????' ),
				'Merge cells'                          => __( '???????????????' ),
				'Split cell'                           => __( '???????????????' ),

				'Height'                               => __( '??????' ),
				'Width'                                => __( '??????' ),
				'Caption'                              => __( '????????????' ),
				'????????????'                            => __( '????????????' ),
				'H Align'                              => _x( '????????????', 'horizontal table cell alignment' ),
				'Left'                                 => __( '???' ),
				'Center'                               => __( '???' ),
				'Right'                                => __( '???' ),
				'None'                                 => _x( '???', 'table cell alignment attribute' ),
				'V Align'                              => _x( '????????????', 'vertical table cell alignment' ),
				'Top'                                  => __( '??????' ),
				'Middle'                               => __( '??????' ),
				'Bottom'                               => __( '??????' ),

				'??????'                            => __( '??????' ),
				'????????????'                         => __( '????????????' ),
				'Row type'                             => __( '?????????' ),
				'???????????????'                            => __( '???????????????' ),
				'??????????????????'                         => __( '??????????????????' ),
				'???????????????'                         => __( '???????????????' ),
				'Scope'                                => _x( '??????', 'table cell scope attribute' ),

				'????????????'                      => _x( '????????????', 'TinyMCE' ),
				'??????'                            => _x( '??????', 'TinyMCE' ),

				'????????????'                     => __( '????????????' ),
				'????????????'                           => __( '????????????' ),
				'?????????'                          => _x( '?????????', 'editor button' ),
				'?????????????????????'            => __( '?????????????????????' ),

				/* translators: Word count. */
				'Words: {0}'                           => sprintf( __( '?????????%s' ), '{0}' ),
				'???????????????????????????????????????????????????????????????????????????' =>
					__( '???????????????????????????????????????????????????????????????????????????' ) . "\n\n" .
					__( '??????????????????Microsoft Word???????????????????????????????????????????????????????????????????????????Word?????????????????????' ),
				'Rich Text Area. Press ALT-F9 for menu. Press ALT-F10 for toolbar. Press ALT-0 for help' =>
					__( '?????????????????????Alt-Shift-H???????????????' ),
				'?????????????????????Control-Option-H???????????????' => __( '?????????????????????Control-Option-H???????????????' ),
				'You have unsaved changes are you sure you want to navigate away?' =>
					__( '???????????????????????????????????????????????????' ),
				'Your browser doesn\'t support direct access to the clipboard. Please use the Ctrl+X/C/V keyboard shortcuts instead.' =>
					__( '??????????????????????????????????????????????????????????????????????????????????????????????????????' ),

				// TinyMCE menus.
				'Insert'                               => _x( '??????', 'TinyMCE menu' ),
				'File'                                 => _x( '??????', 'TinyMCE menu' ),
				'Edit'                                 => _x( '??????', 'TinyMCE menu' ),
				'Tools'                                => _x( '??????', 'TinyMCE menu' ),
				'View'                                 => _x( '??????', 'TinyMCE menu' ),
				'Table'                                => _x( '??????', 'TinyMCE menu' ),
				'Format'                               => _x( '??????', 'TinyMCE menu' ),

				// GeChiUI strings.
				'??????/???????????????'                       => array( __( '??????/???????????????' ), 'accessZ' ),
				'?????????More?????????'                 => array( __( '?????????More?????????' ), 'accessT' ),
				'??????????????????'                => array( __( '??????????????????' ), 'accessP' ),
				'???????????????'                         => __( '???????????????' ), // Title on the placeholder inside the editor (no ellipsis).
				'?????????????????????'        => array( __( '?????????????????????' ), 'accessW' ),
				'?????????'                         => __( '?????????' ), // Tooltip for the 'alignnone' button in the image toolbar.
				'Remove'                               => __( '??????' ),       // Tooltip for the 'remove' button in the image toolbar.
				'Edit|button'                          => __( '??????' ),         // Tooltip for the 'edit' button in the image toolbar.
				'??????URL??????????????????'          => __( '??????URL??????????????????' ), // Placeholder for the inline link dialog.
				'Apply'                                => __( '??????' ),        // Tooltip for the 'apply' button in the inline link dialog.
				'????????????'                         => __( '????????????' ), // Tooltip for the 'link options' button in the inline link dialog.
				'Visual'                               => _x( '?????????', 'Name for the Visual editor tab' ),             // Editor switch tab label.
				'Text'                                 => _x( '??????', 'Name for the Text editor tab (formerly HTML)' ), // Editor switch tab label.
				'????????????'                            => array( __( '????????????' ), 'accessM' ), // Tooltip for the '????????????' button in the block editor Classic block.

				// Shortcuts help modal.
				'???????????????'                   => array( __( '???????????????' ), 'accessH' ),
				'???????????????????????????'     => __( '???????????????????????????' ),
				'?????????????????????'                   => __( '?????????????????????' ),
				'????????????????????????'                => __( '????????????????????????' ),
				'?????????????????????'                     => __( '?????????????????????' ),
				'????????????????????????????????????????????????????????????' => __( '????????????????????????????????????????????????????????????' ),
				'??????????????????????????????'           => __( '??????????????????????????????' ),
				'???????????????'                       => __( '???????????????' ),
				'????????????'                        => __( '????????????' ),
				'Ctrl+Alt+?????????'                 => __( 'Ctrl+Alt+?????????' ),
				'Shift+Alt+?????????'                => __( 'Shift+Alt+?????????' ),
				'Cmd+?????????'                        => __( 'Cmd+?????????' ),
				'Ctrl+?????????'                       => __( 'Ctrl+?????????' ),
				'Letter'                               => __( '??????' ),
				'Action'                               => __( '??????' ),
				'??????????????????????????????????????????????????????????????????' => __( '??????????????????????????????????????????????????????????????????' ),
				'??????????????????????????????????????????Tab???????????????????????????????????????????????????Esc??????????????????????????????' =>
					__( '??????????????????????????????????????????Tab???????????????????????????????????????????????????Esc??????????????????????????????' ),
				'????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????' =>
					__( '????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????' ),
				'????????????????????????????????????????????????????????????????????????????????????' =>
					__( '????????????????????????????????????????????????????????????????????????????????????' ),
				'??????????????????????????????????????????????????????????????????????????????????????????????????????????????????Esc???????????????????????????' =>
					__( '??????????????????????????????????????????????????????????????????????????????????????????????????????????????????Esc???????????????????????????' ),
			);
		}

		/*
		Imagetools plugin (not included):
			'????????????' => __( '????????????' ),
			'????????????' => __( '????????????' ),
			'Back' => __( '??????' ),
			'Invert' => __( 'Invert' ),
			'Flip horizontally' => __( '????????????' ),
			'Flip vertically' => __( '????????????' ),
			'Crop' => __( '??????' ),
			'Orientation' => __( '??????' ),
			'Resize' => __( 'Resize' ),
			'Rotate clockwise' => __( '?????????' ),
			'Rotate counterclockwise' => __( '?????????' ),
			'Sharpen' => __( 'Sharpen' ),
			'Brightness' => __( '??????' ),
			'Color levels' => __( 'Color levels' ),
			'Contrast' => __( 'Contrast' ),
			'Gamma' => __( 'Gamma' ),
			'??????'  => __( '??????'  ),
			'??????'  => __( '??????'  ),
		*/

		return self::$translation;
	}

	/**
	 * Translates the default TinyMCE strings and returns them as JSON encoded object ready to be loaded with tinymce.addI18n(),
	 * or as JS snippet that should run after tinymce.js is loaded.
	 *
	 *
	 * @param string $mce_locale The locale used for the editor.
	 * @param bool   $json_only  Optional. Whether to include the JavaScript calls to tinymce.addI18n() and
	 *                           tinymce.ScriptLoader.markDone().
	 * @return string Translation object, JSON encoded.
	 */
	public static function gc_mce_translation( $mce_locale = '', $json_only = false ) {
		if ( ! $mce_locale ) {
			$mce_locale = self::get_mce_locale();
		}

		$mce_translation = self::get_translation();

		foreach ( $mce_translation as $name => $value ) {
			if ( is_array( $value ) ) {
				$mce_translation[ $name ] = $value[0];
			}
		}

		/**
		 * Filters translated strings prepared for TinyMCE.
		 *
		 *
		 * @param array  $mce_translation Key/value pairs of strings.
		 * @param string $mce_locale      Locale.
		 */
		$mce_translation = apply_filters( 'gc_mce_translation', $mce_translation, $mce_locale );

		foreach ( $mce_translation as $key => $value ) {
			// Remove strings that are not translated.
			if ( $key === $value ) {
				unset( $mce_translation[ $key ] );
				continue;
			}

			if ( false !== strpos( $value, '&' ) ) {
				$mce_translation[ $key ] = html_entity_decode( $value, ENT_QUOTES, 'UTF-8' );
			}
		}

		// Set direction.
		if ( is_rtl() ) {
			$mce_translation['_dir'] = 'rtl';
		}

		if ( $json_only ) {
			return gc_json_encode( $mce_translation );
		}

		$baseurl = self::get_baseurl();

		return "tinymce.addI18n( '$mce_locale', " . gc_json_encode( $mce_translation ) . ");\n" .
			"tinymce.ScriptLoader.markDone( '$baseurl/langs/$mce_locale.js' );\n";
	}

	/**
	 * Force uncompressed TinyMCE when a custom theme has been defined.
	 *
	 * The compressed TinyMCE file cannot deal with custom themes, so this makes
	 * sure that we use the uncompressed TinyMCE file if a theme is defined.
	 * Even if we are on a production environment.
	 *
	 */
	public static function force_uncompressed_tinymce() {
		$has_custom_theme = false;
		foreach ( self::$mce_settings as $init ) {
			if ( ! empty( $init['theme_url'] ) ) {
				$has_custom_theme = true;
				break;
			}
		}

		if ( ! $has_custom_theme ) {
			return;
		}

		$gc_scripts = gc_scripts();

		$gc_scripts->remove( 'gc-tinymce' );
		gc_register_tinymce_scripts( $gc_scripts, true );
	}

	/**
	 * Print (output) the main TinyMCE scripts.
	 *
	 *
	 * @global bool $concatenate_scripts
	 */
	public static function print_tinymce_scripts() {
		global $concatenate_scripts;

		if ( self::$tinymce_scripts_printed ) {
			return;
		}

		self::$tinymce_scripts_printed = true;

		if ( ! isset( $concatenate_scripts ) ) {
			script_concat_settings();
		}

		gc_print_scripts( array( 'gc-tinymce' ) );

		echo "<script type='text/javascript'>\n" . self::gc_mce_translation() . "</script>\n";
	}

	/**
	 * Print (output) the TinyMCE configuration and initialization scripts.
	 *
	 *
	 * @global string $tinymce_version
	 */
	public static function editor_js() {
		global $tinymce_version;

		$tmce_on = ! empty( self::$mce_settings );
		$mceInit = '';
		$qtInit  = '';

		if ( $tmce_on ) {
			foreach ( self::$mce_settings as $editor_id => $init ) {
				$options  = self::_parse_init( $init );
				$mceInit .= "'$editor_id':{$options},";
			}
			$mceInit = '{' . trim( $mceInit, ',' ) . '}';
		} else {
			$mceInit = '{}';
		}

		if ( ! empty( self::$qt_settings ) ) {
			foreach ( self::$qt_settings as $editor_id => $init ) {
				$options = self::_parse_init( $init );
				$qtInit .= "'$editor_id':{$options},";
			}
			$qtInit = '{' . trim( $qtInit, ',' ) . '}';
		} else {
			$qtInit = '{}';
		}

		$ref = array(
			'plugins'  => implode( ',', self::$plugins ),
			'theme'    => 'modern',
			'language' => self::$mce_locale,
		);

		$suffix  = SCRIPT_DEBUG ? '' : '.min';
		$baseurl = self::get_baseurl();
		$version = 'ver=' . $tinymce_version;

		/**
		 * Fires immediately before the TinyMCE settings are printed.
		 *
		 *
		 * @param array $mce_settings TinyMCE settings array.
		 */
		do_action( 'before_gc_tiny_mce', self::$mce_settings );
		?>

		<script type="text/javascript">
		tinyMCEPreInit = {
			baseURL: "<?php echo $baseurl; ?>",
			suffix: "<?php echo $suffix; ?>",
			<?php

			if ( self::$drag_drop_upload ) {
				echo 'dragDropUpload: true,';
			}

			?>
			mceInit: <?php echo $mceInit; ?>,
			qtInit: <?php echo $qtInit; ?>,
			ref: <?php echo self::_parse_init( $ref ); ?>,
			load_ext: function(url,lang){var sl=tinymce.ScriptLoader;sl.markDone(url+'/langs/'+lang+'.js');sl.markDone(url+'/langs/'+lang+'_dlg.js');}
		};
		</script>
		<?php

		if ( $tmce_on ) {
			self::print_tinymce_scripts();

			if ( self::$ext_plugins ) {
				// Load the old-format English strings to prevent unsightly labels in old style popups.
				echo "<script type='text/javascript' src='{$baseurl}/langs/gc-langs-en.js?$version'></script>\n";
			}
		}

		/**
		 * Fires after tinymce.js is loaded, but before any TinyMCE editor
		 * instances are created.
		 *
		 *
		 * @param array $mce_settings TinyMCE settings array.
		 */
		do_action( 'gc_tiny_mce_init', self::$mce_settings );

		?>
		<script type="text/javascript">
		<?php

		if ( self::$ext_plugins ) {
			echo self::$ext_plugins . "\n";
		}

		if ( ! is_admin() ) {
			echo 'var ajaxurl = "' . admin_url( 'admin-ajax.php', 'relative' ) . '";';
		}

		?>

		( function() {
			var initialized = [];
			var initialize  = function() {
				var init, id, inPostbox, $wrap;
				var readyState = document.readyState;

				if ( readyState !== 'complete' && readyState !== 'interactive' ) {
					return;
				}

				for ( id in tinyMCEPreInit.mceInit ) {
					if ( initialized.indexOf( id ) > -1 ) {
						continue;
					}

					init      = tinyMCEPreInit.mceInit[id];
					$wrap     = tinymce.$( '#gc-' + id + '-wrap' );
					inPostbox = $wrap.parents( '.postbox' ).length > 0;

					if (
						! init.gc_skip_init &&
						( $wrap.hasClass( 'tmce-active' ) || ! tinyMCEPreInit.qtInit.hasOwnProperty( id ) ) &&
						( readyState === 'complete' || ( ! inPostbox && readyState === 'interactive' ) )
					) {
						tinymce.init( init );
						initialized.push( id );

						if ( ! window.gcActiveEditor ) {
							window.gcActiveEditor = id;
						}
					}
				}
			}

			if ( typeof tinymce !== 'undefined' ) {
				if ( tinymce.Env.ie && tinymce.Env.ie < 11 ) {
					tinymce.$( '.gc-editor-wrap ' ).removeClass( 'tmce-active' ).addClass( 'html-active' );
				} else {
					if ( document.readyState === 'complete' ) {
						initialize();
					} else {
						document.addEventListener( 'readystatechange', initialize );
					}
				}
			}

			if ( typeof quicktags !== 'undefined' ) {
				for ( id in tinyMCEPreInit.qtInit ) {
					quicktags( tinyMCEPreInit.qtInit[id] );

					if ( ! window.gcActiveEditor ) {
						window.gcActiveEditor = id;
					}
				}
			}
		}());
		</script>
		<?php

		if ( in_array( 'gclink', self::$plugins, true ) || in_array( 'link', self::$qt_buttons, true ) ) {
			self::gc_link_dialog();
		}

		/**
		 * Fires after any core TinyMCE editor instances are created.
		 *
		 *
		 * @param array $mce_settings TinyMCE settings array.
		 */
		do_action( 'after_gc_tiny_mce', self::$mce_settings );
	}

	/**
	 * Outputs the HTML for distraction-free writing mode.
	 *
	 * @deprecated 4.3.0
	 */
	public static function gc_fullscreen_html() {
		_deprecated_function( __FUNCTION__, '4.3.0' );
	}

	/**
	 * Performs post queries for internal linking.
	 *
	 *
	 * @param array $args Optional. Accepts 'pagenum' and 's' (search) arguments.
	 * @return array|false Results.
	 */
	public static function gc_link_query( $args = array() ) {
		$pts      = get_post_types( array( 'public' => true ), 'objects' );
		$pt_names = array_keys( $pts );

		$query = array(
			'post_type'              => $pt_names,
			'suppress_filters'       => true,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
			'post_status'            => 'publish',
			'posts_per_page'         => 20,
		);

		$args['pagenum'] = isset( $args['pagenum'] ) ? absint( $args['pagenum'] ) : 1;

		if ( isset( $args['s'] ) ) {
			$query['s'] = $args['s'];
		}

		$query['offset'] = $args['pagenum'] > 1 ? $query['posts_per_page'] * ( $args['pagenum'] - 1 ) : 0;

		/**
		 * Filters the link query arguments.
		 *
		 * Allows modification of the link query arguments before querying.
		 *
		 * @see GC_Query for a full list of arguments
		 *
		 *
		 * @param array $query An array of GC_Query arguments.
		 */
		$query = apply_filters( 'gc_link_query_args', $query );

		// Do main query.
		$get_posts = new GC_Query;
		$posts     = $get_posts->query( $query );

		// Build results.
		$results = array();
		foreach ( $posts as $post ) {
			if ( 'post' === $post->post_type ) {
				$info = mysql2date( __( 'Y-m-d' ), $post->post_date );
			} else {
				$info = $pts[ $post->post_type ]->labels->singular_name;
			}

			$results[] = array(
				'ID'        => $post->ID,
				'title'     => trim( esc_html( strip_tags( get_the_title( $post ) ) ) ),
				'permalink' => get_permalink( $post->ID ),
				'info'      => $info,
			);
		}

		/**
		 * Filters the link query results.
		 *
		 * Allows modification of the returned link query results.
		 *
		 *
		 * @see 'gc_link_query_args' filter
		 *
		 * @param array $results {
		 *     An array of associative arrays of query results.
		 *
		 *     @type array ...$0 {
		 *         @type int    $ID        Post ID.
		 *         @type string $title     The trimmed, escaped post title.
		 *         @type string $permalink Post permalink.
		 *         @type string $info      A 'Y/m/d'-formatted date for 'post' post type,
		 *                                 the 'singular_name' post type label otherwise.
		 *     }
		 * }
		 * @param array $query  An array of GC_Query arguments.
		 */
		$results = apply_filters( 'gc_link_query', $results, $query );

		return ! empty( $results ) ? $results : false;
	}

	/**
	 * Dialog for internal linking.
	 *
	 */
	public static function gc_link_dialog() {
		// Run once.
		if ( self::$link_dialog_printed ) {
			return;
		}

		self::$link_dialog_printed = true;

		// `display: none` is required here, see #GC27605.
		?>
		<div id="gc-link-backdrop" style="display: none"></div>
		<div id="gc-link-wrap" class="gc-core-ui" style="display: none" role="dialog" aria-labelledby="link-modal-title">
		<form id="gc-link" tabindex="-1">
		<?php gc_nonce_field( 'internal-linking', '_ajax_linking_nonce', false ); ?>
		<h1 id="link-modal-title"><?php _e( '?????????????????????' ); ?></h1>
		<button type="button" id="gc-link-close"><span class="screen-reader-text"><?php _e( '??????' ); ?></span></button>
		<div id="link-selector">
			<div id="link-options">
				<p class="howto" id="gclink-enter-url"><?php _e( '????????????URL' ); ?></p>
				<div>
					<label><span><?php _e( 'URL' ); ?></span>
					<input id="gc-link-url" type="text" aria-describedby="gclink-enter-url" /></label>
				</div>
				<div class="gc-link-text-field">
					<label><span><?php _e( '????????????' ); ?></span>
					<input id="gc-link-text" type="text" /></label>
				</div>
				<div class="link-target">
					<label><span></span>
					<input type="checkbox" id="gc-link-target" /> <?php _e( '??????????????????????????????' ); ?></label>
				</div>
			</div>
			<p class="howto" id="gclink-link-existing-content"><?php _e( '??????????????????????????????' ); ?></p>
			<div id="search-panel">
				<div class="link-search-wrapper">
					<label>
						<span class="search-label"><?php _e( '??????' ); ?></span>
						<input type="search" id="gc-link-search" class="link-search-field" autocomplete="off" aria-describedby="gclink-link-existing-content" />
						<span class="spinner"></span>
					</label>
				</div>
				<div id="search-results" class="query-results" tabindex="0">
					<ul></ul>
					<div class="river-waiting">
						<span class="spinner"></span>
					</div>
				</div>
				<div id="most-recent-results" class="query-results" tabindex="0">
					<div class="query-notice" id="query-notice-message">
						<em class="query-notice-default"><?php _e( '?????????????????????????????????????????????????????????' ); ?></em>
						<em class="query-notice-hint screen-reader-text"><?php _e( '????????????????????????????????????????????????' ); ?></em>
					</div>
					<ul></ul>
					<div class="river-waiting">
						<span class="spinner"></span>
					</div>
				</div>
			</div>
		</div>
		<div class="submitbox">
			<div id="gc-link-cancel">
				<button type="button" class="button"><?php _e( '??????' ); ?></button>
			</div>
			<div id="gc-link-update">
				<input type="submit" value="<?php esc_attr_e( '????????????' ); ?>" class="button button-primary" id="gc-link-submit" name="gc-link-submit">
			</div>
		</div>
		</form>
		</div>
		<?php
	}
}
