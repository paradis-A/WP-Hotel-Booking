<?php
/**
 * WP Hotel Booking core booking functions.
 *
 * @version     2.0
 * @author      ThimPress
 * @package     WP_Hotel_Booking/Functions
 * @category    Core Functions
 * @author      Thimpress, leehld
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'hb_create_booking' ) ) {
	/**
	 * Create new booking.
	 *
	 * @param array $booking_info
	 * @param array $order_items
	 *
	 * @return int|WP_Error
	 */
	function hb_create_booking( $booking_info = array(), $order_items = array() ) {
		$cart = WPHB_Cart::instance();
		if ( $cart->__get( 'cart_items_count' ) == 0 ) {
			return new WP_Error( 'hotel_booking_cart_empty', __( 'Your cart is empty.', 'wp-hotel-booking' ) );
		}

		$args = array(
			'status'        => '',
			'user_id'       => get_current_user_id(),
			'customer_note' => null,
			'booking_id'    => 0,
			'parent'        => 0
		);

		// instance empty pending booking
		$booking = WPHB_Booking::instance( $args['booking_id'] );

		$booking->post->post_title   = sprintf( __( 'Booking ', 'wp-hotel-booking' ) );
		$booking->post->post_content = hb_get_request( 'addition_information' ) ? hb_get_request( 'addition_information' ) : __( 'Empty Booking Notes', 'wp-hotel-booking' );
		$booking->post->post_status  = 'hb-' . apply_filters( 'hb_default_order_status', 'pending' );

		if ( $args['status'] ) {
			if ( ! in_array( 'hb-' . $args['status'], array_keys( hb_get_booking_statuses() ) ) ) {
				return new WP_Error( 'hb_invalid_booking_status', __( 'Invalid booking status', 'wp-hotel-booking' ) );
			}
			$booking->post->post_status = 'hb-' . $args['status'];
		}

		$booking_info['_hb_booking_key'] = apply_filters( 'hb_generate_booking_key', uniqid() );

		// update booking info
		$booking->set_booking_info( $booking_info );

		$booking_id = $booking->update( $order_items );

		// set session booking id
		$cart->set_booking( 'booking_id', $booking_id );

		// do action
		do_action( 'hotel_booking_create_booking', $booking_id, $booking_info, $order_items );

		return $booking_id;
	}
}

if ( ! function_exists( 'hb_get_booking_statuses' ) ) {
	/**
	 * Gets all statuses that room supported.
	 *
	 * @return mixed
	 */
	function hb_get_booking_statuses() {
		$booking_statuses = array(
			'hb-pending'    => _x( 'Pending', 'Booking status', 'wp-hotel-booking' ),
			'hb-cancelled'  => _x( 'Cancelled', 'Booking status', 'wp-hotel-booking' ),
			'hb-processing' => _x( 'Processing', 'Booking status', 'wp-hotel-booking' ),
			'hb-completed'  => _x( 'Completed', 'Booking status', 'wp-hotel-booking' ),
		);

		return apply_filters( 'hb_booking_statuses', $booking_statuses );
	}
}

if ( ! function_exists( 'hb_get_booking_items' ) ) {
	/**
	 * @param null $booking_id
	 * @param string $item_type
	 * @param null $parent
	 * @param bool $array_a
	 *
	 * @return array|null|object
	 */
	function hb_get_booking_items( $booking_id = null, $item_type = 'line_item', $parent = null, $array_a = false ) {

		global $wpdb;

		if ( ! $parent ) {
			$query = $wpdb->prepare( "
                    SELECT booking.* FROM $wpdb->hotel_booking_order_items AS booking
                        RIGHT JOIN $wpdb->posts AS post ON booking.order_id = post.ID
                    WHERE post.ID = %d
                        AND booking.order_item_type = %s
                ", $booking_id, $item_type );
		} else {
			$query = $wpdb->prepare( "
                    SELECT booking.* FROM $wpdb->hotel_booking_order_items AS booking
                        RIGHT JOIN $wpdb->posts AS post ON booking.order_id = post.ID
                    WHERE post.ID = %d
                        AND booking.order_item_type = %s
                        AND booking.order_item_parent = %d
                ", $booking_id, $item_type, $parent );
		}

		$result = $array_a ? $wpdb->get_results( $query, ARRAY_A ) : $wpdb->get_results( $query );

		$items = array();
		if ( is_array( $result ) && $result && $array_a ) {
			foreach ( $result as $key => $item ) {

				$items[ $key ] = $item;

				$product_id = hb_get_booking_item_meta( $item['order_item_id'], 'product_id', true );
				$post_type  = get_post_type( $product_id );

				if ( WPHB_Room_CPT == $post_type ) {
					$check_in_date  = hb_get_booking_item_meta( $item['order_item_id'], 'check_in_date', true );
					$check_out_date = hb_get_booking_item_meta( $item['order_item_id'], 'check_out_date', true );

					$items[ $key ] ['edit_link']      = get_edit_post_link( hb_get_booking_item_meta( $item['order_item_id'], 'product_id', true ) );
					$items[ $key ] ['check_in_date']  = date_i18n( hb_get_date_format(), $check_in_date );
					$items[ $key ] ['check_out_date'] = date_i18n( hb_get_date_format(), $check_out_date );
					$items[ $key ] ['night']          = hb_count_nights_two_dates( $check_out_date, $check_in_date );
					$items[ $key ] ['qty']            = hb_get_booking_item_meta( $item['order_item_id'], 'qty', true );
					$items[ $key ] ['price']          = hb_get_booking_item_meta( $item['order_item_id'], 'subtotal', true );
					$items[ $key ]['extra']           = hb_get_booking_items( $booking_id, 'sub_item', $item['order_item_id'], true );
				} else if ( WPHB_Extra_CPT == $post_type ) {
					$items[ $key ]['unit']   = get_post_meta( $product_id, 'tp_hb_extra_room_respondent', true );
					$items[ $key ] ['qty']   = hb_get_booking_item_meta( $item['order_item_id'], 'qty', true );
					$items[ $key ] ['price'] = hb_get_booking_item_meta( $item['order_item_id'], 'subtotal', true );
				}
			}

			return $items;
		}

		return $result;
	}
}

if ( ! function_exists( 'hb_add_booking_item' ) ) {
	/**
	 * Add booking item.
	 *
	 * @param null $booking_id
	 * @param array $param
	 *
	 * @return bool|int
	 */
	function hb_add_booking_item( $booking_id = null, $param = array() ) {

		global $wpdb;

		$booking_id = absint( $booking_id );

		if ( ! $booking_id ) {
			return false;
		}

		$defaults = array(
			'order_item_name' => '',
			'order_item_type' => 'line_item',
		);

		$param = wp_parse_args( $param, $defaults );

		$wpdb->insert(
			$wpdb->prefix . 'hotel_booking_order_items',
			array(
				'order_item_name'   => $param['order_item_name'],
				'order_item_type'   => $param['order_item_type'],
				'order_item_parent' => isset( $param['order_item_parent'] ) ? $param['order_item_parent'] : null,
				'order_id'          => $booking_id
			),
			array( '%s', '%s', '%d', '%d' )
		);

		$item_id = absint( $wpdb->insert_id );

		do_action( 'hotel_booking_new_order_item', $item_id, $param, $booking_id );

		return $item_id;
	}
}

if ( ! function_exists( 'hb_update_booking_item' ) ) {
	/**
	 * @param null $item_id
	 * @param array $param
	 *
	 * @return bool
	 */
	function hb_update_booking_item( $item_id = null, $param = array() ) {

		global $wpdb;

		$update = $wpdb->update( $wpdb->prefix . 'hotel_booking_order_items', $param, array( 'order_item_id' => $item_id ) );
		if ( false === $update ) {
			return false;
		}

		do_action( 'hotel_booking_update_order_item', $item_id, $param );

		return true;
	}
}

if ( ! function_exists( 'hb_remove_booking_item' ) ) {
	/**
	 * Remove booking item.
	 *
	 * @param null $booking_item_id
	 */
	function hb_remove_booking_item( $booking_item_id = null ) {

		// user booking curd
		WPHB_Booking_CURD::remove_booking_item( $booking_item_id );
	}
}

if ( ! function_exists( 'hb_get_parent_booking_item' ) ) {
	/**
	 * @param null $order_item_id
	 *
	 * @return null|string
	 */
	function hb_get_parent_booking_item( $order_item_id = null ) {

		global $wpdb;
		$query = $wpdb->prepare( "
                SELECT order_item.order_item_parent FROM $wpdb->hotel_booking_order_items AS order_item
                WHERE
                    order_item.order_item_id = %d
                    LIMIT 1
            ", $order_item_id );

		return $wpdb->get_var( $query );
	}
}

if ( ! function_exists( 'hb_get_sub_item_booking_item_id' ) ) {
	/**
	 * @param null $order_item_id
	 *
	 * @return array
	 */
	function hb_get_sub_item_booking_item_id( $order_item_id = null ) {

		global $wpdb;
		$query = $wpdb->prepare( "
                SELECT order_item.order_item_id FROM $wpdb->hotel_booking_order_items AS order_item
                WHERE
                    order_item.order_item_parent = %d
            ", $order_item_id );

		return $wpdb->get_col( $query );
	}
}

if ( ! function_exists( 'hb_empty_booking_items' ) ) {
	/**
	 * @param null $booking_id
	 *
	 * @return false|int
	 */
	function hb_empty_booking_items( $booking_id = null ) {

		global $wpdb;

		$sql = $wpdb->prepare( "
                DELETE hb_order_item, hb_order_itemmeta
                    FROM $wpdb->hotel_booking_order_items as hb_order_item
                    LEFT JOIN $wpdb->hotel_booking_order_itemmeta as hb_order_itemmeta ON hb_order_item.order_item_id = hb_order_itemmeta.hotel_booking_order_item_id
                WHERE
                    hb_order_item.order_id = %d
            ", $booking_id );

		return $wpdb->query( $sql );
	}
}

if ( ! function_exists( 'hb_add_booking_item_meta' ) ) {
	/**
	 * Add booking item meta.
	 *
	 * @param null $item_id
	 * @param null $meta_key
	 * @param null $meta_value
	 * @param bool $unique
	 *
	 * @return false|int
	 */
	function hb_add_booking_item_meta( $item_id = null, $meta_key = null, $meta_value = null, $unique = false ) {

		return add_metadata( 'hotel_booking_order_item', $item_id, $meta_key, $meta_value, $unique );
	}
}

if ( ! function_exists( 'hb_update_booking_item_meta' ) ) {
	/**
	 * Update booking item meta.
	 *
	 * @param null $item_id
	 * @param null $meta_key
	 * @param null $meta_value
	 * @param bool $prev_value
	 *
	 * @return bool|int
	 */
	function hb_update_booking_item_meta( $item_id = null, $meta_key = null, $meta_value = null, $prev_value = false ) {

		return update_metadata( 'hotel_booking_order_item', $item_id, $meta_key, $meta_value, $prev_value );
	}
}

if ( ! function_exists( 'hb_get_booking_item_meta' ) ) {
	/**
	 * Get booking item meta.
	 *
	 * @param null $item_id
	 * @param null $key
	 * @param bool $single
	 *
	 * @return mixed
	 */
	function hb_get_booking_item_meta( $item_id = null, $key = null, $single = true ) {

		return get_metadata( 'hotel_booking_order_item', $item_id, $key, $single );
	}
}

if ( ! function_exists( 'hb_delete_booking_item_meta' ) ) {
	/**
	 * Delete booking item meta.
	 *
	 * @param null $item_id
	 * @param null $meta_key
	 * @param string $meta_value
	 * @param bool $delete_all
	 *
	 * @return bool
	 */
	function hb_delete_booking_item_meta( $item_id = null, $meta_key = null, $meta_value = '', $delete_all = false ) {

		return delete_metadata( 'hotel_booking_order_item', $item_id, $meta_key, $meta_value, $delete_all );
	}
}

if ( ! function_exists( 'hb_booking_subtotal' ) ) {
	/**
	 * Get booking subtotal.
	 *
	 * @param null $booking_id
	 *
	 * @return int|null|string
	 * @throws Exception
	 */
	function hb_booking_subtotal( $booking_id = null ) {
		if ( ! $booking_id ) {
			throw new Exception( __( 'Booking is not found.', 'wp-hotel-booking' ) );
		}
		$booking = WPHB_Booking::instance( $booking_id );

		return $booking->sub_total();
	}
}

if ( ! function_exists( 'hb_booking_total' ) ) {
	/**
	 * Get booking total.
	 *
	 * @param null $booking_id
	 *
	 * @return int|null|string
	 * @throws Exception
	 */
	function hb_booking_total( $booking_id = null ) {
		if ( ! $booking_id ) {
			throw new Exception( __( 'Booking is not found.', 'wp-hotel-booking' ) );
		}
		$booking = WPHB_Booking::instance( $booking_id );

		return $booking->total();
	}
}

if ( ! function_exists( 'hb_booking_tax_total' ) ) {
	/**
	 * Get booking tax total.
	 *
	 * @param null $booking_id
	 *
	 * @return float|int|null|string
	 * @throws Exception
	 */
	function hb_booking_tax_total( $booking_id = null ) {
		if ( ! $booking_id ) {
			throw new Exception( __( 'Booking is not found.', 'wp-hotel-booking' ) );
		}
		$booking = WPHB_Booking::instance( $booking_id );

		return $booking->tax_total();
	}
}

if ( ! function_exists( 'hb_customer_booked_room' ) ) {
	/**
	 * Checks to see if a user is booked room.
	 *
	 * @param $room_id
	 *
	 * @return mixed
	 */
	function hb_customer_booked_room( $room_id ) {
		return apply_filters( 'hb_customer_booked_room', true, $room_id );
	}
}

if ( ! function_exists( 'hb_get_booking_id_by_key' ) ) {
	/**
	 * Get booking id by booking key.
	 *
	 * @param $booking_key
	 *
	 * @return null|string
	 */
	function hb_get_booking_id_by_key( $booking_key ) {
		global $wpdb;

		$booking_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = '_hb_booking_key' AND meta_value = %s", $booking_key ) );

		return $booking_id;
	}
}

if ( ! function_exists( 'hb_get_booking_status_label' ) ) {
	/**
	 * @param $booking_id
	 *
	 * @return string
	 */
	function hb_get_booking_status_label( $booking_id ) {
		$statuses = hb_get_booking_statuses();
		if ( is_numeric( $booking_id ) ) {
			$status = get_post_status( $booking_id );
		} else {
			$status = $booking_id;
		}

		return ! empty( $statuses[ $status ] ) ? $statuses[ $status ] : __( 'Cancelled', 'wp-hotel-booking' );
	}
}

if ( ! function_exists( 'hb_booking_get_check_in_date' ) ) {
	/**
	 * Get check in date from booking meta.
	 *
	 * @param null $booking_id
	 *
	 * @return array|mixed
	 */
	function hb_booking_get_check_in_date( $booking_id = null ) {
		if ( ! $booking_id ) {
			return array();
		}

		$order_items = hb_get_booking_items( $booking_id );
		$data        = array();
		foreach ( $order_items as $item ) {
			$data[] = hb_get_booking_item_meta( $item->order_item_id, 'check_in_date', true );
		}
		sort( $data );

		return array_shift( $data );

	}
}

if ( ! function_exists( 'hb_booking_get_check_out_date' ) ) {
	/**
	 * Get check out date from booking meta.
	 *
	 * @param null $booking_id
	 *
	 * @return mixed|string
	 */
	function hb_booking_get_check_out_date( $booking_id = null ) {
		if ( ! $booking_id ) {
			return '';
		}

		$order_items = hb_get_booking_items( $booking_id );
		$data        = array();
		foreach ( $order_items as $item ) {
			$data[] = hb_get_booking_item_meta( $item->order_item_id, 'check_out_date', true );
		}
		sort( $data );

		return array_pop( $data );
	}
}

if ( ! function_exists( 'hb_booking_restrict_manage_posts' ) ) {
	/**
	 * Create booking date drop down filter.
	 */
	function hb_booking_restrict_manage_posts() {
		$type = 'post';
		if ( isset( $_GET['post_type'] ) ) {
			$type = $_GET['post_type'];
		}

		// only add filter to hb_booking post type
		if ( 'hb_booking' == $type ) {
			//change this to the list of values you want to show
			$from           = hb_get_request( 'date-from' );
			$from_timestamp = hb_get_request( 'date-from-timestamp' );
			$to             = hb_get_request( 'date-to' );
			$to_timestamp   = hb_get_request( 'date-to-timestamp' );
			$filter_type    = hb_get_request( 'filter-type' );

			$filter_types = apply_filters(
				'hb_booking_filter_types',
				array(
					'booking-date'   => __( 'Booking date', 'wp-hotel-booking' ),
					'check-in-date'  => __( 'Check-in date', 'wp-hotel-booking' ),
					'check-out-date' => __( 'Check-out date', 'wp-hotel-booking' )
				)
			); ?>
			<span><?php _e( 'Date Range', 'wp-hotel-booking' ); ?></span>
			<input type="text" id="hb-booking-date-from" class="hb-date-field" value="<?php echo esc_attr( $from ); ?>"
			       name="date-from" readonly placeholder="<?php _e( 'From', 'wp-hotel-booking' ); ?>"/>
			<input type="hidden" value="<?php echo esc_attr( $from_timestamp ); ?>" name="date-from-timestamp"/>
			<input type="text" id="hb-booking-date-to" class="hb-date-field" value="<?php echo esc_attr( $to ); ?>"
			       name="date-to" readonly placeholder="<?php _e( 'To', 'wp-hotel-booking' ); ?>"/>
			<input type="hidden" value="<?php echo esc_attr( $to_timestamp ); ?>" name="date-to-timestamp"/>
			<select name="filter-type">
				<option value=""><?php _e( 'Filter By', 'wp-hotel-booking' ); ?></option>
				<?php foreach ( $filter_types as $slug => $text ) { ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $slug == $filter_type ); ?>><?php echo esc_html( $text ); ?></option>
				<?php } ?>
			</select>
			<?php
		}
	}
}

if ( ! function_exists( 'hb_schedule_cancel_booking' ) ) {
	/**
	 * Schedule cancel pending booking.
	 *
	 * @param $booking_id
	 */
	function hb_schedule_cancel_booking( $booking_id ) {
		$booking_status = get_post_status( $booking_id );
		if ( $booking_status === 'hb-pending' ) {
			wp_clear_scheduled_hook( 'hotel_booking_change_cancel_booking_status', array( $booking_id ) );
			$time = hb_settings()->get( 'cancel_payment', 12 ) * HOUR_IN_SECONDS;
			wp_schedule_single_event( time() + $time, 'hotel_booking_change_cancel_booking_status', array( $booking_id ) );
		}
	}
}

if ( ! function_exists( 'hb_cancel_booking' ) ) {
	/**
	 * Cancel booking when expired.
	 *
	 * @param $booking_id
	 */
	function hb_cancel_booking( $booking_id ) {
		$booking_status = get_post_status( $booking_id );
		if ( $booking_status === 'hb-pending' ) {
			wp_update_post( array(
				'ID'          => $booking_id,
				'post_status' => 'hb-cancelled'
			) );
		}
	}
}

if ( ! function_exists( 'hb_send_place_booking_email' ) ) {
	/**
	 * Send email for customer and admin when customer places booking.
	 *
	 * @param array $return
	 * @param null $booking_id
	 *
	 * @return bool
	 */
	function hb_send_place_booking_email( $return = array(), $booking_id = null ) {
		if ( ! $booking_id || ! isset( $return['result'] ) || $return['result'] !== 'success' ) {
			return false;
		}

		$booking  = WPHB_Booking::instance( $booking_id );
		$settings = hb_settings();

		// send customer email
		hb_send_customer_booking_email( $booking );

		// send admin email
		if ( $settings->get( 'email_new_booking_enable' ) ) {
			hb_send_admin_booking_email( $booking );
		}

		return true;
	}
}

if ( ! function_exists( 'hb_send_booking_completed_email' ) ) {
	/**
	 * Send email for customer and admin when booking completed.
	 *
	 * @param null $booking_id
	 * @param null $old_status
	 * @param null $new_status
	 *
	 * @return bool
	 */
	function hb_send_booking_completed_email( $booking_id = null, $old_status = null, $new_status = null ) {
		if ( ! $booking_id || ( $new_status && $new_status !== 'completed' ) ) {
			return false;
		}

		$booking  = WPHB_Booking::instance( $booking_id );
		$settings = hb_settings();

		// send customer email
		hb_send_customer_booking_email( $booking, 'booking_completed' );

		// send admin email
		if ( $settings->get( 'email_booking_completed_enable' ) ) {
			hb_send_admin_booking_email( $booking, 'booking_completed' );
		}

		return true;
	}
}

if ( ! function_exists( 'wphb_send_booking_cancelled_email' ) ) {
	/**
	 * @param null $booking_id
	 *
	 * @return bool
	 */
	function wphb_send_booking_cancelled_email( $booking_id = null ) {
		if ( ! $booking_id ) {
			return false;
		}

		$booking = WPHB_Booking::instance( $booking_id );

		// send customer email
		hb_send_admin_booking_email( $booking, 'booking_cancelled' );

		return true;
	}
}
