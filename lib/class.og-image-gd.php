<?php

namespace Clearsite\Plugins\OGImage;

defined('ABSPATH') or die('You cannot be here.');

use GDText\Box;
use GDText\Color;

require_once __DIR__ . '/../vendor/autoload.php';

class GD
{
	private $handler;
	private $manager;

	private $source;
	private $source_is_temporary;
	private $target;

	private $line_height_factor = 1;
	private $text_area_width;

	private $resource;

	public function __construct(Image $handler, $source, $target)
	{
		$this->handler = $handler;
		$this->manager = $handler->getManager();
		$this->source_is_temporary = false;
		$this->text_area_width = Plugin::TEXT_AREA_WIDTH;

		// use this construction, so we don't have to check file mime
		if (is_file($source) && preg_match('@\.webp$@', strtolower(trim($source)))) {
			// do we have webp support?
			$support = function_exists('imagewebp');

			if (!$support) {
				// we cannot support natively
				$support = Plugin::maybe_fake_support_webp();
				if ($support) {
					// we fake support, so we need to convert the input image to PNG
					$source = Plugin::convert_webp_to_png($source);
					$this->source_is_temporary = true;
				}
			}
		}

		$this->source = $source;
		$this->target = $target;

		$baseImage = imagecreatefromstring(file_get_contents($this->source));
		$w = $this->manager->width * Plugin::AA;
		$h = $this->manager->height * Plugin::AA;
		$this->resource = imagecreatetruecolor($w, $h);

		do_action_ref_array('bsi_image_gd', [&$this->resource, 'after_creating_canvas', $this->handler->post_id, $this->handler->image_id]);

		imagealphablending($this->resource, true);
		imagesavealpha($this->resource, true);

		$source_width = imagesx($baseImage);
		$source_height = imagesy($baseImage);

		// just in time cropping

		$target_ratio = $h / $w;
		$source_ratio = $source_height / $source_width;
		$source_x = $source_y = 0;
		if ($source_ratio > $target_ratio) { // image is too high
			$source_height = $source_width * $target_ratio;
			$source_y = (imagesy($baseImage) - $source_height) / 2;
		}
		if ($source_ratio < $target_ratio) { // image is too wide
			$source_width = $source_height / $target_ratio;
			$source_x = (imagesx($baseImage) - $source_width) / 2;
		}

		imagecopyresampled($this->resource, $baseImage, 0, 0, $source_x, $source_y, $w, $h, $source_width, $source_height);
		imagedestroy($baseImage);
		if ($this->source_is_temporary) {
			@unlink($this->source);
		}

		do_action_ref_array('bsi_image_gd', [&$this->resource, 'after_adding_background', $this->handler->post_id, $this->handler->image_id]);
	}

	public function text_overlay($textOptions, $text)
	{
		$image_width = imagesx($this->resource); // already is Plugin::AA
		$image_height = imagesy($this->resource);

		$text_color = $this->manager->hex_to_rgba($textOptions['color'], true);
		$text_color = new Color($text_color[0], $text_color[1], $text_color[2], $text_color[3]);

		$background_color = false;
		if ('on' === $textOptions['background-enabled'] && $textOptions['background-color']) {
			$background_color_rgba = $this->manager->hex_to_rgba($textOptions['background-color'], true);
			if ($background_color_rgba[3] < 127) { // not 100% transparent
				//		var_dump($background_color);exit;
				$background_color = imagecolorallocatealpha($this->resource, $background_color_rgba[0], $background_color_rgba[1], $background_color_rgba[2], $background_color_rgba[3]);
				imagecolortransparent($this->resource, $background_color);
			}
		}
		else {
			$textOptions['padding'] = 0;
		}

		$text_shadow_color = false;
		if ($textOptions['text-shadow-color']) {
			$text_shadow_color_rgba = $this->manager->hex_to_rgba($textOptions['text-shadow-color'], true);
			if ($text_shadow_color_rgba[3] < 127) { // not 100% transparent
				$text_shadow_color = new Color($text_shadow_color_rgba[0], $text_shadow_color_rgba[1], $text_shadow_color_rgba[2], $text_shadow_color_rgba[3]);
			}
		}

		$text_stroke_color = false;
		if ($textOptions['text-stroke-color']) {
			$text_stroke_color_rgba = $this->manager->hex_to_rgba($textOptions['text-stroke-color'], true);
			if ($text_stroke_color_rgba[3] < 127) { // not 100% transparent
				$text_stroke_color = new Color($text_stroke_color_rgba[0], $text_stroke_color_rgba[1], $text_stroke_color_rgba[2], $text_stroke_color_rgba[3]);
			}
		}
		$font = $textOptions['font-file'];
		Plugin::log("Text overlay: using font: $font");
		$tweaks = Plugin::font_rendering_tweaks_for($font, 'gd');
		if ($tweaks) {
			if (!empty($tweaks['line-height'])) {
				$this->line_height_factor = $tweaks['line-height'];
			}
			if (!empty($tweaks['text-area-width'])) {
				$this->text_area_width = $tweaks['text-area-width'] * $this->text_area_width;
			}
		}
		$fontSize = $textOptions['font-size'] * Plugin::AA;
		if (!trim($text)) {
			$background_color = false;
		}

		$text = str_replace(['<br>', '<br />', '<br/>', '\\n'], "\n", $text); // predefined line breaks
		$text = str_replace('\\r', '', $text);
		$text = $this->wrapTextByPixels($text, $image_width * $this->text_area_width, $fontSize, $font);

		$textDim = imagettfbbox($fontSize, 0, $font, implode(' ', explode("\n", $text . 'Hj'))); // Hj is to make sure the correct line-height is calculated.
		$line_height = $textDim[1] - $textDim[7];

		$lines = explode("\n", $text);
		$text_width = 0;
		foreach ($lines as $line) {
			$textDim = imagettfbbox($fontSize, 0, $font, $line);
			$text_width = max($text_width, $textDim[2] - $textDim[0]);
		}
		$text_width += 2;
		$line_height *= $this->line_height_factor;
		$text_height = $line_height * count($lines);

		$p = $textOptions['padding'] * Plugin::AA;

		$textOptions['left'] *= Plugin::AA;
		$textOptions['right'] *= Plugin::AA;
		$textOptions['top'] *= Plugin::AA;
		$textOptions['bottom'] *= Plugin::AA;

		$text_posX = $text_posY = 0;
		if ($textOptions['halign'] == 'center') {
			$text_posX = ($image_width - $textOptions['left'] - $textOptions['right']) / 2 - $text_width / 2 + $textOptions['left'];
		}
		if ($textOptions['halign'] == 'right') {
			$text_posX = $image_width - $textOptions['right'] - $text_width - $p;
		}
		if ($textOptions['halign'] == 'left') {
			$text_posX = $textOptions['left'] + $p;
		}

		if ($textOptions['valign'] == 'center') {
			$text_posY = ($image_height - $textOptions['top'] - $textOptions['bottom']) / 2 - $text_height / 2 + $textOptions['top'];
		}
		if ($textOptions['valign'] == 'bottom') {
			$text_posY = $image_height - $textOptions['bottom'] - ($text_height / .75) - $p;
		}
		if ($textOptions['valign'] == 'top') {
			$text_posY = $textOptions['top'] + $p;
		}

		// text-background
		if (false !== $background_color) {
			if ('inline' === $textOptions['display']) {
				//  /.75 points to pixels
				imagefilledrectangle($this->resource, $text_posX - $p, $text_posY - $p, $text_posX + $text_width + $p, $text_posY + ($text_height / .75) + $p, $background_color);
			}
		}
		// NOTE: imagettf uses Y position for bottom!! of the text, not the top
		// ALSO: this is for the text BASE, so some text might stick out below. compensate by 18% of text height.
		if (false !== $text_shadow_color) {
			$shiftX = $textOptions['text-shadow-left'] * Plugin::AA;
			$shiftY = $textOptions['text-shadow-top'] * Plugin::AA;
			$steps = max(absint($shiftX), absint($shiftY));
			$start_color = $textOptions['color'];
			$end_color = $textOptions['text-shadow-color'];
			if ('open' === $textOptions['text-shadow-type']) {
				$steps = 1;
			}
			if ('solid' === $textOptions['text-shadow-type']) {
				$start_color = $end_color;
			}

			for ($step = $steps; $step > 0; $step-- /* skip step 0 as it is the text-position */) {
				$shiftX_step = self::gradient_value(0, $shiftX, $step, $steps);
				$shiftY_step = self::gradient_value(0, $shiftY, $step, $steps);
				$text_shadow_color_step = $this->gradient_color($start_color, $end_color, $step, $steps, true);
				$text_shadow_color_step = $this->manager->hex_to_rgba($text_shadow_color_step, true);
//				$text_shadow_color_step = imagecolorallocatealpha($this->resource, $text_shadow_color_step[0], $text_shadow_color_step[1], $text_shadow_color_step[2], $text_shadow_color_step[3]);
				$text_shadow_color_step = new Color($text_shadow_color_step[0], $text_shadow_color_step[1], $text_shadow_color_step[2], $text_shadow_color_step[3]);
//				imagecolortransparent($this->resource, $text_shadow_color_step);
//				imagettftext($this->resource, $fontSize, 0, $text_posX + $shiftX_step, $text_posY + $shiftY_step + $line_height - .2*$line_height , $text_shadow_color_step, $font, $text);
				$this->imagettftextbox($this->resource, $fontSize, $text_posX + $shiftX_step, $text_posY + $shiftY_step, $text_width, $text_height, $text_shadow_color_step, $font, $text, ['align' => $textOptions['halign']]);
			}
		}

//		if (false !== $text_stroke_color) {
//			$this->imagettftextbox_stroke($this->resource, $fontSize, $text_posX, $text_posY, $text_width, $text_height,
//				$text_stroke_color, $font, $text, $textOptions['text-stroke'], $textOptions['halign']);
//		}
//		imagettftext($this->resource, $fontSize, 0, $text_posX, $text_posY + $line_height - .2*$line_height , $text_color, $font, $text);
		$this->imagettftextbox($this->resource, $fontSize, $text_posX, $text_posY, $text_width, $text_height, $text_color, $font, $text, [
			'align' => $textOptions['halign'],
			'stroke_width' => $textOptions['text-stroke'],
			'stroke_color' => $text_stroke_color,
		]);

		do_action_ref_array('bsi_image_gd', [&$this->resource, 'after_adding_text', $this->handler->post_id, $this->handler->image_id]);
	}

	private function gradient_color($hex_rgba_start, $hex_rgba_end, $step, $steps = 100, $skip_alpha = false, $return_as_hex = true)
	{
		$hex_rgba_start_rgba = $this->manager->hex_to_rgba($hex_rgba_start);
		$hex_rgba_end_rgba = $this->manager->hex_to_rgba($hex_rgba_end);
		if ($skip_alpha) {
			if (is_bool($skip_alpha)) {
				$skip_alpha = $hex_rgba_end;
			}
			$skip_alpha = $this->manager->hex_to_rgba($skip_alpha);
			$hex_rgba_start_rgba[3] = $hex_rgba_end_rgba[3] = $skip_alpha[3];
		}

		$gradient_hex_rgba = [
			self::gradient_value($hex_rgba_start_rgba[0], $hex_rgba_end_rgba[0], $step, $steps),
			self::gradient_value($hex_rgba_start_rgba[1], $hex_rgba_end_rgba[1], $step, $steps),
			self::gradient_value($hex_rgba_start_rgba[2], $hex_rgba_end_rgba[2], $step, $steps),
			self::gradient_value($hex_rgba_start_rgba[3], $hex_rgba_end_rgba[3], $step, $steps),
		];

		return $return_as_hex ? $this->manager->rgba_to_hex($gradient_hex_rgba) : $gradient_hex_rgba;
	}

	private static function gradient_value($start, $end, $step, $steps = 100)
	{
		$percentage = $step / $steps;
		$value_range = $end - $start;

		return $value_range * $percentage + $start;
	}

	public function logo_overlay($logoOptions)
	{
		Plugin::log('Overlay: logo');
		if (!$logoOptions['file']) {
			Plugin::log('Logo overlay: ( no logo )');

			return;
		}
		$file = $logoOptions['file'];
		Plugin::log('Logo overlay: logo file; ' . $file);
		if (!is_file($file)) {
			Plugin::log('Logo overlay: logo file not found!');

			return;
		}

		// source
		$logo = imagecreatefromstring(file_get_contents($file));
		$logo_width = imagesx($logo);
		$logo_height = imagesy($logo);

		// target
		$image_width = imagesx($this->resource);
		$image_height = imagesy($this->resource);

		Plugin::log("Logo overlay: logo dimensions; W:$image_width, H:$image_height");

		// logo overlay
		$w = $logoOptions['w'] * Plugin::AA * 0.5;
		$h = $logoOptions['h'] * Plugin::AA * 0.5;

		$logoOptions['left'] *= Plugin::AA;
		$logoOptions['right'] *= Plugin::AA;
		$logoOptions['top'] *= Plugin::AA;
		$logoOptions['bottom'] *= Plugin::AA;

		$p = $logo_posX = $logo_posY = 0;

		if ($logoOptions['halign'] == 'center') {
			$logo_posX = ($image_width - $logoOptions['left'] - $logoOptions['right']) / 2 - $w / 2 + $logoOptions['left'];
		}
		if ($logoOptions['halign'] == 'right') {
			$logo_posX = $image_width - $logoOptions['right'] - $w - $p;
		}
		if ($logoOptions['halign'] == 'left') {
			$logo_posX = $logoOptions['left'] + $p;
		}

		if ($logoOptions['valign'] == 'center') {
			$logo_posY = ($image_height - $logoOptions['top'] - $logoOptions['bottom']) / 2 - $h / 2 + $logoOptions['top'];
		}
		if ($logoOptions['valign'] == 'bottom') {
			$logo_posY = $image_height - $logoOptions['bottom'] - $h - $p;
		}
		if ($logoOptions['valign'] == 'top') {
			$logo_posY = $logoOptions['top'] + $p;
		}

		imagecopyresampled($this->resource, $logo, $logo_posX, $logo_posY, 0, 0, $w, $h, $logo_width, $logo_height);
		imagedestroy($logo);

		do_action_ref_array('bsi_image_gd', [&$this->resource, 'after_adding_logo', $this->handler->post_id, $this->handler->image_id]);
	}

	public function save($format, $quality)
	{
		$this->manager->file_put_contents($this->target, ''); // prime the file, creating all directories
		switch($format) {
			case 'jpg':
				imagejpeg(imagescale($this->resource, $this->manager->width, $this->manager->height, IMG_BICUBIC_FIXED), $this->target, $quality);
			break;
			case 'webp':
				imagewebp(imagescale($this->resource, $this->manager->width, $this->manager->height, IMG_BICUBIC_FIXED), $this->target, $quality);
			break;
			case 'png':
			default:
				imagepng(imagescale($this->resource, $this->manager->width, $this->manager->height, IMG_BICUBIC_FIXED), $this->target, $quality);
			break;
		}
	}

	private function imagettftextbox($image, $size, $x, $y, $w, $h, Color $color, $fontfile, $text, $options = [])
	{
		/** @var $align string left, center or right */
		/** @var $stroke_width int */
		/** @var $stroke_color false|Color a color */
		extract(shortcode_atts([
			'align' => 'left',
			'stroke_width' => 0,
			'stroke_color' => false,
		], $options));
		$textbox = new Box($image);
		$textbox->setFontSize($size / .75);
		$textbox->setFontFace($fontfile);
		$textbox->setFontColor($color);
		if (false !== $stroke_color && $stroke_width > 0) {
			$textbox->setStrokeColor($stroke_color);
			$textbox->setStrokeSize($stroke_width);
		}
		$textbox->setBox(
			$x,  // distance from left edge
			$y,  // distance from top edge
			$w, // textbox width
			$h  // textbox height
		);

		// text will be aligned inside textbox to right horizontally and to top vertically
		$textbox->setTextAlign($align, 'top');

		$textbox->draw($text);
	}


	// Returns expected width of rendered text in pixels
	private function getWidthPixels($text, $font, $font_size)
	{
		if (!trim($text)) {
			return 0;
		}
		static $widthCorrection;
		// Can't get font width if there is no font.
		if ( ! $font || ! is_file( $font ) ) {
			return 0;
		}
		$bbox = imageftbbox($font_size, 0, $font, $text);
		$width = ($bbox[2] - $bbox[0]);
		if ($width) {
			if (!$widthCorrection) {
				$widthCorrection = static::getWidthPixelsTrue($text, $font, $font_size);
				$widthCorrection = $widthCorrection['width'] / $width;
			}
		}

		return $widthCorrection ? $width / $widthCorrection : 0;
	}

	private function getWidthPixelsTrue($text, $font_file, $font_size, $font_angle = 0)
	{
		$box = imagettfbbox($font_size, $font_angle, $font_file, $text);
		if (!$box) {
			return false;
		}
		$min_x = min(array($box[0], $box[2], $box[4], $box[6]));
		$max_x = max(array($box[0], $box[2], $box[4], $box[6]));
		$min_y = min(array($box[1], $box[3], $box[5], $box[7]));
		$max_y = max(array($box[1], $box[3], $box[5], $box[7]));
		$width = ($max_x - $min_x);
		$height = ($max_y - $min_y);
		$left = abs($min_x) + $width;
		$top = abs($min_y) + $height;
		// to calculate the exact bounding box, I write the text in a large image
		$img = @imagecreatetruecolor($width << 2, $height << 2);
		$white = imagecolorallocate($img, 255, 255, 255);
		$black = imagecolorallocate($img, 0, 0, 0);
		imagefilledrectangle($img, 0, 0, imagesx($img), imagesy($img), $black);
		// for sure the text is completely in the image!
		imagettftext($img, $font_size,
			$font_angle, $left, $top,
			$white, $font_file, $text);
		// start scanning (0=> black => empty)
		$rleft = $w4 = $width << 2;
		$rright = 0;
		$rbottom = 0;
		$rtop = $h4 = $height << 2;
		for ($x = 0; $x < $w4; $x++) {
			for ($y = 0; $y < $h4; $y++) {
				if (imagecolorat($img, $x, $y)) {
					$rleft = min($rleft, $x);
					$rright = max($rright, $x);
					$rtop = min($rtop, $y);
					$rbottom = max($rbottom, $y);
				}
			}
		}
		// destroy img and serve the result
		imagedestroy($img);

		return array(
			"left" => $left - $rleft,
			"top" => $top - $rtop,
			"width" => $rright - $rleft + 1,
			"height" => $rbottom - $rtop + 1
		);
	}

	// Returns wrapped format (with newlines) of a piece of text (meant to be rendered on an image)
	// using the width of rendered bounding box of text
	private function wrapTextByPixels( $text, $line_max_pixels, $font_size, $font )
	{
		$words = explode(' ', $text);
		$lines = [];
		$crr_line_idx = 0;

		foreach ($words as $word) {
			if (empty($lines[$crr_line_idx])) {
				$lines[$crr_line_idx] = '';
			}
			$before = $lines[$crr_line_idx];
			$lines[$crr_line_idx] = trim($lines[$crr_line_idx] . ' ' . $word);
			if (static::getWidthPixels($lines[$crr_line_idx], $font, $font_size) < $line_max_pixels) {
				continue;
			}
			// word overflow;
			$lines[$crr_line_idx] = $before;
			$crr_line_idx++;
			$lines[$crr_line_idx] = $word;
			// protect against infinite loop
			if (static::getWidthPixels($lines[$crr_line_idx], $font, $font_size) >= $line_max_pixels) {
				// this word on its own is too long, ignore that fact and move on
				$crr_line_idx++;
			}
		}

		return trim(implode(PHP_EOL, $lines));
	}
}
