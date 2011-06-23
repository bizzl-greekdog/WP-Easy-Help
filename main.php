<?php
/*
  Plugin Name: Wordpress Online Admin Help
  Plugin URI:
  Description: A help system that integrates into the wordpress admin panel.
  Version: 1.0.0
  Author: Benjamin Kleiner
  Author URI:
  License: GPLv3
 */

if (!function_exists('join_path')) {

	function join_path() {
		$fuck = func_get_args();
		return implode(DIRECTORY_SEPARATOR, $fuck);
	}

}

class Wordpress_Online_Admin_Help {

	protected static $domain = 'wordpress-online-admin-help';
	protected static $base = '';
	
	protected static function init_base() {
		self::$base = basename(dirname(__FILE__));
	}

	protected static function init_l10n() {
		$j = join_path(self::$base, 'locale');
		load_plugin_textdomain(self::$domain, false, $j);
	}

	public static function init() {
		self::init_base();
		self::init_l10n();
	}
}

Wordpress_Online_Admin_Help::init();
?>