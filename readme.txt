=== Branded Social Images ===
Contributors: clearsite
Tags: social images, OG-image, open-graph, featured image
Requires at least: 4.7
Tested up to: 5.8.1
Stable tag: 1.0.12
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The simplest way to brand your social images. Provide all your social images (Open Graph images) with your brand en text. In just a few clicks.

== Description ==

# Rich social images with just a few clicks.
This plugin creates rich social images to match with your company’s style. Including a company logo and title.
The images can either be auto-generated for the entire site or you have the option to overrule this per page/post.
# Works with every (public) post-type in WordPress!
For more information, visit our [website](https://clearsite.nl/branded-social-images/ "Our webpage about Branded Social Images")

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
Although this might be a feature for a future version; there currently is no way to set-up Branded Social Images in one single place for the entire network.

= Is the plugin WPML compatible? =

Yes, as long as your font supports the languages. The supplied fonts are "Western" only. If you need character sets for languages like Korean or Japanese, you need to upload an approriate font.

= Your plugin is not the first I've seen, why bother creating this plugin? =

The plugins we've seen all use an external service to generate the images, or are very complex to use.
We aim for a simple, elegant solution that is completely self-contained.

= Does Branded Social Images use external services? =

For image generation; NO. The plugin is self-contained in that manner.

The plugin does use external services, namely the following, and only once after install or update.
1. Google Fonts - to download a set of sample fonts for you to use (on install or loss of cache folder),
2. Google APIs - to download image conversion software for converting WEBP to PNG so the images can be used by GD2 (on intall and on update).

Number 2 might fail silently, resulting in not being able to use WEBP, but this will in no way affect the plugin itself when using PNG and JPEG.

= I am using plugin XYZ for SEO and your plugin does not use the selected image or configured text, what can I do? =

You can set-up Branded Social Image on every post/page manually, but an automatic solution is always possible.
You can use WordPress filters to influence the text- and image selection process;
1. Filter `bsi_text` with parameters `$text`, `$post_id` and `$image_id`
2. Filter `bsi_image` with parameters `$image_id` and `$post_id`

= I don't like the plugin to live in the main admin menu. =

With WordPress filter `bsi_admin_menu_location` you can move the entry to the Settings menu. Just return any value other than 'main'.

= I want to use a smaller, sidebar version of the post-meta =

Use filter `bsi_meta_box_context` to change the meta-box position, return either `advanced`, `side` or `normal`.
Beware that the `side` option makes the meta-box very small and with that less usable.
In a classic-editor environment, dragging the metabox to the sidebar (or back) is possible.

= Can I assist with translating the plugin? =

Absolutely! A .pot template can be found in the [GitHub repository](https://github.com/clearsite/branded-social-images "Branded Social Images on GitHub")

== Screenshots ==

1. Enrich your social images, automatically.
2. The settings panel.
3. Post-meta

== Changelog ==

= 1.0.13 =
* fixed: showing debug information leaves image cache in locked state, preventing (re-)generation of image.
* improved: interface will now scale on small displays.
* changed: for new posts, title is automatically filled based on '{title} - {blogname}'.
* changed: overhaul of javascript and style.
* added: @developers; For debugging, set BSI_UNMINIFIED to true. Script and style will be more readable.

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
