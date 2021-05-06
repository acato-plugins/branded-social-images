<?php
//
//namespace Clearsite\Plugins\OGImage;
//
//use Clearsite\Plugins\OGImage;
//use \Imagick as ImageMagick;
//use ImagickDraw;
//
//class IMagick {
//	private $handler;
//	private $manager;
//
//	private $source;
//	private $target;
//
//	private $resource;
//
//	public function __construct(Image $handler, $source, $target)
//	{
//		$this->handler = $handler;
//		$this->manager = $handler->getManager();
//		$this->source = $source;
//		$this->target = $target;
//
//		// use this construction so we don't have to check file mime
//		$this->resource = new ImageMagick();
//		$this->resource->readImage($source);
//		$this->resource->setImageFormat('PNG');
//	}
//
//	public function text_overlay($textOptions, $text)
//	{
//		$image_width = $this->resource->getImageWidth();
//		$image_height = $this->resource->getImageHeight();
//
//		$text_color = $this->manager->hex_to_rgba($textOptions['color']);
//
//
//		$background_color = false;
//		if ($textOptions['background-color']) {
//			$background_color = $this->manager->hex_to_rgba($textOptions['background-color']);
//			//		var_dump($background_color);exit;
//			$background_color = imagecolorallocatealpha($this->resource, $background_color[0], $background_color[1], $background_color[2], $background_color[3]);
//			imagecolortransparent($this->resource, $background_color);
//		}
//
//		$text_shadow_color = false;
//		if ($textOptions['text-shadow-color']) {
//			$text_shadow_color = $this->manager->hex_to_rgba($textOptions['text-shadow-color']);
////			var_dump($text_shadow_color);exit;
//			$text_shadow_color = imagecolorallocatealpha($this->resource, $text_shadow_color[0], $text_shadow_color[1], $text_shadow_color[2], $text_shadow_color[3]);
//			imagecolortransparent($this->resource, $text_shadow_color);
//		}
//
//		$text_stroke_color = false;
//		if ($textOptions['text-stroke-color']) {
//			$text_stroke_color = $this->manager->hex_to_rgba($textOptions['text-stroke-color']);
//			$text_stroke_color = imagecolorallocatealpha($this->resource, $text_stroke_color[0], $text_stroke_color[1], $text_stroke_color[2], $text_stroke_color[3]/10);
//			imagecolortransparent($this->resource, $text_stroke_color);
//		}
//
//		$font = $textOptions['font-file'];
//		$fontSize = $textOptions['font-size'];
//
//		$draw = new ImagickDraw();
//		$draw->setFillColor($text_color);
//		$draw->setFont($font);
//		$draw->setFontSize($fontSize);
//		if ($textOptions['background-color']) {
//			$draw->setTextUnderColor($this->manager->hex_to_rgba($textOptions['background-color']);
//		}
//		if ($textOptions['text-stroke-color']) {
//			$draw->setStrokeColor($this->manager->hex_to_rgba($textOptions['text-stroke-color']);
//		}
//		if ($text_shadow_color) {
////			$shiftX = $textOptions['text-shadow-left'];
////			$shiftY = $textOptions['text-shadow-top'];
////			imagettftext($this->resource, $fontSize, 0, $text_posX + $shiftX, $text_posY + $shiftY + $text_height * .82, $text_shadow_color, $font, $text);
////		}
//
//		$textDim = imagettfbbox($fontSize, 0, $font, $text);
//		$text_width = $textDim[2] - $textDim[0];
//		$text_height = $textDim[1] - $textDim[7];
//
//		$l = $textOptions['line-height'];
//
//		if ($l > $text_height) {
////			$text_height = $l;
//		}
//		// todo: word-wrapping
//
//		$p = $textOptions['padding'];
//
//		if ($textOptions['halign'] == 'center') {
//			$text_posX = ( $image_width - $textOptions['left'] - $textOptions['right']) / 2 - $text_width / 2 + $textOptions['left'];
//		}
//		if ($textOptions['halign'] == 'right') {
//			$text_posX = $image_width - $textOptions['right'] - $text_width - $p;
//		}
//		if ($textOptions['halign'] == 'left') {
//			$text_posX = $textOptions['left'] + $p;
//		}
//
//		if ($textOptions['valign'] == 'center') {
//			$text_posY = ( $image_height - $textOptions['top'] - $textOptions['bottom '] ) / 2 - $text_height / 2 + $textOptions['top'];
//		}
//		if ($textOptions['valign'] == 'bottom') {
//			$text_posY = $image_height - $textOptions['bottom'] - $text_height - $p;
//		}
//		if ($textOptions['valign'] == 'top') {
//			$text_posY = $textOptions['top'] + $p;
//		}
////
//		$this->resource->annotateImage($draw, $text_posX,$text_posY,0, $text);
//
//
////		// text-background
////		if ($background_color) {
////			if ('inline' === $textOptions['display']) {
////				imagefilledrectangle($this->resource, $text_posX - $p, $text_posY - $p, $text_posX + $text_width + $p, $text_posY + $text_height + $p, $background_color);
////			}
////		}
////		// NOTE: imagettf uses Y position for bottom!! of the text, not the top
////		// ALSO: this is for the text BASE, so some text might stick out below. compensate by 18% of text height.
////		if ($text_shadow_color) {
////			$shiftX = $textOptions['text-shadow-left'];
////			$shiftY = $textOptions['text-shadow-top'];
////			imagettftext($this->resource, $fontSize, 0, $text_posX + $shiftX, $text_posY + $shiftY + $text_height * .82, $text_shadow_color, $font, $text);
////		}
////
////		if ($text_stroke_color) {
////			$this->imagettfstroketext($this->resource, $fontSize, 0, $text_posX, $text_posY + $text_height * .82,
////				$text_stroke_color, $font, $text, $textOptions['text-stroke']);
////		}
////		imagettftext($this->resource, $fontSize, 0, $text_posX, $text_posY + $text_height * .82, $text_color, $font, $text);
//	}
//
//	public function logo_overlay($logoOptions)
//	{
//		if (!$logoOptions['file']) {
//			return;
//		}
//		$file = $logoOptions['file'];
//		if (!is_file($file)) {
//			return;
//		}
//
//
//		// source
//		$logo = imagecreatefromstring(file_get_contents($file));
//		$logo_width = imagesx($logo);
//		$logo_height = imagesy($logo);
//
//		// target
//		$image_width = imagesx($this->resource);
//		$image_height = imagesy($this->resource);
//
//		// logo overlay
//		$w = $logoOptions['w'];
//		$h = $logoOptions['h'];
//
//		$p = 0 ; //???
//
//		if ($logoOptions['halign'] == 'center') {
//			$logo_posX = ( $image_width - $logoOptions['left'] - $logoOptions['right']) / 2 - $w / 2 + $logoOptions['left'];
//		}
//		if ($logoOptions['halign'] == 'right') {
//			$logo_posX = $image_width - $logoOptions['right'] - $w - $p;
//		}
//		if ($logoOptions['halign'] == 'left') {
//			$logo_posX = $logoOptions['left'] + $p;
//		}
//
//		if ($logoOptions['valign'] == 'center') {
//			$logo_posY = ( $image_height - $logoOptions['top'] - $logoOptions['bottom '] ) / 2 - $h / 2 + $logoOptions['top'];
//		}
//		if ($logoOptions['valign'] == 'bottom') {
//			$logo_posY = $image_height - $logoOptions['bottom'] - $h - $p;
//		}
//		if ($logoOptions['valign'] == 'top') {
//			$logo_posY = $logoOptions['top'] + $p;
//		}
//
//		imagecopyresampled($this->resource, $logo, $logo_posX, $logo_posY, 0, 0, $w, $h, $logo_width, $logo_height);
//		imagedestroy($logo);
//	}
//
//	public function save()
//	{
//		$this->manager->file_put_contents($this->target, ''); // prime the file, creating all directories
//		imagepng($this->resource, $this->target, 2);
//	}
//
//	public function push_to_browser($filename)
//	{
//		header('Content-Type: image/png');
//		header('Content-Disposition: inline; filename='. $filename);
//		imagepng($this->resource);
//	}
//
//	private function imagettfstroketext(&$image, $size, $angle, $x, $y, &$strokecolor, $fontfile, $text, $px) {
//		for($c1 = ($x-$px); $c1 <= ($x+$px); $c1++) {
//			$a +=2;
//			$c2 = $y + round(sqrt($px*$px - ($x-$c1)*($x-$c1)));
//			imagettftext($image, $size, $angle, $c1, $c2, $strokecolor, $fontfile, $text);
//			$c3 = $y - round(sqrt($px*$px - ($x-$c1)*($x-$c1)));
//			imagettftext($image, $size, $angle, $c1, $c3, $strokecolor, $fontfile, $text);
//		}
//	}
//}
