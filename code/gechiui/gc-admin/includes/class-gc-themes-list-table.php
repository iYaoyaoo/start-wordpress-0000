<?php
/**
 * List Table API: GC_Themes_List_Table class
 *
 * @package GeChiUI
 * @subpackage Administration
 *
 */

/**
 * Core class used to implement displaying installed themes in a list table.
 *
 *
 * @access private
 *
 * @see GC_List_Table
 */
class GC_Themes_List_Table extends GC_List_Table {

	protected $search_terms = array();
	public $features        = array();

	/**
	 * Constructor.
	 *
	 *
	 * @see GC_List_Table::__construct() for more information on default arguments.
	 *
	 * @param array $args An associative array of arguments.
	 */
	public function __construct( $args = array() ) {
		parent::__construct(
			array(
				'ajax'   => true,
				'screen' => isset( $args['screen'] ) ? $args['screen'] : null,
			)
		);
	}

	/**
	 * @return bool
	 */
	public function ajax_user_can() {
		// Do not check edit_theme_options here. Ajax calls for available themes require switch_themes.
		return current_user_can( 'switch_themes' );
	}

	/**
	 */
	public function prepare_items() {
		$themes = gc_get_themes( array( 'allowed' => true ) );

		if ( ! empty( $_REQUEST['s'] ) ) {
			$this->search_terms = array_unique( array_filter( array_map( 'trim', explode( ',', strtolower( gc_unslash( $_REQUEST['s'] ) ) ) ) ) );
		}

		if ( ! empty( $_REQUEST['features'] ) ) {
			$this->features = $_REQUEST['features'];
		}

		if ( $this->search_terms || $this->features ) {
			foreach ( $themes as $key => $theme ) {
				if ( ! $this->search_theme( $theme ) ) {
					unset( $themes[ $key ] );
				}
			}
		}

		unset( $themes[ get_option( 'stylesheet' ) ] );
		GC_Theme::sort_by_name( $themes );

		$per_page = 36;
		$page     = $this->get_pagenum();

		$start = ( $page - 1 ) * $per_page;

		$this->items = array_slice( $themes, $start, $per_page, true );

		$this->set_pagination_args(
			array(
				'total_items'     => count( $themes ),
				'per_page'        => $per_page,
				'infinite_scroll' => true,
			)
		);
	}

	/**
	 */
	public function no_items() {
		if ( $this->search_terms || $this->features ) {
			_e( '找不到条目。' );
			return;
		}

		$blog_id = get_current_blog_id();
		if ( is_multisite() ) {
			if ( current_user_can( 'install_themes' ) && current_user_can( 'manage_network_themes' ) ) {
				printf(
					/* translators: 1: URL to Themes tab on Edit Site screen, 2: URL to Add Themes screen. */
					__( '您目前只安装了一个主题。您可访问“管理网络”页面来<a href="%1$s">启用</a>或<a href="%2$s">安装</a>更多主题。' ),
					network_admin_url( 'site-themes.php?id=' . $blog_id ),
					network_admin_url( 'theme-install.php' )
				);

				return;
			} elseif ( current_user_can( 'manage_network_themes' ) ) {
				printf(
					/* translators: %s: URL to Themes tab on Edit Site screen. */
					__( '您目前只安装了一个主题。您可访问“管理网络”页面来<a href="%s">启用</a>更多主题。' ),
					network_admin_url( 'site-themes.php?id=' . $blog_id )
				);

				return;
			}
			// Else, fallthrough. install_themes doesn't help if you can't enable it.
		} else {
			if ( current_user_can( 'install_themes' ) ) {
				printf(
					/* translators: %s: URL to Add Themes screen. */
					__( '您目前只安装了一个主题，太少了吧！您可随时从www.GeChiUI.com主题目录中的上千种免费主题中选择：只需点击上方的<a href="%s">安装主题</a>标签。' ),
					admin_url( 'theme-install.php' )
				);

				return;
			}
		}
		// Fallthrough.
		printf(
			/* translators: %s: Network title. */
			__( '只有活动主题对您可用。有关访问其他主题的信息，请与%s管理员联系。' ),
			get_site_option( 'site_name' )
		);
	}

	/**
	 * @param string $which
	 */
	public function tablenav( $which = 'top' ) {
		if ( $this->get_pagination_arg( 'total_pages' ) <= 1 ) {
			return;
		}
		?>
		<div class="tablenav themes <?php echo $which; ?>">
			<?php $this->pagination( $which ); ?>
			<span class="spinner"></span>
			<br class="clear" />
		</div>
		<?php
	}

	/**
	 * Displays the themes table.
	 *
	 * Overrides the parent display() method to provide a different container.
	 *
	 */
	public function display() {
		gc_nonce_field( 'fetch-list-' . get_class( $this ), '_ajax_fetch_list_nonce' );
		?>
		<?php $this->tablenav( 'top' ); ?>

		<div id="availablethemes">
			<?php $this->display_rows_or_placeholder(); ?>
		</div>

		<?php $this->tablenav( 'bottom' ); ?>
		<?php
	}

	/**
	 * @return array
	 */
	public function get_columns() {
		return array();
	}

	/**
	 */
	public function display_rows_or_placeholder() {
		if ( $this->has_items() ) {
			$this->display_rows();
		} else {
			echo '<div class="no-items">';
			$this->no_items();
			echo '</div>';
		}
	}

	/**
	 */
	public function display_rows() {
		$themes = $this->items;

		foreach ( $themes as $theme ) :
			?>
			<div class="available-theme">
			<?php

			$template   = $theme->get_template();
			$stylesheet = $theme->get_stylesheet();
			$title      = $theme->display( 'Name' );
			$version    = $theme->display( 'Version' );
			$author     = $theme->display( 'Author' );

			$activate_link = gc_nonce_url( 'themes.php?action=activate&amp;template=' . urlencode( $template ) . '&amp;stylesheet=' . urlencode( $stylesheet ), 'switch-theme_' . $stylesheet );

			$actions             = array();
			$actions['activate'] = sprintf(
				'<a href="%s" class="activatelink" title="%s">%s</a>',
				$activate_link,
				/* translators: %s: Theme name. */
				esc_attr( sprintf( _x( '启用“%s”', 'theme' ), $title ) ),
				__( '启用' )
			);

			if ( current_user_can( 'edit_theme_options' ) && current_user_can( 'customize' ) ) {
				$actions['preview'] .= sprintf(
					'<a href="%s" class="load-customize hide-if-no-customize">%s</a>',
					gc_customize_url( $stylesheet ),
					__( '实时预览' )
				);
			}

			if ( ! is_multisite() && current_user_can( 'delete_themes' ) ) {
				$actions['delete'] = sprintf(
					'<a class="submitdelete deletion" href="%s" onclick="return confirm( \'%s\' );">%s</a>',
					gc_nonce_url( 'themes.php?action=delete&amp;stylesheet=' . urlencode( $stylesheet ), 'delete-theme_' . $stylesheet ),
					/* translators: %s: Theme name. */
					esc_js( sprintf( __( "Y您将要删除此主题 '%s'\n  “取消”停止，“确定”删除。" ), $title ) ),
					__( '删除' )
				);
			}

			/** This filter is documented in gc-admin/includes/class-gc-ms-themes-list-table.php */
			$actions = apply_filters( 'theme_action_links', $actions, $theme, 'all' );

			/** This filter is documented in gc-admin/includes/class-gc-ms-themes-list-table.php */
			$actions       = apply_filters( "theme_action_links_{$stylesheet}", $actions, $theme, 'all' );
			$delete_action = isset( $actions['delete'] ) ? '<div class="delete-theme">' . $actions['delete'] . '</div>' : '';
			unset( $actions['delete'] );

			$screenshot = $theme->get_screenshot();
			?>

			<span class="screenshot hide-if-customize">
				<?php if ( $screenshot ) : ?>
					<img src="<?php echo esc_url( $screenshot ); ?>" alt="" />
				<?php endif; ?>
			</span>
			<a href="<?php echo gc_customize_url( $stylesheet ); ?>" class="screenshot load-customize hide-if-no-customize">
				<?php if ( $screenshot ) : ?>
					<img src="<?php echo esc_url( $screenshot ); ?>" alt="" />
				<?php endif; ?>
			</a>

			<h3><?php echo $title; ?></h3>
			<div class="theme-author">
				<?php
					/* translators: %s: Theme author. */
					printf( __( '作者：%s' ), $author );
				?>
			</div>
			<div class="action-links">
				<ul>
					<?php foreach ( $actions as $action ) : ?>
						<li><?php echo $action; ?></li>
					<?php endforeach; ?>
					<li class="hide-if-no-js"><a href="#" class="theme-detail"><?php _e( '详情' ); ?></a></li>
				</ul>
				<?php echo $delete_action; ?>

				<?php theme_update_available( $theme ); ?>
			</div>

			<div class="themedetaildiv hide-if-js">
				<p><strong><?php _e( '版本：' ); ?></strong> <?php echo $version; ?></p>
				<p><?php echo $theme->display( 'description' ); ?></p>
				<?php
				if ( $theme->parent() ) {
					printf(
						/* translators: 1: Link to documentation on child themes, 2: Name of parent theme. */
						' <p class="howto">' . __( '这个<a href="%1$s">子主题</a>需要其父主题%2$s才能工作。' ) . '</p>',
						__( 'https://developer.gechiui.com/themes/advanced-topics/child-themes/' ),
						$theme->parent()->display( 'Name' )
					);
				}
				?>
			</div>

			</div>
			<?php
		endforeach;
	}

	/**
	 * @param GC_Theme $theme
	 * @return bool
	 */
	public function search_theme( $theme ) {
		// Search the features.
		foreach ( $this->features as $word ) {
			if ( ! in_array( $word, $theme->get( 'Tags' ), true ) ) {
				return false;
			}
		}

		// Match all phrases.
		foreach ( $this->search_terms as $word ) {
			if ( in_array( $word, $theme->get( 'Tags' ), true ) ) {
				continue;
			}

			foreach ( array( 'Name', 'description', 'Author', 'AuthorURI' ) as $header ) {
				// Don't mark up; Do translate.
				if ( false !== stripos( strip_tags( $theme->display( $header, false, true ) ), $word ) ) {
					continue 2;
				}
			}

			if ( false !== stripos( $theme->get_stylesheet(), $word ) ) {
				continue;
			}

			if ( false !== stripos( $theme->get_template(), $word ) ) {
				continue;
			}

			return false;
		}

		return true;
	}

	/**
	 * Send required variables to JavaScript land
	 *
	 *
	 * @param array $extra_args
	 */
	public function _js_vars( $extra_args = array() ) {
		$search_string = isset( $_REQUEST['s'] ) ? esc_attr( gc_unslash( $_REQUEST['s'] ) ) : '';

		$args = array(
			'search'      => $search_string,
			'features'    => $this->features,
			'paged'       => $this->get_pagenum(),
			'total_pages' => ! empty( $this->_pagination_args['total_pages'] ) ? $this->_pagination_args['total_pages'] : 1,
		);

		if ( is_array( $extra_args ) ) {
			$args = array_merge( $args, $extra_args );
		}

		printf( "<script type='text/javascript'>var theme_list_args = %s;</script>\n", gc_json_encode( $args ) );
		parent::_js_vars();
	}
}