<?php

namespace Clearsite\Plugins\OGImage;

use Clearsite\Plugins\OGImage;

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
		$this->resource = imagecreatetruecolor($w = imagesx($baseImage), $h = imagesy($baseImage));
		imagealphablending($this->resource, true);
		imagesavealpha($this->resource, true);
		imagecopy($this->resource, $baseImage, 0, 0, 0, 0, $w, $h);
		imagedestroy($baseImage);
	}

	public function text_overlay($textOptions, $text)
	{
		$image_width = imagesx($this->resource);
		$image_height = imagesy($this->resource);

		$text_color = $this->manager->hex_to_rgba($textOptions['color'], true);
//		var_dump($text_color);
		$text_color = imagecolorallocatealpha($this->resource, $text_color[0], $text_color[1], $text_color[2], $text_color[3] );
		imagecolortransparent($this->resource, $text_color);

		$background_color = false;
		if ($textOptions['background-color']) {
			$background_color = $this->manager->hex_to_rgba($textOptions['background-color'], true);
			//		var_dump($background_color);exit;
			$background_color = imagecolorallocatealpha($this->resource, $background_color[0], $background_color[1], $background_color[2], $background_color[3]);
			imagecolortransparent($this->resource, $background_color);
		}

		$text_shadow_color = false;
		if ($textOptions['text-shadow-color']) {
			$text_shadow_color = $this->manager->hex_to_rgba($textOptions['text-shadow-color'], true);
//			var_dump($text_shadow_color);exit;
			$text_shadow_color = imagecolorallocatealpha($this->resource, $text_shadow_color[0], $text_shadow_color[1], $text_shadow_color[2], $text_shadow_color[3]);
			imagecolortransparent($this->resource, $text_shadow_color);
		}

		$text_stroke_color = false;
		if ($textOptions['text-stroke-color']) {
			$text_stroke_color = $this->manager->hex_to_rgba($textOptions['text-stroke-color'], true);
			$text_stroke_color = imagecolorallocatealpha($this->resource, $text_stroke_color[0], $text_stroke_color[1], $text_stroke_color[2], $text_stroke_color[3]/10);
			imagecolortransparent($this->resource, $text_stroke_color);
		}

		$font = $textOptions['font-file'];
		$fontSize = $textOptions['font-size'];

		$text = str_replace(['<br>', '<br />', '<br/>', '\\n'], "\n", $text); // predefined line breaks
		$text = str_replace('\\r', '', $text);
		$text = $this->wrapTextByPixels($text, $image_width * .7, $fontSize, $font);
		$lines = count(explode("\n", $text));

		$textDim = imagettfbbox($fontSize, 0, $font, $text);
		$text_width = $textDim[2] - $textDim[0];
		$text_height = $textDim[1] - $textDim[7];

		$lineDim = imagettfbbox($fontSize, 0, $font, 'Wg');
		$line_height = $lineDim[1] - $lineDim[7];

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
			$text_posY = ( $image_height - $textOptions['top'] - $textOptions['bottom '] ) / 2 - $text_height / 2 + $textOptions['top'];
		}
		if ($textOptions['valign'] == 'bottom') {
			$text_posY = $image_height - $textOptions['bottom'] - $text_height - $p;
		}
		if ($textOptions['valign'] == 'top') {
			$text_posY = $textOptions['top'] + $p;
		}

		// text-background
		if ($background_color) {
			if ('inline' === $textOptions['display']) {
				imagefilledrectangle($this->resource, $text_posX - $p, $text_posY - $p, $text_posX + $text_width + $p, $text_posY + $text_height + $p, $background_color);
			}
		}
		// NOTE: imagettf uses Y position for bottom!! of the text, not the top
		// ALSO: this is for the text BASE, so some text might stick out below. compensate by 18% of text height.
		if ($text_shadow_color) {
			$shiftX = $textOptions['text-shadow-left'];
			$shiftY = $textOptions['text-shadow-top'];
			imagettftext($this->resource, $fontSize, 0, $text_posX + $shiftX, $text_posY + $shiftY + $line_height - .2*$line_height , $text_shadow_color, $font, $text);
		}

		if ($text_stroke_color) {
			$this->imagettfstroketext($this->resource, $fontSize, 0, $text_posX, $text_posY + $line_height - .2*$line_height ,
				$text_stroke_color, $font, $text, $textOptions['text-stroke']);
		}
		imagettftext($this->resource, $fontSize, 0, $text_posX, $text_posY + $line_height - .2*$line_height , $text_color, $font, $text);
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
			$logo_posY = ( $image_height - $logoOptions['top'] - $logoOptions['bottom '] ) / 2 - $h / 2 + $logoOptions['top'];
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

	private function imagettfstroketext(&$image, $size, $angle, $x, $y, &$strokecolor, $fontfile, $text, $px) {
		for($c1 = ($x-$px); $c1 <= ($x+$px); $c1++) {
			$a +=2;
			$c2 = $y + round(sqrt($px*$px - ($x-$c1)*($x-$c1)));
			imagettftext($image, $size, $angle, $c1, $c2, $strokecolor, $fontfile, $text);
			$c3 = $y - round(sqrt($px*$px - ($x-$c1)*($x-$c1)));
			imagettftext($image, $size, $angle, $c1, $c3, $strokecolor, $fontfile, $text);
		}
	}



	// Returns expected width of rendered text in pixels
	private function getWidthPixels(string $text, string $font, int $font_size): int {
		// https://www.php.net/manual/en/function.imageftbbox.php#refsect1-function.imageftbbox-returnvalues
		$bbox = imageftbbox($font_size, 0, $font, " " . $text);
		return $bbox[2] - $bbox[0];
	}

	// Returns wrapped format (with newlines) of a piece of text (meant to be rendered on an image)
	// using the width of rendered bounding box of text
	private function wrapTextByPixels(
		string $text,
		int $line_max_pixels,
		int $font_size,
		string $font
	): string {
		$words = explode(' ', $text);   // tokenize the text into words
		$lines = [];                             // Array[Array[string]]: array to store lines of words
		$crr_line_idx = 0;                       // (zero-based) index of current lines in which words are being added
		$crr_line_pixels = 0;                    // width of current line (in which words are being added) in pixels

		foreach ($words as $word) {
			// determine the new width of current line (in pixels) if the current word is added to it (including space)
			$crr_line_new_pixels = $crr_line_pixels + static::getWidthPixels(' ' . $word, $font, $font_size);
			// determine the width of current word in pixels
			$crr_word_pixels = static::getWidthPixels($word, $font, $font_size);


			if ($crr_word_pixels > $line_max_pixels) {
				// if the current word itself is too long to fit in single line
				// then we have no option: it must still be put in oneline only
				if ($crr_line_pixels == 0) {
					// but it is put into current line only if current line is empty
					$lines[$crr_line_idx] = array($word);
					$crr_line_idx++;
				} else {
					// otherwise if current line is non-empty, then the extra long word is put into a newline
					$crr_line_idx++;
					$lines[$crr_line_idx] = array($word);
					$crr_line_idx++;
					$crr_line_pixels = 0;
				}
			} else if ($crr_line_new_pixels > $line_max_pixels) {
				// otherwise if new width of current line (including current word and space)
				// exceeds the maximum permissible width, then force the current word into newline
				$crr_line_idx++;
				$lines[$crr_line_idx] = array($word);
				$crr_line_pixels = $crr_word_pixels;
			} else {
				// else if the current word (including space) can fit in the current line, then put it there
				$lines[$crr_line_idx][] = $word;
				$crr_line_pixels = $crr_line_new_pixels;
			}
		}

		// after the above foreach loop terminates, the $lines 2-d array Array[Array[string]]
		// would contain words segregated into lines to preserve the $line_max_pixels

		// now we just need to stitch together lines (array of word strings) into a single continuous piece of text with
		$concatenated_string = array_reduce(
			$lines,
			static function (string $wrapped_text, array $crr_line): string {
				return $wrapped_text . PHP_EOL . implode(' ', $crr_line);
			},
			''
		);

		// the above process of concatenating lines into single piece of text will inadvertently
		// add an extra newline '\n' character in the beginning; so we must remove that
		return Image::removeFirstOccurrence($concatenated_string, "\n");
	}
}
