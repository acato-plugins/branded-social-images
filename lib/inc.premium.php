<?php

namespace Clearsite\Plugins\OGImage;

use Clearsite\Tools\Licensing;

class Premium {
	public static function getInstance()
	{
		static $instance;
		if (!$instance) {
			$instance = new static();
		}

		return $instance;
	}

	public static function verifyLicense()
	{
		$licensing = Licensing::getInstance();
		$licensing->code_storage(Plugin::OPTION_PREFIX . '_license_code');
		$licensing->verification_storage(Plugin::OPTION_PREFIX . '_license_verified');

		if ($licensing->verified()) {
			$licensing->install();
		}
	}
}

Premium::getInstance();
