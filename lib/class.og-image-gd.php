<?php

namespace Clearsite\Plugins\OGImage;

use Clearsite\Plugins\OGImage;
use GDText\Box;
use GDText\Color;

require_once __DIR__ .'/../vendor/autoload.php';

class GD {
	private $handler;
	private $manager;

	private $source;
	private $target;

	private $resource;

	public function __construct(Image $handler, $source, $target)
	{
		$this->handler = $handler;
		$this->manager = $handler->getManager();
		$this->source = $source;
		$this->target = $target;

		// use this construction so we don't have to check file mime
		$baseImage = imagecreatefromstring(file_get_contents($source));
		$w = $this->manager->width;
		$h = $this->manager->height;
		$this->resource = imagecreatetruecolor($w, $h);
		imagealphablending($this->resource, true);
		imagesavealpha($this->resource, true);
		imagecopyresampled($this->resource, $baseImage, 0, 0, 0, 0, $w, $h, imagesx($baseImage), imagesy($baseImage));
		imagedestroy($baseImage);
	}

	public function text_overlay($textOptions, $text)
	{
		$debug = false; // true;
		$image_width = imagesx($this->resource);
		$image_height = imagesy($this->resource);

		$text_color = $this->manager->hex_to_rgba($textOptions['color'], true);
//		var_dump($text_color);
//		$text_color = imagecolorallocatealpha($this->resource, $text_color[0], $text_color[1], $text_color[2], $text_color[3] );
//		imagecolortransparent($this->resource, $text_color);
		$text_color = new Color($text_color[0], $text_color[1], $text_color[2], $text_color[3]);

		if ($debug) { print 'TextOptions debug: <pre>'; var_dump($textOptions); }
		$background_color = false;
		if ($textOptions['background-color']) {
			$background_color_rgba = $this->manager->hex_to_rgba($textOptions['background-color'], true);
			if ($background_color_rgba[3] < 127) { // not 100% transparent
				//		var_dump($background_color);exit;
				$background_color = imagecolorallocatealpha($this->resource, $background_color_rgba[0], $background_color_rgba[1], $background_color_rgba[2], $background_color_rgba[3]);
				imagecolortransparent($this->resource, $background_color);
				if ($debug) { print "\n" . 'background-color:'; var_dump($background_color_rgba, $background_color); }
			}
		}

		$text_shadow_color = false;
		if ($textOptions['text-shadow-color']) {
			$text_shadow_color_rgba = $this->manager->hex_to_rgba($textOptions['text-shadow-color'], true);
			if ($text_shadow_color_rgba[3] < 127) { // not 100% transparent
//			var_dump($text_shadow_color);exit;
//				$text_shadow_color = imagecolorallocatealpha($this->resource, $text_shadow_color_rgba[0], $text_shadow_color_rgba[1], $text_shadow_color_rgba[2], $text_shadow_color_rgba[3]);
//				imagecolortransparent($this->resource, $text_shadow_color);
//				if ($debug) { print "\n" . 'text-shadow-color:'; var_dump($text_shadow_color_rgba, $text_shadow_color); }

				$text_shadow_color = new Color($text_shadow_color_rgba[0], $text_shadow_color_rgba[1], $text_shadow_color_rgba[2], $text_shadow_color_rgba[3]);
			}
		}

		$text_stroke_color = false;
		if ($textOptions['text-stroke-color']) {
			$text_stroke_color_rgba = $this->manager->hex_to_rgba($textOptions['text-stroke-color'], true);
			if ($text_stroke_color_rgba[3] < 127) { // not 100% transparent
//			var_dump($textOptions['text-stroke-color'], $text_stroke_color);exit;
//				$text_stroke_color = imagecolorallocatealpha($this->resource, $text_stroke_color_rgba[0], $text_stroke_color_rgba[1], $text_stroke_color_rgba[2], $text_stroke_color_rgba[3]);
//				imagecolortransparent($this->resource, $text_stroke_color);
//				if ($debug) { print "\n" . 'text-stroke-color:'; var_dump($text_stroke_color_rgba, $text_stroke_color); }
				$text_stroke_color = new Color($text_stroke_color_rgba[0], $text_stroke_color_rgba[1], $text_stroke_color_rgba[2], $text_stroke_color_rgba[3]);
			}
		}
		if ($debug) { exit; }
		$font = $textOptions['font-file'];
		$fontSize = $textOptions['font-size'];

		$text = str_replace(['<br>', '<br />', '<br/>', '\\n'], "\n", $text); // predefined line breaks
		$text = str_replace('\\r', '', $text);
		$text = $this->wrapTextByPixels($text, $image_width * .7, $fontSize, $font);

		$textDim = imagettfbbox($fontSize, 0, $font, implode(' ', explode("\n", $text)));
		$line_height = $textDim[1] - $textDim[7];

		$lines = explode("\n", $text);
		$text_width = 0;
		foreach ($lines as $line) {
			$textDim = imagettfbbox($fontSize, 0, $font, $line);
			$text_width = max($text_width, $textDim[2] - $textDim[0]);
		}
		$text_width += 2;
		$text_height = $line_height * count($lines);

		$p = $textOptions['padding'];

		if ($textOptions['halign'] == 'center') {
			$text_posX = ( $image_width - $textOptions['left'] - $textOptions['right']) / 2 - $text_width / 2 + $textOptions['left'];
		}
		if ($textOptions['halign'] == 'right') {
			$text_posX = $image_width - $textOptions['right'] - $text_width - $p;
		}
		if ($textOptions['halign'] == 'left') {
			$text_posX = $textOptions['left'] + $p;
		}

		if ($textOptions['valign'] == 'center') {
			$text_posY = ( $image_height - $textOptions['top'] - $textOptions['bottom'] ) / 2 - $text_height / 2 + $textOptions['top'];
		}
		if ($textOptions['valign'] == 'bottom') {
			$text_posY = $image_height - $textOptions['bottom'] - $text_height - $p;
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
			$shiftX = $textOptions['text-shadow-left'];
			$shiftY = $textOptions['text-shadow-top'];
			$steps = max(absint($shiftX), absint($shiftY));
			$start_color = $textOptions['color'];
			$end_color = $textOptions['text-shadow-color'];
			if ('open' === $textOptions['text-shadow-type']) {
				$steps = 1;
			}
			if ('solid' === $textOptions['text-shadow-type']) {
				$start_color = $end_color;
			}

			for ($step = $steps; $step > 0; $step -- /* skip step 0 as it is the text-position */) {
				$shiftX_step = self::gradient_value(0, $shiftX, $step, $steps);
				$shiftY_step = self::gradient_value(0, $shiftY, $step, $steps);
				$text_shadow_color_step = $this->gradient_color($start_color, $end_color, $step, $steps, true);
				$text_shadow_color_step = $this->manager->hex_to_rgba($text_shadow_color_step, true);
//				$text_shadow_color_step = imagecolorallocatealpha($this->resource, $text_shadow_color_step[0], $text_shadow_color_step[1], $text_shadow_color_step[2], $text_shadow_color_step[3]);
				$text_shadow_color_step = new Color($text_shadow_color_step[0], $text_shadow_color_step[1], $text_shadow_color_step[2], $text_shadow_color_step[3]);
				imagecolortransparent($this->resource, $text_shadow_color_step);
//				imagettftext($this->resource, $fontSize, 0, $text_posX + $shiftX_step, $text_posY + $shiftY_step + $line_height - .2*$line_height , $text_shadow_color_step, $font, $text);
				$this->imagettftextbox($this->resource, $fontSize, $text_posX + $shiftX_step, $text_posY + $shiftY_step, $text_width, $text_height , $text_shadow_color_step, $font, $text, $textOptions['halign']);
			}
		}

		if (false !== $text_stroke_color) {
			$this->imagettftextbox_stroke($this->resource, $fontSize, $text_posX, $text_posY, $text_width, $text_height,
				$text_stroke_color, $font, $text, $textOptions['text-stroke'], $textOptions['halign']);
		}
//		imagettftext($this->resource, $fontSize, 0, $text_posX, $text_posY + $line_height - .2*$line_height , $text_color, $font, $text);
		$this->imagettftextbox($this->resource, $fontSize, $text_posX, $text_posY, $text_width, $text_height, $text_color, $font, $text, $textOptions['halign']);
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
			self::gradient_value( $hex_rgba_start_rgba[0], $hex_rgba_end_rgba[0], $step, $steps),
			self::gradient_value( $hex_rgba_start_rgba[1], $hex_rgba_end_rgba[1], $step, $steps),
			self::gradient_value( $hex_rgba_start_rgba[2], $hex_rgba_end_rgba[2], $step, $steps),
			self::gradient_value( $hex_rgba_start_rgba[3], $hex_rgba_end_rgba[3], $step, $steps),
		];

		return $return_as_hex ? $this->manager->rgba_to_hex($gradient_hex_rgba) : $gradient_hex_rgba;
	}

	private static function gradient_value($start, $end, $step, $steps = 100) {
		$percentage = $step/$steps;
		$value_range = $end - $start;
		return $value_range * $percentage + $start;
	}

	public function logo_overlay($logoOptions)
	{
		if (!$logoOptions['file']) {
			return;
		}
		$file = $logoOptions['file'];
		if (!is_file($file)) {
			return;
		}


		// source
		$logo = imagecreatefromstring(file_get_contents($file));
		$logo_width = imagesx($logo);
		$logo_height = imagesy($logo);

		// target
		$image_width = imagesx($this->resource);
		$image_height = imagesy($this->resource);

		// logo overlay
		$w = $logoOptions['w'];
		$h = $logoOptions['h'];

		$p = 0 ; //???

		if ($logoOptions['halign'] == 'center') {
			$logo_posX = ( $image_width - $logoOptions['left'] - $logoOptions['right']) / 2 - $w / 2 + $logoOptions['left'];
		}
		if ($logoOptions['halign'] == 'right') {
			$logo_posX = $image_width - $logoOptions['right'] - $w - $p;
		}
		if ($logoOptions['halign'] == 'left') {
			$logo_posX = $logoOptions['left'] + $p;
		}

		if ($logoOptions['valign'] == 'center') {
			$logo_posY = ( $image_height - $logoOptions['top'] - $logoOptions['bottom'] ) / 2 - $h / 2 + $logoOptions['top'];
		}
		if ($logoOptions['valign'] == 'bottom') {
			$logo_posY = $image_height - $logoOptions['bottom'] - $h - $p;
		}
		if ($logoOptions['valign'] == 'top') {
			$logo_posY = $logoOptions['top'] + $p;
		}

		imagecopyresampled($this->resource, $logo, $logo_posX, $logo_posY, 0, 0, $w, $h, $logo_width, $logo_height);
		imagedestroy($logo);
	}

	public function save()
	{
		$this->manager->file_put_contents($this->target, ''); // prime the file, creating all directories
		imagepng($this->resource, $this->target, 2);
	}

	public function push_to_browser($filename)
	{
		header('Content-Type: image/png');
		header('Content-Disposition: inline; filename='. $filename);
		imagepng($this->resource);
	}

	private function imagettftextbox_stroke(&$image, $size, $x, $y, $w, $h, $strokecolor, $fontfile, $text, $px, $align='left') {
		for($c1 = ($x-$px); $c1 <= ($x+$px); $c1++) {
			$a +=2;
			$c2 = $y + round(sqrt($px*$px - ($x-$c1)*($x-$c1)));
//			imagettftext($image, $size, $angle, $c1, $c2, $strokecolor, $fontfile, $text);
			$this->imagettftextbox($image, $size, $c1, $c2, $w, $h, $strokecolor, $fontfile, $text, $align);
			$c3 = $y - round(sqrt($px*$px - ($x-$c1)*($x-$c1)));
//			imagettftext($image, $size, $angle, $c1, $c3, $strokecolor, $fontfile, $text);
			$this->imagettftextbox($image, $size, $c1, $c3, $w, $h, $strokecolor, $fontfile, $text, $align);
		}
	}

	private function imagettftextbox($image, $size, $x, $y, $w, $h, Color $color, $fontfile, $text, $align='left') {
		$textbox = new Box($image);
		$textbox->setFontSize($size/.75);
		$textbox->setFontFace($fontfile);
		$textbox->setFontColor($color);
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
	private function getWidthPixels(string $text, string $font, int $font_size): int {
		static $widthCorrection;
		$bbox = imageftbbox($font_size, 0, $font, $text);
		$width = ($bbox[2] - $bbox[0]);
		if (!$widthCorrection) {
			$widthCorrection = static::getWidthPixelsTrue($text, $font, $font_size);
			$widthCorrection = $widthCorrection['width'] / $width;
		}

		return $width / $widthCorrection;
	}

	private function getWidthPixelsTrue($text, $font_file, $font_size, $font_angle = 0) {
		$box   = imagettfbbox($font_size, $font_angle, $font_file, $text);
		if( !$box )
			return false;
		$min_x = min( array($box[0], $box[2], $box[4], $box[6]) );
		$max_x = max( array($box[0], $box[2], $box[4], $box[6]) );
		$min_y = min( array($box[1], $box[3], $box[5], $box[7]) );
		$max_y = max( array($box[1], $box[3], $box[5], $box[7]) );
		$width  = ( $max_x - $min_x );
		$height = ( $max_y - $min_y );
		$left   = abs( $min_x ) + $width;
		$top    = abs( $min_y ) + $height;
		// to calculate the exact bounding box i write the text in a large image
		$img     = @imagecreatetruecolor( $width << 2, $height << 2 );
		$white   =  imagecolorallocate( $img, 255, 255, 255 );
		$black   =  imagecolorallocate( $img, 0, 0, 0 );
		imagefilledrectangle($img, 0, 0, imagesx($img), imagesy($img), $black);
		// for sure the text is completely in the image!
		imagettftext( $img, $font_size,
			$font_angle, $left, $top,
			$white, $font_file, $text);
		// start scanning (0=> black => empty)
		$rleft  = $w4 = $width<<2;
		$rright = 0;
		$rbottom   = 0;
		$rtop = $h4 = $height<<2;
		for( $x = 0; $x < $w4; $x++ )
			for( $y = 0; $y < $h4; $y++ )
				if( imagecolorat( $img, $x, $y ) ){
					$rleft   = min( $rleft, $x );
					$rright  = max( $rright, $x );
					$rtop    = min( $rtop, $y );
					$rbottom = max( $rbottom, $y );
				}
		// destroy img and serve the result
		imagedestroy( $img );
		return array( "left"   => $left - $rleft,
			"top"    => $top  - $rtop,
			"width"  => $rright - $rleft + 1,
			"height" => $rbottom - $rtop + 1 );
	}

	// Returns wrapped format (with newlines) of a piece of text (meant to be rendered on an image)
	// using the width of rendered bounding box of text
	private function wrapTextByPixels(
		string $text,
		int $line_max_pixels,
		int $font_size,
		string $font
	): string {
		$words = explode(' ', $text);
		$lines = [];
		$crr_line_idx = 0;
		$debug = false;

		foreach ($words as $word) {
			$before = $lines[$crr_line_idx];
			$lines[$crr_line_idx] = trim($lines[$crr_line_idx] . ' ' . $word);
			if (static::getWidthPixels($lines[$crr_line_idx], $font, $font_size) < $line_max_pixels) {
				if ($debug) {
					$lines[$crr_line_idx + 1] = trim($lines[$crr_line_idx + 1] . ' ' . static::getWidthPixels($word, $font, $font_size));
				}
				continue;
			}
			// word overflow;
			$lines[$crr_line_idx] = $before;
			$crr_line_idx++;
			if ($debug) {
				$crr_line_idx++;
			}
			$lines[$crr_line_idx] = $word;
			if ($debug) {
				$lines[$crr_line_idx + 1] = static::getWidthPixels($word, $font, $font_size);
			}
			// protect against infinite loop
			if (static::getWidthPixels($lines[$crr_line_idx], $font, $font_size) >= $line_max_pixels) {
				// this word on it's own is too long, ignore that fact and move on
				$crr_line_idx++;
				if ($debug) {
					$crr_line_idx++;
				}
			}
		}

		foreach($lines as $line) {
//			var_dump($line, static::getWidthPixels($line, $font, $font_size), "($line_max_pixels)");
		}
//		exit;

		return trim(implode( PHP_EOL, $lines));
	}
}
