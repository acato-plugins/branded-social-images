=== Branded Social Images ===
Contributors: clearsite
Tags: social images, OG-image, open-graph, featured image
Requires at least: 4.7
Tested up to: 5.8.1
Stable tag: 1.0.0
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The simplest way to brand your social images. Provide all your social images (Open Graph images) with your brand en text. In just a few clicks.

== Description ==

# Rich social images with just a few clicks.
This plugin creates rich social images to match with your company’s style. Including a company logo and title.
The images can either be auto-generated for the entire site or you have the option to overrule this per page/post.
# Works with every post-type in WordPress!
For more information, visit our [website](https://clearsite.nl/branded-social-images/ "Our webpage about Branded Social Images")

== Frequently Asked Questions ==

= Does this plugin work with third party plugins like Yoast etc.? =

This plugin will take the title from the page as it is created by ANY SEO-plugin, and if you want, you can change it. E.g. If you have used Yoast-SEO to choose an OG:Image, this plugin will use it as well.

= How do I configure the plugin? =

After installing the plugin, go to the "Branded social images" configuration page and set your fallback image, font, -color and -size, add your logo, and you are done.

= How do I add my own fonts? =

To use your own font, you need a .TTF (True Type Font) or .OTF (OpenType Font) file. You can get this from any source you wish, just make sure it is an ordinary font (Google's variable fonts will not work). Upload the font with the "Upload custom font" feature.

= Does the plugin work with international character sets like Kanji (Chinese, Japanese, etc.)? =

Yes, it does, but you have to make sure you upload the appropriate font. If you see "empty square" or "square with a cross" symbols in the image, your font is not compatible.

= Is the plugin MultiSite compatible? =

Yes and no. The plugin can be installed without problems in a MultiSite environment and can be activated per site or network-wide, but the settings are not network-wide.
Although this might be a feature for a future version; there currently is no way to set-up Branded Social Images in one single place for the entire network.

= Is the plugin WPML compatible? =

Yes, as long as your font supports the languages. The supplied fonts are "Western" only. If you need character sets for languages like Korean or Japanese, you need to upload an approriate font.

= I am using plugin XYZ for SEO and your plugin does not use the selected image or configured text, what can I do? =

You can always set-up Branded Social Image on every post/page manually, but an automatic solution is always possible.
You can use WordPress filters to influence the text- and image selection process;
1. Filter `bsi_text` with parameters `$text`, `$post_id` and `$image_id`
1. Filter `bsi_image` with parameters `$image_id` and `$post_id`

== Screenshots ==

1. Enrich your social images, automatically.
2. The settings panel.
3. Post-meta

== Changelog ==

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
