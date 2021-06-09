<?php
/**
 * Plugin Name: OG-Image (by Clearsite)
 * Description: Spice up your OG-Images to be meaningful.
 * Plugin URI: https://clearsite.nl/plugin/cls-og-image
 * Author: Internetbureau Clearsite, Remon Pel, Merlijn Ackerstaff, Gijs van Arem
 * Author URI: https://www.clearsite.nl
 * Version: 0.0.1
 * License: GPL2
 * Text Domain: cls-og-image
 * Domain Path: lang/
 */

/*
    Copyright (C) 2021  Internetbureau Clearsite, Remon Pel, Gijs van Arem, Merlijn Ackerstaff  cls-og-image@clearsite.nl

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

namespace Clearsite\Plugins\OGImage;

require_once 'lib/class.og-image-plugin.php';

add_action('plugins_loaded', [Plugin::class, 'init']);

//add_filter('cls_og_text', function($text, $post_id, $image_id, $type) {
//	return "Case:\nBetaalvereniging.nl";
//}, 10, 4);
