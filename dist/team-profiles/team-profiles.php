<?php
/**
 * Plugin Name: Team Profiles
 * Description: Create and display a sortable team grid with photos, qualifications, and blurbs via shortcode.
 * Version: 1.0.0
 * Author: Codex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Team_Profiles {
	const VERSION = '1.0.0';
	const SLUG    = 'team-profiles';

	private static $instance = null;

	/**
	 * Ensure singleton.
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_team_member', array( $this, 'save_meta' ), 10, 2 );
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_action( 'admin_post_team_profiles_save_member', array( $this, 'handle_save_member' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_frontend_assets' ) );
		add_action( 'wp_ajax_team_profiles_save_order', array( $this, 'ajax_save_order' ) );
		add_filter( 'manage_team_member_posts_columns', array( $this, 'add_admin_columns' ) );
		add_action( 'manage_team_member_posts_custom_column', array( $this, 'render_admin_column' ), 10, 2 );

		add_shortcode( 'team_profiles', array( $this, 'render_shortcode' ) );
		add_shortcode( 'team', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Register the custom post type used to store team members.
	 */
	public function register_post_type() {
		$labels = array(
			'name'                     => __( 'Team Members', 'team-profiles' ),
			'singular_name'            => __( 'Team Member', 'team-profiles' ),
			'add_new'                  => __( 'Add New Member', 'team-profiles' ),
			'add_new_item'             => __( 'Add New Team Member', 'team-profiles' ),
			'edit_item'                => __( 'Edit Team Member', 'team-profiles' ),
			'new_item'                 => __( 'New Team Member', 'team-profiles' ),
			'view_item'                => __( 'View Team Member', 'team-profiles' ),
			'search_items'             => __( 'Search Team Members', 'team-profiles' ),
			'not_found'                => __( 'No team members found.', 'team-profiles' ),
			'not_found_in_trash'       => __( 'No team members found in Trash.', 'team-profiles' ),
			'all_items'                => __( 'Team Members', 'team-profiles' ),
			'menu_name'                => __( 'Team Profiles', 'team-profiles' ),
			'item_published'           => __( 'Team member published.', 'team-profiles' ),
			'item_updated'             => __( 'Team member updated.', 'team-profiles' ),
			'filter_items_list'        => __( 'Filter team members list', 'team-profiles' ),
			'items_list'               => __( 'Team members list', 'team-profiles' ),
			'items_list_navigation'    => __( 'Team members list navigation', 'team-profiles' ),
			'item_published_privately' => __( 'Team member published privately.', 'team-profiles' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'exclude_from_search'=> true,
			'publicly_queryable' => false,
			'show_ui'            => false,
			'show_in_menu'       => false,
			'show_in_rest'       => true,
			'menu_icon'          => 'dashicons-groups',
			'supports'           => array( 'title', 'thumbnail', 'page-attributes' ),
			'hierarchical'       => false,
			'has_archive'        => false,
			'rewrite'            => false,
		);

		register_post_type( 'team_member', $args );
	}

	/**
	 * Register meta fields for qualifications and blurbs.
	 */
	public function register_meta() {
		register_post_meta(
			'team_member',
			'team_profiles_qualification',
			array(
				'single'            => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'auth_callback'     => array( $this, 'can_edit_meta' ),
			)
		);

		register_post_meta(
			'team_member',
			'team_profiles_blurb',
			array(
				'single'            => true,
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_blurb' ),
				'show_in_rest'      => true,
				'auth_callback'     => array( $this, 'can_edit_meta' ),
			)
		);
	}

	/**
	 * Determine if the current user can edit the given meta.
	 *
	 * @param bool   $allowed Current allowed value.
	 * @param string $meta_key Meta key being sanitized.
	 * @param int    $post_id Post ID.
	 *
	 * @return bool
	 */
	public function can_edit_meta( $allowed, $meta_key, $post_id ) {
		unset( $allowed, $meta_key );

		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Sanitize blurb text, allowing basic formatting.
	 *
	 * @param string $value Raw blurb content.
	 *
	 * @return string
	 */
	public function sanitize_blurb( $value ) {
		$value = is_string( $value ) ? $value : '';

		return wp_kses_post( $value );
	}

	/**
	 * Add meta box for qualifications and blurb.
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'team_profiles_details',
			__( 'Team Details', 'team-profiles' ),
			array( $this, 'render_meta_box' ),
			'team_member',
			'normal',
			'high'
		);
	}

	/**
	 * Render meta box fields.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( 'team_profiles_meta_nonce', 'team_profiles_meta_nonce' );

		$qualification = get_post_meta( $post->ID, 'team_profiles_qualification', true );
		$blurb         = get_post_meta( $post->ID, 'team_profiles_blurb', true );
		?>
		<p>
			<label for="team_profiles_qualification"><strong><?php esc_html_e( 'Qualification', 'team-profiles' ); ?></strong></label><br />
			<input type="text" class="widefat" id="team_profiles_qualification" name="team_profiles_qualification" value="<?php echo esc_attr( $qualification ); ?>" placeholder="<?php esc_attr_e( 'Ex: Lead Designer, Senior Developer', 'team-profiles' ); ?>" />
		</p>
		<p>
			<label for="team_profiles_blurb"><strong><?php esc_html_e( 'Short blurb (optional)', 'team-profiles' ); ?></strong></label><br />
			<textarea class="widefat" id="team_profiles_blurb" name="team_profiles_blurb" rows="5" placeholder="<?php esc_attr_e( 'Add a brief bio or highlight.', 'team-profiles' ); ?>"><?php echo esc_textarea( $blurb ); ?></textarea>
			<em><?php esc_html_e( 'Use the Featured Image box for the profile photo.', 'team-profiles' ); ?></em>
		</p>
		<?php
	}

	/**
	 * Persist meta box fields.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post object.
	 */
	public function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['team_profiles_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['team_profiles_meta_nonce'] ) ), 'team_profiles_meta_nonce' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( 'team_member' !== $post->post_type ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$qualification = isset( $_POST['team_profiles_qualification'] ) ? sanitize_text_field( wp_unslash( $_POST['team_profiles_qualification'] ) ) : '';
		$blurb         = isset( $_POST['team_profiles_blurb'] ) ? $this->sanitize_blurb( wp_unslash( $_POST['team_profiles_blurb'] ) ) : '';

		update_post_meta( $post_id, 'team_profiles_qualification', $qualification );
		update_post_meta( $post_id, 'team_profiles_blurb', $blurb );
	}

	/**
	 * Add plugin top-level menu and ordering page.
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Team Profiles', 'team-profiles' ),
			__( 'Team Profiles', 'team-profiles' ),
			'edit_posts',
			self::SLUG,
			array( $this, 'render_manage_page' ),
			'dashicons-groups',
			26
		);
	}

	/**
	 * Enqueue admin assets when needed.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function admin_assets( $hook ) {
		if ( 'toplevel_page_team-profiles' === $hook ) {
			wp_enqueue_media();

			wp_enqueue_style(
				'team-profiles-admin',
				plugins_url( 'assets/team-profiles-admin.css', __FILE__ ),
				array(),
				self::VERSION
			);

			wp_enqueue_script( 'jquery-ui-sortable' );
			wp_enqueue_script(
				'team-profiles-admin',
				plugins_url( 'assets/team-profiles-admin.js', __FILE__ ),
				array( 'jquery', 'jquery-ui-sortable' ),
				self::VERSION,
				true
			);

			wp_localize_script(
				'team-profiles-admin',
				'TeamProfilesOrder',
				array(
					'nonce'      => wp_create_nonce( 'team_profiles_order' ),
					'savingText' => __( 'Saving order...', 'team-profiles' ),
					'savedText'  => __( 'Order saved.', 'team-profiles' ),
					'errorText'  => __( 'Unable to save order. Please try again.', 'team-profiles' ),
					'photoFrameTitle' => __( 'Select profile photo', 'team-profiles' ),
					'photoFrameButton'=> __( 'Use this photo', 'team-profiles' ),
				)
			);
		}
	}

	/**
	 * Register frontend styles.
	 */
	public function register_frontend_assets() {
		wp_register_style(
			'team-profiles',
			plugins_url( 'assets/team-profiles.css', __FILE__ ),
			array(),
			self::VERSION
		);
	}

	/**
	 * Render the shortcode output.
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'count' => -1,
			),
			$atts,
			'team_profiles'
		);

		wp_enqueue_style( 'team-profiles' );

		$query = new WP_Query(
			array(
				'post_type'      => 'team_member',
				'posts_per_page' => intval( $atts['count'] ),
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
				'post_status'    => 'publish',
			)
		);

		if ( ! $query->have_posts() ) {
			return '';
		}

		ob_start();
		?>
		<div class="team-profiles-grid" role="list" aria-label="<?php esc_attr_e( 'Team members', 'team-profiles' ); ?>">
			<?php
			while ( $query->have_posts() ) :
				$query->the_post();
				$member_id     = get_the_ID();
				$name          = get_the_title();
				$qualification = get_post_meta( $member_id, 'team_profiles_qualification', true );
				$blurb         = get_post_meta( $member_id, 'team_profiles_blurb', true );
				$thumb_id      = get_post_thumbnail_id( $member_id );
				$thumb_src     = $thumb_id ? wp_get_attachment_image_src( $thumb_id, 'medium' ) : false;
				?>
				<article class="team-profiles__item" role="listitem" itemscope itemtype="https://schema.org/Person">
					<div class="team-profiles__image-wrapper">
						<?php
						if ( $thumb_id ) {
							echo wp_get_attachment_image(
								$thumb_id,
								'medium',
								false,
								array(
									'class'   => 'team-profiles__photo',
									'loading' => 'lazy',
									'alt'     => $name,
									'itemprop'=> 'image',
								)
							);
							if ( $thumb_src ) {
								echo '<meta itemprop="image" content="' . esc_url( $thumb_src[0] ) . '" />';
							}
						} else {
							echo '<div class="team-profiles__placeholder" aria-hidden="true"></div>';
						}
						?>
					</div>
					<h3 class="team-profiles__name" itemprop="name"><?php echo esc_html( $name ); ?></h3>
					<?php if ( $qualification ) : ?>
						<p class="team-profiles__role"><span itemprop="jobTitle"><?php echo esc_html( $qualification ); ?></span></p>
					<?php endif; ?>
					<?php if ( $blurb ) : ?>
						<div class="team-profiles__blurb" itemprop="description"><?php echo wp_kses_post( wpautop( $blurb ) ); ?></div>
					<?php endif; ?>
				</article>
			<?php endwhile; ?>
		</div>
		<?php
		wp_reset_postdata();

		return ob_get_clean();
	}

	/**
	 * Render manage page: add/edit members and ordering.
	 */
	public function render_manage_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$members = get_posts(
			array(
				'post_type'      => 'team_member',
				'numberposts'    => -1,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
				'post_status'    => array( 'publish', 'draft', 'pending', 'future' ),
			)
		);
		$status  = isset( $_GET['team_profiles_status'] ) ? sanitize_text_field( wp_unslash( $_GET['team_profiles_status'] ) ) : '';
		$message = '';
		if ( 'created' === $status ) {
			$message = __( 'Team member added.', 'team-profiles' );
		} elseif ( 'updated' === $status ) {
			$message = __( 'Team member updated.', 'team-profiles' );
		} elseif ( 'error' === $status ) {
			$message = __( 'There was a problem saving the team member. Please try again.', 'team-profiles' );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Team Profiles', 'team-profiles' ); ?></h1>
			<p><?php esc_html_e( 'Manage your team from one page: add/edit members, upload their photo, and drag to order. Use the shortcode to display on the front end.', 'team-profiles' ); ?></p>

			<?php if ( $message ) : ?>
				<?php $class = 'error' === $status ? 'notice notice-error' : 'notice notice-success'; ?>
				<div class="<?php echo esc_attr( $class ); ?>"><p><?php echo esc_html( $message ); ?></p></div>
			<?php endif; ?>

			<div class="team-profiles-admin">
				<div class="team-profiles-admin__panel">
					<h2><?php esc_html_e( 'Add or Edit a Team Member', 'team-profiles' ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="team-profiles-form">
						<input type="hidden" name="action" value="team_profiles_save_member" />
						<?php wp_nonce_field( 'team_profiles_save_member', 'team_profiles_save_member_nonce' ); ?>
						<input type="hidden" name="member_id" id="team-profiles-member-id" value="" />
						<input type="hidden" name="team_profiles_photo_id" id="team-profiles-photo-id" value="" />

						<p>
							<label for="team-profiles-name"><strong><?php esc_html_e( 'Name', 'team-profiles' ); ?> *</strong></label>
							<input type="text" required class="widefat" name="team_profiles_name" id="team-profiles-name" />
						</p>

						<p>
							<label for="team-profiles-qualification"><strong><?php esc_html_e( 'Qualification / Role', 'team-profiles' ); ?></strong></label>
							<input type="text" class="widefat" name="team_profiles_qualification" id="team-profiles-qualification" />
						</p>

						<p>
							<label for="team-profiles-blurb"><strong><?php esc_html_e( 'Short blurb', 'team-profiles' ); ?></strong></label>
							<textarea class="widefat" name="team_profiles_blurb" id="team-profiles-blurb" rows="5"></textarea>
						</p>

						<div class="team-profiles-photo">
							<label><strong><?php esc_html_e( 'Profile photo', 'team-profiles' ); ?></strong></label>
							<div class="team-profiles-photo__controls">
								<div class="team-profiles-photo__preview team-profiles-photo__placeholder" id="team-profiles-photo-preview" data-src=""></div>
								<div class="team-profiles-photo__buttons">
									<button type="button" class="button" id="team-profiles-photo-select"><?php esc_html_e( 'Select photo', 'team-profiles' ); ?></button>
									<button type="button" class="button link-button" id="team-profiles-photo-remove"><?php esc_html_e( 'Remove', 'team-profiles' ); ?></button>
								</div>
							</div>
							<p class="description"><?php esc_html_e( 'Square images work best. The photo will be shown as a circle.', 'team-profiles' ); ?></p>
						</div>

						<p class="team-profiles-form__actions">
							<button type="submit" class="button button-primary" id="team-profiles-submit"><?php esc_html_e( 'Save Member', 'team-profiles' ); ?></button>
							<button type="button" class="button" id="team-profiles-new"><?php esc_html_e( 'Add New', 'team-profiles' ); ?></button>
						</p>
					</form>
				</div>

				<div class="team-profiles-admin__panel">
					<h2><?php esc_html_e( 'Team list & order', 'team-profiles' ); ?></h2>
					<?php if ( empty( $members ) ) : ?>
						<p><?php esc_html_e( 'No team members yet. Add some first.', 'team-profiles' ); ?></p>
					<?php else : ?>
						<ul id="team-profiles-sortable" class="team-profiles-sortable" aria-live="polite">
							<?php
							foreach ( $members as $member ) :
								$qualification = get_post_meta( $member->ID, 'team_profiles_qualification', true );
								$thumb_id      = get_post_thumbnail_id( $member->ID );
								$thumb_src     = $thumb_id ? wp_get_attachment_image_src( $thumb_id, 'thumbnail' ) : false;
								?>
								<li class="team-profiles-sortable__item" data-id="<?php echo esc_attr( $member->ID ); ?>" data-name="<?php echo esc_attr( $member->post_title ); ?>" data-qualification="<?php echo esc_attr( $qualification ); ?>" data-blurb="<?php echo esc_attr( get_post_meta( $member->ID, 'team_profiles_blurb', true ) ); ?>" data-photo-id="<?php echo esc_attr( $thumb_id ); ?>" data-photo-src="<?php echo $thumb_src ? esc_url( $thumb_src[0] ) : ''; ?>">
									<span class="team-profiles-sortable__handle dashicons dashicons-move" aria-hidden="true"></span>
									<span class="team-profiles-sortable__thumb-wrapper">
										<?php
										$thumb = get_the_post_thumbnail(
											$member->ID,
											'thumbnail',
											array(
												'class' => 'team-profiles-sortable__thumb',
												'alt'   => get_the_title( $member->ID ),
											)
										);
										echo $thumb ? $thumb : '<span class="team-profiles-sortable__placeholder" aria-hidden="true"></span>';
										?>
									</span>
									<span class="team-profiles-sortable__text">
										<strong><?php echo esc_html( $member->post_title ); ?></strong>
										<?php if ( $qualification ) : ?>
											<em><?php echo esc_html( $qualification ); ?></em>
										<?php endif; ?>
									</span>
									<button type="button" class="button button-small team-profiles-edit"><?php esc_html_e( 'Edit', 'team-profiles' ); ?></button>
								</li>
							<?php endforeach; ?>
						</ul>
						<p class="description"><?php esc_html_e( 'Drag to reorder. The order here is the order shown on the frontend.', 'team-profiles' ); ?></p>
						<div id="team-profiles-order-status" class="notice-inline" aria-live="polite"></div>
					<?php endif; ?>

					<div class="team-profiles-shortcode">
						<h3><?php esc_html_e( 'Shortcode', 'team-profiles' ); ?></h3>
						<p><code>[team_profiles]</code> <?php esc_html_e( 'or', 'team-profiles' ); ?> <code>[team]</code></p>
						<p class="description"><?php esc_html_e( 'Optional attribute: count="4" limits how many members display.', 'team-profiles' ); ?></p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Save ordering via AJAX.
	 */
	public function ajax_save_order() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'You do not have permission to reorder team members.', 'team-profiles' ) );
		}

		check_ajax_referer( 'team_profiles_order', 'nonce' );

		if ( empty( $_POST['order'] ) || ! is_array( $_POST['order'] ) ) {
			wp_send_json_error( __( 'Invalid order payload.', 'team-profiles' ) );
		}

		$order = array_map( 'intval', $_POST['order'] );

		foreach ( $order as $index => $post_id ) {
			if ( 'team_member' !== get_post_type( $post_id ) ) {
				continue;
			}

			wp_update_post(
				array(
					'ID'         => $post_id,
					'menu_order' => $index,
				)
			);
		}

		wp_send_json_success();
	}

	/**
	 * Add qualification column in admin list.
	 *
	 * @param array $columns Existing columns.
	 *
	 * @return array
	 */
	public function add_admin_columns( $columns ) {
		$columns['team_profiles_qualification'] = __( 'Qualification', 'team-profiles' );

		return $columns;
	}

	/**
	 * Render custom column content.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public function render_admin_column( $column, $post_id ) {
		if ( 'team_profiles_qualification' === $column ) {
			$qualification = get_post_meta( $post_id, 'team_profiles_qualification', true );

			echo $qualification ? esc_html( $qualification ) : '&mdash;';
		}
	}

	/**
	 * Handle add/update of team members from custom admin form.
	 */
	public function handle_save_member() {
		if ( ! isset( $_POST['team_profiles_save_member_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['team_profiles_save_member_nonce'] ) ), 'team_profiles_save_member' ) ) {
			wp_safe_redirect( add_query_arg( 'team_profiles_status', 'error', admin_url( 'admin.php?page=' . self::SLUG ) ) );
			exit;
		}

		$member_id     = isset( $_POST['member_id'] ) ? intval( $_POST['member_id'] ) : 0;
		$name          = isset( $_POST['team_profiles_name'] ) ? sanitize_text_field( wp_unslash( $_POST['team_profiles_name'] ) ) : '';
		$qualification = isset( $_POST['team_profiles_qualification'] ) ? sanitize_text_field( wp_unslash( $_POST['team_profiles_qualification'] ) ) : '';
		$blurb         = isset( $_POST['team_profiles_blurb'] ) ? $this->sanitize_blurb( wp_unslash( $_POST['team_profiles_blurb'] ) ) : '';
		$photo_id      = isset( $_POST['team_profiles_photo_id'] ) ? intval( $_POST['team_profiles_photo_id'] ) : 0;

		if ( '' === $name ) {
			wp_safe_redirect( add_query_arg( 'team_profiles_status', 'error', admin_url( 'admin.php?page=' . self::SLUG ) ) );
			exit;
		}

		$is_new = 0 === $member_id;

		if ( $is_new ) {
			if ( ! current_user_can( 'publish_posts' ) ) {
				wp_safe_redirect( add_query_arg( 'team_profiles_status', 'error', admin_url( 'admin.php?page=' . self::SLUG ) ) );
				exit;
			}

			$member_id = wp_insert_post(
				array(
					'post_type'   => 'team_member',
					'post_status' => 'publish',
					'post_title'  => $name,
				)
			);
		} else {
			if ( 'team_member' !== get_post_type( $member_id ) || ! current_user_can( 'edit_post', $member_id ) ) {
				wp_safe_redirect( add_query_arg( 'team_profiles_status', 'error', admin_url( 'admin.php?page=' . self::SLUG ) ) );
				exit;
			}

			wp_update_post(
				array(
					'ID'         => $member_id,
					'post_title' => $name,
				)
			);
		}

		if ( $member_id && ! is_wp_error( $member_id ) ) {
			update_post_meta( $member_id, 'team_profiles_qualification', $qualification );
			update_post_meta( $member_id, 'team_profiles_blurb', $blurb );

			if ( $photo_id ) {
				set_post_thumbnail( $member_id, $photo_id );
			} else {
				delete_post_thumbnail( $member_id );
			}

			$status = $is_new ? 'created' : 'updated';
			wp_safe_redirect( add_query_arg( 'team_profiles_status', $status, admin_url( 'admin.php?page=' . self::SLUG ) ) );
			exit;
		}

		wp_safe_redirect( add_query_arg( 'team_profiles_status', 'error', admin_url( 'admin.php?page=' . self::SLUG ) ) );
		exit;
	}
}

Team_Profiles::instance();
