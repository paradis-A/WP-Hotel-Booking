<?php

/**
 * WP Hotel Booking admin settings class.
 *
 * @class       WPHB_Admin_Settings
 * @version     2.0
 * @package     WP_Hotel_Booking/Classes
 * @category    Class
 * @author      Thimpress, leehld
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPHB_Admin_Settings' ) ) {
	/**
	 * Class WPHB_Admin_Settings.
	 *
	 * @since 2.0
	 */
	class WPHB_Admin_Settings {
		/**
		 * Get admin setting page tabs.
		 *
		 * @since 2.0
		 *
		 * @return array
		 */
		public static function get_settings_pages() {
			$tabs = array();

			$tabs[] = include_once 'settings/class-wphb-setting-general.php';
			$tabs[] = include_once 'settings/class-wphb-setting-pages.php';
			$tabs[] = include_once 'settings/class-wphb-setting-room.php';
			$tabs[] = include_once 'settings/class-wphb-setting-payments.php';
			$tabs[] = include_once 'settings/class-wphb-setting-emails.php';

			return apply_filters( 'hotel_booking_admin_setting_pages', $tabs );
		}

		/**
		 * Output settings page.
		 *
		 * @since 2.0
		 */
		public static function output() {
			self::get_settings_pages();
			$tabs         = hb_admin_settings_tabs();
			$selected_tab = ! empty( $_REQUEST['tab'] ) ? sanitize_text_field( $_REQUEST['tab'] ) : '';

			if ( ! array_key_exists( $selected_tab, $tabs ) ) {
				$tab_keys     = array_keys( $tabs );
				$selected_tab = reset( $tab_keys );
			} ?>

            <div id='wphb-admin-setting-wrapper' class="wrap">
                <h2 class="nav-tab-wrapper">
					<?php if ( is_array( $tabs ) && $tabs ) { ?>
						<?php foreach ( $tabs as $slug => $title ) { ?>
                            <a class="nav-tab<?php echo sprintf( '%s', $selected_tab == $slug ? ' nav-tab-active' : '' ); ?>"
                               href="?page=wphb-settings&tab=<?php echo esc_attr( $slug ); ?>"
                               data-tab="<?php echo esc_attr( $slug ); ?>">
								<?php echo esc_html( $title ); ?>
                            </a>
						<?php } ?>
					<?php } ?>
                </h2>
				<?php if ( is_array( $tabs ) && $tabs ) { ?>
					<?php foreach ( $tabs as $slug => $title ) { ?>
                        <form method="post" action="" enctype="multipart/form-data" name="hb-admin-settings-form"
                              id="settings-<?php echo esc_attr( $slug ); ?>"
                              style="<?php echo $selected_tab !== $slug ? 'display: none' : ''; ?>">
							<?php do_action( 'hb_admin_settings_tab_before', $slug ); ?>
							<?php do_action( 'hb_admin_settings_sections_' . $slug ); ?>
							<?php do_action( 'hb_admin_settings_tab_' . $slug ); ?>
							<?php wp_nonce_field( 'hb_admin_settings_tab_' . $slug, 'hb_admin_settings_tab_' . $slug . '_field' ); ?>
							<?php do_action( 'hb_admin_settings_tab_after', $slug ); ?>
                            <button class="button button-primary"><?php _e( 'Update', 'wp-hotel-booking' ); ?></button>
                        </form>
					<?php } ?>
				<?php } ?>
            </div>
			<?php
		}
	}
}