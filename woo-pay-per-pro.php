<?php
/* 
Plugin Name: Woocommerce Payment Per Products
Description: Woocommerce payment method per Products.
Version: 1.0
Author: Rahul Kumar and Gulshan Naz
Author URI: http://www.indianbusybees.com/
License: GPL
Copyright: IndianBusyBees
*/


/* check woocommerce plugin*/
function woopp_plugin_actievate(){
    // Require woocommerce plugin
    if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) and current_user_can( 'activate_plugins' ) ) {
        // Stop activation redirect and show error
        wp_die('Sorry, but this plugin requires woocommerce plugin to be installed and active. <br><a href="' . admin_url( 'plugins.php' ) . '">&laquo; Return to Plugins</a>');
    }
}
register_activation_hook( __FILE__, 'woopp_plugin_activate' );



/**
 * Add metabox.
 */
add_action( 'add_meta_boxes', 'woopp_meta_boxes', 2 );
function woopp_meta_boxes( $meta_boxes ) {
	$screens = array( 'product');
	foreach ( $screens as $screen ) {
		add_meta_box(
			'paymentdiv',
			__( 'Payment Method', 'woopp' ),
			'woopp_meta_boxes_callback',
			$screen,
			'side'
		);
	}
}

/**
 * Output the HTML for the metabox.
 */
function woopp_meta_boxes_callback() {
	global $woocommerce, $post;
	// Nonce field to validate form request came from current site
	wp_nonce_field( basename( __FILE__ ), 'payment_fields' );
	// Get the location data if it's already been entered
	$paymethod = get_post_meta( $post->ID, 'paymethod', true );
	$paymethod = @explode(",",$paymethod);
	
	$woo = new WC_Payment_Gateways();
    $payments = $woo->payment_gateways;

	// Loop through Woocommerce available payment gateways
	foreach( $payments as $gateway ){
		if($gateway->enabled =="yes"){
			$title = $gateway->get_title();
			if (@in_array($gateway->id, $paymethod)) 
				$checked = " checked";
			else
				$checked = " ";	
			echo "<input type='checkbox' name='paymethod[]' value='".$gateway->id."' ".$checked.">".$title.'<br/>';
		}
	}
}


/**
 * Save the metabox data
 */
function woopp_save_payment_meta( $post_id, $post ) {
	// Return if the user doesn't have edit permissions.
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return $post_id;
	}
	if ($post->post_type != 'product') {
        return $post_id;
    }
	if ( ! wp_verify_nonce( $_POST['payment_fields'], basename(__FILE__) ) ) {
		return $post_id;
	}
	// Verify this came from the our screen and with proper authorization,
	// because save_post can be triggered at other times.
	$pay = sanitize_text_field($_POST['paymethod']);
	if ( ! isset($pay) ) {
		delete_post_meta( $post_id, "paymethod");
		return $post_id;
	}
	if (isset($pay) && ($pay!="")) {
		$paymethod = implode(",",$pay);
		if ( get_post_meta( $post_id, "paymethod", false ) ) {
			update_post_meta( $post_id, "paymethod", $paymethod );
		} else {
			add_post_meta( $post_id, "paymethod", $paymethod);
		}
	} 	
}
add_action( 'save_post', 'woopp_save_payment_meta', 1, 2 );

/*
Payment select
*/
add_filter('woocommerce_available_payment_gateways', 'woopp_conditional_payment_gateways', 10, 1);
function woopp_conditional_payment_gateways( $available_gateways ) {
    // Not in backend (admin)
    if( is_admin() ) 
        return $available_gateways;

    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
        $paymethod = get_post_meta( $cart_item['product_id'], 'paymethod', true );
		if($paymethod!="")
			$pay_method = @explode(",", $paymethod);
	}
    if(sizeof($available_gateways)>0) {
        foreach($available_gateways as $gateways) {
			//echo "TEST";
			//print_r($pay_method);
			if (!@in_array($gateways->id, $pay_method) && (sizeof($pay_method)>0)) {
				unset($available_gateways[$gateways->id]);
			}
		}
	}
    return $available_gateways;
}
?>