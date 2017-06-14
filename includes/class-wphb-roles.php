<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'WPHB_Roles' ) ) {

	/**
	 * Class WPHB_Roles
	 */
	class WPHB_Roles {


		/**
		 * WPHB_Roles constructor.
		 */
		public function __construct() {

			add_action( 'plugins_loaded', array( $this, 'add_roles' ) );

		}


		/**
		 * Add user roles.
		 */
		public static function add_roles() {

			add_role(
				'wphb_hotel_manager',
				__( 'Hotel Manager' ),
				array()
			);

			$room_cap    = 'hb_rooms';
			$booking_cap = 'hb_bookings';

			$hotel_manager = get_role( 'wphb_hotel_manager' );

			// add capability for hotel manager
			$hotel_manager->add_cap( 'delete_' . $room_cap );
			$hotel_manager->add_cap( 'delete_published_' . $room_cap );
			$hotel_manager->add_cap( 'delete_private_' . $room_cap );
			$hotel_manager->add_cap( 'edit_others_' . $room_cap );
			$hotel_manager->add_cap( 'edit_' . $room_cap );
			$hotel_manager->add_cap( 'edit_published_' . $room_cap );
			$hotel_manager->add_cap( 'edit_private_' . $room_cap );
			$hotel_manager->add_cap( 'edit_others_' . $room_cap );

			$hotel_manager->add_cap( 'delete_' . $booking_cap );
			$hotel_manager->add_cap( 'delete_published_' . $booking_cap );
			$hotel_manager->add_cap( 'delete_private_' . $booking_cap );
			$hotel_manager->add_cap( 'edit_others_' . $booking_cap );
			$hotel_manager->add_cap( 'edit_' . $booking_cap );
			$hotel_manager->add_cap( 'edit_published_' . $booking_cap );
			$hotel_manager->add_cap( 'edit_private_' . $booking_cap );
			$hotel_manager->add_cap( 'edit_others_' . $booking_cap );

			$hotel_manager->add_cap( 'upload_files' );
			$hotel_manager->add_cap( 'manage_hb_booking' );


			$admin = get_role( 'administrator' );

			// add capability for admin
			$admin->add_cap( 'delete_' . $room_cap );
			$admin->add_cap( 'delete_published_' . $room_cap );
			$admin->add_cap( 'delete_private_' . $room_cap );
			$admin->add_cap( 'edit_others_' . $room_cap );
			$admin->add_cap( 'edit_' . $room_cap );
			$admin->add_cap( 'edit_published_' . $room_cap );
			$admin->add_cap( 'edit_private_' . $room_cap );
			$admin->add_cap( 'edit_others_' . $room_cap );

			$admin->add_cap( 'delete_' . $booking_cap );
			$admin->add_cap( 'delete_published_' . $booking_cap );
			$admin->add_cap( 'delete_private_' . $booking_cap );
			$admin->add_cap( 'edit_others_' . $booking_cap );
			$admin->add_cap( 'edit_' . $booking_cap );
			$admin->add_cap( 'edit_published_' . $booking_cap );
			$admin->add_cap( 'edit_private_' . $booking_cap );
			$admin->add_cap( 'edit_others_' . $booking_cap );

			$admin->add_cap( 'manage_hb_booking' );

		}

	}

}

new WPHB_Roles();