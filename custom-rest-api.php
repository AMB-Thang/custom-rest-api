<?php
/**
 * Plugin Name:  Rest Api Custom - amoeba
 * Plugin URI: https://amoeba.com/
 * Description: 11Floating click to contact buttons All-In-One
 * Version: 1.0.5
 * Author: amoeba.com
 * Author URI: https://amoeba.com
 * Text Domain: amoeba-aio-ct-button
 * Requires PHP: 5.6
 */

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

add_action('woocommerce_api_loaded', function () {
    include_once('class-wc-api-custom.php');
});

add_filter('woocommerce_api_classes', function ($classes) {
    $classes[] = 'WC_API_Custom';
    return $classes;
});




