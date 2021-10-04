<?php
/**
 * Plugin Name: Branded Social Images
 * Description: Spice up your OpenGraph Social Images to be meaningful.
 * Plugin URI: https://clearsite.nl/plugin/branded-social-images
 * Author: Internetbureau Clearsite
 * Author URI: https://www.clearsite.nl
 * Version: 0.0.3
 * License: GPL2
 */

namespace Clearsite\Plugins\OGImage;

require_once __DIR__ . '/lib/class.og-image-plugin.php';
require_once __DIR__ . '/lib/class.og-image-admin.php';

add_action('plugins_loaded', [Plugin::class, 'init']);

// short term migration
add_action('plugins_loaded', function(){
	if (get_option('bsi_version', 1) < 1) {
		global $wpdb;
		try {
			$wpdb->query("UPDATE {$wpdb->postmeta} SET meta_key = REPLACE(meta_key, 'cls_og_', 'bsi_')");
			$wpdb->query("UPDATE {$wpdb->options} SET option_name = REPLACE(option_name, 'cls_og_', 'bsi_')");
			$wpdb->query("UPDATE {$wpdb->options} SET option_name = REPLACE(option_name, 'cls_default_og_', 'bsi_default_')");
		} catch (\Exception $e) {

		}
		update_option('bsi_version', 1);
	}
}, ~PHP_INT_MAX);

/**
 * This will fix the "You are not allowed to upload to this post" error when in admin settings.
 * This only happens sometimes, but once it happens, it keeps happening.
 */
add_action('check_ajax_referer', function($action){
	if ('media-form' === $action && !empty($_REQUEST['action']) && 'upload-attachment' === $_REQUEST['action'] && isset($_REQUEST['post_id']) && empty($_REQUEST['post_id'])) {
		unset($_REQUEST['post_id']);
	}
});

/**
 * Reference list
 * @see https://www.cssscript.com/color-picker-alpha-selection/
 */
