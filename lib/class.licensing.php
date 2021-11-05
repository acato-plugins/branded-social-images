<?php

namespace Clearsite\Tools;

class Licensing {
	private $code_storage;
	private $verification_storage;
	const VERIFICATION_SERVER = 'https://wp.clearsite.nl/license/verify/';

	public static function getInstance()
	{
		static $instance;
		if (!$instance) {
			$instance = new static();
		}

		return $instance;
	}

	public function verified()
	{

	}

	public function verify($offsite=false)
	{
		$code = get_option($this->code_storage);
		$verification = get_option($this->verification_storage);
		if (!$code) {
			// no code input
			return false;
		}
		// check the presence of a code loosely just for loading the code
		if (!$offsite && $verification) {
			return true;
		}

		// check strict when asked for it.
		if ($verification == md5(date('YW'). $code)) {
			// verified this week
			return true;
		}

		// we don't want to flood the service, only do offsite check in wp-admin.
		if (!$offsite) {
			return false;
		}

		// at this point we either have no verification at all, or an expired verification
		$data = [
			'body' => self::gatherRelevantData(),
			'headers' => [],
			'referer' => get_bloginfo('url'),
			'useragent' => self::userAgent(),
		];
		$response = wp_remote_post(self::VERIFICATION_SERVER, $data);
		if (is_wp_error($response)) {
			// could not verify
		}
		else {
			
		}
	}

	public function install()
	{

	}

	public function uninstall()
	{

	}

	public static function admin_message()
	{

	}

	/**
	 * @param mixed $code_storage
	 */
	public function setCodeStorage($code_storage): void
	{
		$this->code_storage = $code_storage;
	}

	/**
	 * @param mixed $verification_storage
	 */
	public function setVerificationStorage($verification_storage): void
	{
		$this->verification_storage = $verification_storage;
	}


}
