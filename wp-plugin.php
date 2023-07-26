<?php
/**
 * Plugin Name: Branded Social Images
 * Description: The simplest way to brand your social images. Provide all your social images (Open Graph Images) with your brand en text. In just a few clicks.
 * Plugin URI: https://github.com/clearsite/branded-social-images
 * Author: Acato
 * Author URI: https://acato.nl
 * Version: 1.1.3
 * License: GPL2
 */

/**
 * Note from the developers.
 *
 * We know the plugin code is not perfect. There is a lot of room for improvement, but in our
 * enthusiasm to share this with you, we could not wait for everything to be polished.
 *
 * We hope you like it and please, encourage others to use the plugin as well.
 *
 * Found bugs? Need help?
 * Please visit the WordPress support page;
 * @see: https://wordpress.org/support/plugin/branded-social-images/
 *
 * The code ain't pretty. I know.
 * Want to help clean it up?
 * Want to help improve?
 *
 * Please visit the GitHub page for this plugin;
 * @see: https://github.com/clearsite/branded-social-images/
 */

/**
 * Feature wishlist:
 * 1. Better settings handler and all settings filterable for mass rollout or embedding in themes
 * 2. Positioning of text and logo as well as logo scaling and font-size done "on image"
 * 3. Title builder (like Yoast SEO)
 * 4. ImageMagick support
 * 5. With IM; svg and webp support
 * 6. Code refactoring
 */

use Clearsite\Plugins\OGImage\Plugin;

define('BSI_PLUGIN_FILE', __FILE__);

require_once __DIR__ . '/lib/inc.functions.php';
require_once __DIR__ . '/lib/class.og-image-plugin.php';
require_once __DIR__ . '/lib/class.og-image-admin.php';

add_action('plugins_loaded', [Plugin::class, 'init']);

/**
 * This will fix the "You are not allowed to upload to this post" error when in admin settings.
 * This only happens occasionally, most often on Gutenberg enabled WP sites, but once it happens, it keeps happening.
 */
add_action('check_ajax_referer', function ($action) {
	if ('media-form' === $action && !empty($_REQUEST['action']) && 'upload-attachment' === $_REQUEST['action'] && isset($_REQUEST['post_id']) && empty($_REQUEST['post_id'])) {
		unset($_REQUEST['post_id']);
	}
});

/**
 * plugin activation/deactivation/uninstall hooks
 */
register_activation_hook(BSI_PLUGIN_FILE, [Plugin::class, 'on_activation']);
register_deactivation_hook(BSI_PLUGIN_FILE, [Plugin::class, 'on_deactivation']);
register_uninstall_hook(BSI_PLUGIN_FILE, [Plugin::class, 'on_uninstall']);

/**
 * Reference list
 * @see https://www.cssscript.com/color-picker-alpha-selection/
 */
