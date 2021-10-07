<?php
/**
 * Plugin Name: Branded Social Images
 * Description: The simplest way to brand your social images. Provide all your social images (Open Graph Images) with your brand en text. In just a few clicks.
 * Plugin URI: https://clearsite.nl/plugin/branded-social-images
 * Author: Internetbureau Clearsite
 * Author URI: https://www.clearsite.nl
 * Version: 0.0.5
 * License: GPL2
 */

use Clearsite\Plugins\OGImage\Plugin;
define('BSI_PLUGIN_FILE', __FILE__);

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
 * On plugin activation, register a flag to rewrite permalinks.
 * THe plugin will do so after adding the post-endpoint.
 */
register_activation_hook( __FILE__, 'bsi_plugin_activation' );
function bsi_plugin_activation($network_wide) {
	global $wpdb;
	update_option( "bsi_needs_rewrite_rules", true );
	if ($network_wide && function_exists('is_multisite') && is_multisite()) {
		$blog_ids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->base_prefix}blogs");
		foreach ($blog_ids as $blog_id) {
			switch_to_blog($blog_id);
			update_option( "bsi_needs_rewrite_rules", true );
			restore_current_blog();
		}
	}
}

/**
 * Reference list
 * @see https://www.cssscript.com/color-picker-alpha-selection/
 */
