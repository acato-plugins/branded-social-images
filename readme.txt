=== Branded Social Images - Open Graph Images with logo and extra text layer ===
Contributors: acato,clearsite
Tags: social image, Open Graph Image, OG Image, OG-image, open graph, open-graph, facebook image, featured image, branded, watermark, logo
Requires at least: 4.7
Tested up to: 6.8.1
Stable tag: 1.1.4
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The simplest way to brand your social images. Provide all your social images (Open Graph images) with your brand en text. In just a few clicks.

== Description ==

# Branded social images (open graph images) in just a few clicks.
This plugin creates branded social images to match with your company’s style. Including a company logo and title.
These open graph images can either be auto-generated for the entire site or you have the option to overrule this per page/post.
# Works with every (public) post-type in WordPress!
# Also tested with WOOCommerce.
# The version 2.0.0 branch even supports taxonomies, so you can brand your category and tag pages as well! Check it out on [GitHub](https://github.com/acato-plugins/branded-social-images "Branded Social Images on GitHub")

== Installation ==

Installation is as easy as
1. In the plugins panel of your website click the "Add New"
2. Find the plugin in the list by typing "Branded Social Images"
3. Click Install and follow up with Activate

Manual installation is almost as easy
1. Click the "Download" button on the right
2. In the plugins panel of your website click the "Add New" and "Upload Plugin"
3. Upload the ZIP downloaded in step 1
4. Activate the plugin.

Please note:
**This plugin requires the GD2 library to be installed**
This is usually the case. If not, contact your hosting company or internet agency.

== Frequently Asked Questions ==

= This plugin seems to be slow in development, is it still maintained? =

Yes, it is. The plugin is maintained by [Acato](https://acato.nl), a Dutch web agency that has taken over the development of this plugin.
The plugin is quite stable and does not need a lot of changes, but we certainly have not abandoned it.

= Can I help with the development? =

Absolutely! The plugin is open source and can be found on [GitHub](https://github.com/acato-plugins/branded-social-images "Branded Social Images on GitHub"). There is a master branch for the current 1.x versions of the plugin and a version-2.0.0 branch. We welcome you to try it out!

= Does this plugin work with third party plugins like Yoast etc.? =

For existing pages/posts, this plugin will take the title from the page as it is created by ANY SEO-plugin, and if you want, you can change it.
And if you have used Yoast-SEO to choose an OG:Image, this plugin will use it as well.

= How do I configure the plugin? =

After installing the plugin, go to the "Branded Social Images" configuration page and set your fallback image, font, -color and -size, add your logo, and you are done.

= How do I add my own fonts? =

To use your own font, you need a .TTF (True Type Font) or .OTF (OpenType Font) file. You can get this from any source you wish, just make sure it is an ordinary font (Google's variable fonts will not work). Upload the font with the "Upload custom font" feature.

= Does the plugin work with international character sets like Kanji (Chinese, Japanese, etc.)? =

Yes, it does, but you have to make sure you upload the appropriate font. If you see "empty square" or "square with a cross" symbols in the image, your font is not compatible.

= Is the plugin MultiSite compatible? =

Yes and no. The plugin can be installed without problems in a MultiSite environment and can be activated per site or network-wide, but the settings are not network-wide.
Although this might be a feature for a future version; there currently is no way to set up Branded Social Images in one single place for the entire network.

= Is the plugin WPML compatible? =

Yes, as long as your font supports the languages. The supplied fonts are "Western" only. If you need character sets for languages like Korean or Japanese, you need to upload an appropriate font.

= Your plugin is not the first I've seen, why bother creating this plugin? =

The plugins we've seen all use an external service to generate the images, or are very complex to use.
We aim for a simple, elegant solution that is completely self-contained.

= Does Branded Social Images use external services? =

For image generation; NO. The plugin is self-contained in that manner.

The plugin does use external services, namely the following, and only once after install or update.
1. Google Fonts - to download a set of sample fonts for you to use (on install or loss of cache folder),
2. Google APIs - to download image conversion software for converting WEBP to PNG so the images can be used by GD2 (on install and on update).

Number 2 might fail silently, resulting in not being able to use WEBP, but this will in no way affect the plugin itself when using PNG and JPEG.

= I am using plugin XYZ for SEO and your plugin does not use the selected image or configured text, what can I do? =

You can set up Branded Social Image on every post/page manually, but an automatic solution is always possible.
You can use WordPress filters to influence the text- and image selection process;
1. Filter `bsi_text` with parameters `$text`, `$post_id` and `$image_id`
2. Filter `bsi_image` with parameters `$image_id` and `$post_id`

= I don't like the plugin to live in the main admin menu. =

With WordPress filter `bsi_admin_menu_location` you can move the entry to the Settings menu. Just return any value other than 'main'.

= I want to use a smaller, sidebar version of the post-meta =

Use filter `bsi_meta_box_context` to change the meta-box position, return either `advanced`, `side` or `normal`.
Beware that the `side` option makes the meta-box very small and with that less usable.
In a classic-editor environment, dragging the metabox to the sidebar (or back) is possible.

= In version 1.0.14, the output format changed from PNG to JPG, now my images don't work anymore. =

While the plugin has code to migrate, obviously in your case it failed. Sorry about that. To remedy the situation, perform 2 steps;
1. Go to your permalinks settings and click the save button. No need to change anything, just go there and hit save.
2. Purge the BSI cache using the purge cache button on the bottom of the BSI settings panel.

= In version 1.0.14, the output format changed from PNG to JPG. I don't want that, can I switch back to PNG? =

Yes you can. use filter `bsi_settings_output_format` to change it; `add_filter('bsi_settings_output_format', function() { return 'png'; });`

= The quality of the output image is not high enough, the output image size is too large. What can I do? =

By changing the quality-level of the output image, you can reduce the filesize, or increase the sharpness of the image.
Use filter `bsi_settings_jpg_quality_level` and return a number between 0 and 100, 100 being the best quality, 75 being the default.
If you set the output to PNG, use filter `bsi_settings_png_compression_level` and return a number from 0 to 9, 2 being the default.

= Can I assist with translating the plugin? =

Absolutely! A .pot template can be found in the [GitHub repository](https://github.com/acato-plugins/branded-social-images "Branded Social Images on GitHub") or you can check the [WordPress translation page](https://translate.wordpress.org/projects/wp-plugins/branded-social-images/)

= Something isn't working properly. Can you help? =

Sure! Go to the support forum and create a new request.
Please include as much information as you feel comfortable with, just make sure you do not reveal information that could lead to unauthorized access to your website.
Do NOT send us your logins, password etc.
If you have problems with a specific image, please generate a log and include it in your support ticket (take the url to the social-image, add ?debug=BSI to it). If you do not see the log on-screen, you can find it in the BSI settings panel.
If you don't want to share this information publicly, send us an e-mail referencing the support ticket.
When in doubt, contact us before sharing.

== Screenshots ==

1. Enrich your social images, automatically.
2. The settings panel.
3. Post-meta

== Changelog ==

= 1.1.4 =
* Fix the title-updater in the BSI editor - in some cases the "empty" title was \uFEFF, a zero-width space character.

= 1.1.3 =
* Fix division by zero error in the rare case that a font file has gone missing.

= 1.1.2 =
* Fix issue with missing admin icon.
* Fix error log spam on PHP8.

= 1.1.1 =
* Fix CVE-2023-28536

= 1.1.0 =
* *Important message*
* [Acato](https://acato.nl) has recently acquired the Web Agency that created this plugin. You will see this name in the plugin code (Clearsite) but this will change in the future.
* This version (1.1.0) will be the last backward compatible version of the plugin, version 2.0.0 will be released sometime this year (probably november) that may or may not break your website.
* Please DO NOT upgrade this plugin on a live, production website to version 2.0.0 before you have done ample testing. You can find a test version on github in the branch 'version-2.0.0'.
* Please note that this test version will NOT be supported via WordPress.org, only on github. If you want to help with the development, make a fork and submit pull-requests.

= 1.0.22 =
* fixed: fatal error due to strong typing of function OGImage::getWidthPixels() ( PHP Fatal error: Uncaught TypeError: Return value of Clearsite\Plugins\OGImage\GD::getWidthPixels() must be of the type int, float returned )

= 1.0.21 =
* fixed: shutdown function not always triggered, leaving a stray lock file after building an image. lock file now removed just before serving the image after building.

= 1.0.20 =
* added: filter `bsi_image_url` added to allow filtering of the final output OG:Image url.

= 1.0.19 =
* fixed: more protection on functions that might not exist and a try/catch does not prevent crashing

= 1.0.17 =
* fixed: editor issues with colors fixed; sprintf for formatting floats was set to locale aware. oops.

= 1.0.16 =
* fixed: in some cases, the {title} placeholder gets stored in the post-meta and appears in the generated image. This should not happen as the {title} placeholder is replaced while editing.
* cleanup: lot of code cleaned up, comments added
* removed: some code suggested support for webp output (instead of png or jpg) but as Facebook and LinkedIn (and probably more) platforms do not support webp for og:image, no reason to try to generate it.

= 1.0.15 =
* fixed: some users report "function mime_content_type does not exist". This function should exist and indicates a broken/misconfigured server. To accommodate, fallback functions are in place.

= 1.0.14 =
* warning: Only install this version if your host has the "magic mime" extension properly configured (function mime_content_type exists)
* important change: Switch from PNG output to JPG output. The reason is disk-space usage; the JPG takes only a fraction of the disk space and has practically the same quality. See the FAQ for more information.
* fixed: showing debug information leaves image cache in locked state, preventing (re-)generation of image.
* improved: interface will now scale on small displays.
* changed: for new posts, title is automatically filled based on '{title} - {blogname}'.
* changed: overhaul of javascript and style.
* added: @developers; For debugging, set BSI_UNMINIFIED to true. Script and style will be more readable.
* added: Last generated debug log (with ?debug=BSI on image url) will be shown in the admin panel. If you have problems with an image, please include this information in your support ticket.

= 1.0.13 =
* fixed: in WPML folder-per-language installation, the language folder is duplicated in the social-image-url
* fixed: some themes make all links relative, this breaks the admin-bar link to OpenGraph.xyz
* changed: action `bsi_image_gd` now includes two extra parameters: $post_id and $image_id
* added: action `bsi_image_editor` with 1 parameter; $stage, in 4 stages of displaying the editor (`after_creating_canvas`, `after_adding_background`, `after_adding_text` and `after_adding_logo`) for future expansion of the editor

= 1.0.12 =
* added: interface now recognises RankMath image selection
* added: action `bsi_image_gd` with 2 parameters; &$resource and $stage, in 4 stages of the image generation (`after_creating_canvas`, `after_adding_background`, `after_adding_text` and `after_adding_logo`)
* fixed: replace image with BSI in RankMath LD+JSON
* fixed: BSI image url not always used by RankMath
* added: debug information for administrators on url.to/post/social-image.png/?debug=BSI
* added: admin option to purge BSI cache

= 1.0.11 =
* improved: Meta panel improved layout
* improved: interface texts and elements made more clear
* fixed: oembed still showed a social image even if none was available

= 1.0.10 =
* updated: translations updated.
* added: filter bsi_admin_menu_location to change the menu-position (main for main-menu, any other value moves Branded Social Images to the Settings menu).
* added: filter bsi_meta_box_context to change the meta-box position (either 'advanced', 'side' or 'normal'). Beware that the 'side' option makes the meta-box very small and with that less usable.

= 1.0.9 =
* added: oEmbed data is now also patched to use the correct OG:Image. This will please Facebook and LinkedIn.
* changed: set og:image url to a full URL instead of a relative one. Facebook made a big deal of this.
* fixed: Selecting a featured image shows it in the Meta-box interface, but now also in the correct position.

= 1.0.8 =
* removed: removed scraping title/og:title by using wp_head(). It often does not work or even reports wrong data. how? why? don't know.
* fixed: scraping title/og:title was broken in meta panel, displaying wrong title

= 1.0.7 =
* fixed: scraping of og:title was broken

= 1.0.6 =
* fixed: meta box not showing in classic editor

= 1.0.5 =
* fixed: title detection does not always work.
* fixed: custom post types now supported without implementing a filter
* added: support for post-type-archives; fallback image and text are used. (For developers: implement filter bsi_text if you want something else)
* rebuilt: visual text editor did not respond well to pasting text and "undo" did not work. editor has been rebuilt. please report issues using the support tab.

= 1.0.4 =
* added: protection against font-stealing; the BSI Storage directory is now protected from outside access.
* fixed: missing text-background layer in post-meta interface
* removed: cache-debugging removed. BSI image cache is cleared when a post is saved. (For developers: You can bypass caching with WP_DEBUG enabled)

= 1.0.3 =
* fixed: html entities in scraped title decoded
* fixed: php error regarding non-static method called statically

= 1.0.2 =
* fixed: url to support page
* added: support homepage when it is not a page but the index of all posts ( is_home() )

= 1.0.1 =
* fixed: dummy logo showing up on post-meta
* fixed: title not detected when html-attributes are used in the title-tag
* fixed: urls in plugin meta data corrected

= 1.0.0 =
* First public release to wordpress.org

= 0.1.0 =
Last tweaks and polish before shipping to WordPress

= 0.0.9 =
* more code cleanup
* more code documentation
* font-rendering tweak 'text-area-width' is now a factor instead of a new value.

= 0.0.8 =
* code cleanup
* code documentation

= 0.0.7 =
* Added .otf support

= 0.0.6 =
* Bugfixes and changes as per brainstorm session october 2021
* First round of code-hardening
* New icon

= 0.0.4 =
* Bugfixes per third user-test

= 0.0.3 =
* Many interface changes to reflect the results of the second user-test

= 0.0.2 =
* Major overhaul of all code
* Renaming everything to reflect the chosen name of the plugin: Branded Social Images

= 0.0.1 =
* Proof of concept

== Upgrade Notice ==

= 0.0.2 =
This version renames all option keys and is therefore not backward compatible.

= 0.0.1 =
Internal proof of concept
