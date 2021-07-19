<?php
echo "Testing ability to call the conversion tools from PHP; ". PHP_EOL;

$d = __DIR__;

$result = exec('"'. $d . '/dwebp" "'. $d . '/test.webp" -o "' . $d . '/test.png"');

$succes = is_file($d . '/test.png');
if ($succes) {
	unlink($d . '/test.png');
}
echo $succes ? "It works!" : "Sorry, it did not work";
echo PHP_EOL;

touch ($d .'/can-execute-binaries-from-php.' . ( $succes ? 'success' : 'fail'));
