<?php
/*
Plugin Name:  Dynasun Shipping
Plugin URI:   https://channelister.com
Description:  Dynasun Shipping Management for woocommerce
Version:      1.0
Author:       Alok Singh 
Author URI:   https://channelister.com
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  dynasun-shipping
Domain Path:  /languages
*/

define('DYNASUN_VERSION', 1.0);

class DynaSun{

    public function __construct(){
        add_action( 'admin_notices', array( $this, 'check_for_woocommerce' ));
        add_action( 'add_meta_boxes', array( $this, 'dynasun_shipping_add_meta_boxes' ));
        add_action( 'save_post', array( $this, 'dynasun_save_wc_order_shipping_fields' ), 10, 1 );
        add_action( 'rest_api_init', array( $this, 'dynasun_rest_api_register_endpoint' ), 10, 1 );     
    }

    public function check_for_woocommerce(){
        if (!defined('WC_VERSION')) {
            echo '<div class="notice notice-error">
                    <p>DynaSun Shipping plugin reqires woocommerce plugin to install.</p>
                </div>';
        }
    }

    public function dynasun_shipping_add_meta_boxes(){
        add_meta_box( 'mv_other_fields', __('DynaSun shipping info','woocommerce'), array( $this, 'dynasun_add_other_fields_for_shipping'), 'shop_order', 'side', 'core' );
    }
 
 
    public function dynasun_add_other_fields_for_shipping(){
        global $post;

        $tracking_carrier = get_post_meta( $post->ID, 'tracking_carrier', true ) ? get_post_meta( $post->ID, 'tracking_carrier', true ) : '';
        $tracking_number = get_post_meta( $post->ID, 'tracking_number', true ) ? get_post_meta( $post->ID, 'tracking_number', true ) : '';
        $ship_date = get_post_meta( $post->ID, 'ship_date', true ) ? get_post_meta( $post->ID, 'ship_date', true ) : '';
        $trackingUrl = get_post_meta( $post->ID, 'tracking_url', true ) ? get_post_meta( $post->ID, 'tracking_url', true ) : '';

        echo '<input type="hidden" name="dynasun_shpping_field_nonce" value="' . wp_create_nonce() . '">
        <p>Tracking Carrier
            <input type="text" style="width:250px;" name="tracking_carrier" placeholder="" value="' . $tracking_carrier . '"></p>

        <p>Tracking number
            <input type="text" style="width:250px;" name="tracking_number" placeholder="" value="' . $tracking_number . '"></p>';
        
        if($ship_date){
            echo '<p>Ship Date
            <input type="text" style="width:250px;" readonly="true" name="ship_date" placeholder="" value="' . date('Y-m-d h:i:s A', strtotime($ship_date)) . '"></p>';
        }
        if($trackingUrl){
            echo '<a href="'.$trackingUrl.'" class="button" target="_blank">Track</a>';
        }

    }
 
    public function dynasun_save_wc_order_shipping_fields( $post_id ){

        // We need to verify this with the proper authorization (security stuff).

        // Check if our nonce is set.
        if ( ! isset( $_POST[ 'dynasun_shpping_field_nonce' ] ) ) {
            return $post_id;
        }
        $nonce = $_REQUEST[ 'dynasun_shpping_field_nonce' ];

        //Verify that the nonce is valid.
        if ( ! wp_verify_nonce( $nonce ) ) {
            return $post_id;
        }

        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return $post_id;
        }

        // Check the user's permissions.
        if ( 'page' == $_POST[ 'post_type' ] ) {

            if ( ! current_user_can( 'edit_page', $post_id ) ) {
                return $post_id;
            }
        } else {

            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                return $post_id;
            }
        }
        // --- Its safe for us to save the data ! --- //

        // Sanitize user input  and update the meta field in the database.
        update_post_meta( $post_id, 'tracking_carrier', $_POST[ 'tracking_carrier' ] );
        update_post_meta( $post_id, 'tracking_number', $_POST[ 'tracking_number' ] );
    }

    public function dynasun_rest_api_register_endpoint(){
        register_rest_route( 'ds/v1', 'create_shipment/(?P<orderId>\d+)', array(
            'methods' => 'POST',
            'callback' => array($this, 'dynasun_rest_api_handle_request'),
            'args' => array(
                'orderId' => array(
                  'validate_callback' => function($param, $request, $key) {
                    return is_numeric( $param );
                  }
                )
          ) ));
    }

    public function dynasun_rest_api_handle_request(WP_REST_Request $request ){
        // You can access parameters via direct array access on the object:
        $order = wc_get_order( $request->get_param( 'orderId' ));

        if ( empty( $order ) ) {
            return new WP_Error( 'no_order', 'Invalid order', array( 'status' => 404 ) );
        }

        if($request->get_param( 'carrier' ) == ''){
            return new WP_Error( 'no_carrier', 'Carrier is required.', array( 'status' => 400 ) );
        }

        if($request->get_param( 'trackingNumber' ) == ''){
            return new WP_Error( 'no_trackingNumber', 'Trracking number is required.', array( 'status' => 400 ) );
        }

        if($request->get_param( 'shipDate' ) == ''){
            return new WP_Error( 'no_shipDate', 'ship Date is required.', array( 'status' => 400 ) );
        }

        update_post_meta( $order->get_id(), 'tracking_carrier', $request->get_param( 'carrier' ) );
        update_post_meta( $order->get_id(), 'tracking_number', $request->get_param( 'trackingNumber' ) );
        update_post_meta( $order->get_id(), 'ship_date', $request->get_param( 'shipDate' ) );
        update_post_meta( $order->get_id(), 'tracking_url', $request->get_param( 'trackingUrl' ) );
        
        $note = "Shipping carrier added with {$request->get_param( 'carrier' )} and tracking number {$request->get_param( 'trackingNumber' )}";

        // Add the note
        $order->add_order_note( $note );

        return new WP_Error( 'success', 'Shipping details added.', array( 'status' => 200 ) );
    }
 
}

new DynaSun();