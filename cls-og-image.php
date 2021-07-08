<?php
/**
 * Plugin Name: Branded OpenGraph image - Social image optimized
 * Description: Spice up your Social Open Graph Images to be meaningful.
 * Plugin URI: https://clearsite.nl/plugin/cls-og-image
 * Author: Internetbureau Clearsite, Remon Pel, Merlijn Ackerstaff, Gijs van Arem
 * Author URI: https://www.clearsite.nl
 * Version: 0.0.1
 * License: GPL2
 * Text Domain: cls-og-image
 * Domain Path: lang/
 */

namespace Clearsite\Plugins\OGImage;

require_once 'lib/class.og-image-plugin.php';

add_action('plugins_loaded', [Plugin::class, 'init']);

//add_filter('cls_og_text', function($text, $post_id, $image_id, $type) {
//	return "Case:\nBetaalvereniging.nl";
//}, 10, 4);
