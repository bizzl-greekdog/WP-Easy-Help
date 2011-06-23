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

//if (!function_exists('join_path')) {

	function __join_path() {
		$fuck = func_get_args();
		for ($i = 0; $i < count($fuck); $i++)
			if (is_array($fuck[$i]))
				array_splice ($fuck, $i, 1, $fuck[$i]);
		$f = implode(DIRECTORY_SEPARATOR, $fuck);
		return preg_replace('/\\' . DIRECTORY_SEPARATOR . '+/', DIRECTORY_SEPARATOR, $f);
	}

//}

class LookUpPath {
	private $pathes = array();
	
	public function __construct() {
		$args = func_get_args();
		foreach ($args as $argv)
			$this->addPath($argv);
	}
	
	public function addPath($path) {
		if (is_array($path))
			$this->pathes = array_merge($this->pathes, $path);
		else
			$this->pathes[] = $path;
	}
	
	public function lookup($filename) {
		$f = func_get_args();
		$f2 = __join_path($f);
		foreach ($this->pathes as $path) {
			$f = __join_path($path, $f2);
			if (file_exists($f))
				return $f;
		}
		return false;
	}
}

function lookup($filename, $pathes) {
	$lup = new LookUpPath($pathes);
	return $lup->lookup($filename);
	
}

class CallbackContext {
	private $callback = null;
	private $argv = null;
	
	public function __construct($callback, $argv) {
		$this->callback = $callback;
		$this->argv = $argv;
	}
	
	public function call() {
		$f = func_get_args();
		$argv = array_merge($f, $this->argv);
		return call_user_func_array($this->callback, $argv);
	}
	
	public function cb() {
		return array(&$this, 'call');
	}
}

class Wordpress_Online_Admin_Help {

	protected static $domain = 'wordpress-online-admin-help';
	protected static $base = '';
	protected static $plugins = NULL;
	
	protected static function init_base() {
		self::$base = basename(dirname(__FILE__));
	}

	protected static function init_l10n() {
		$j = __join_path(self::$base, 'locale');
		load_plugin_textdomain(self::$domain, false, $j);
	}

	public static function init() {
		self::init_base();
		self::init_l10n();
		self::$plugins = array_merge(wp_get_active_network_plugins(), wp_get_active_and_valid_plugins());
		add_action('admin_menu', array(__CLASS__, 'menu_init'));
	}
	
	public static function menu_init() {
	   add_menu_page(__('Help', self::$domain), __('Help System', self::$domain), 'edit_posts', 'online-help', array(__CLASS__, 'help'));
	}
	
	public static function rebase_src($matches, $base_path, $base_url) {
		if (preg_match('#^[a-z0-9]+://#', $matches[1]))
				return $matches[0];
		if ($base_url[strlen($base_url) - 1] != '/')
			$base_url .= '/';
		$src = lookup($matches[1], array(
			__join_path($base_path, 'help', get_locale()),
			__join_path($base_path, 'help', 'en_US'),
			__join_path($base_path, 'help', 'assets')
		));
		if ($src)
			$src = str_replace($base_path, $base_url, $src);
		else
			$src = 'Whoops, looks like the developer fucked it up!';
		return "src=\"{$src}\"";
	}
	
	public static function rebase_href($matches, $base) {
		if (preg_match('#^[a-z0-9]+://#', $matches[1]))
				return $matches[0];
		return "href=\"admin.php?page=online-help&entry={$base}/{$matches[1]}\"";
	}
	
	public static function load_file($path, $request_base = 'wordpress', $asset_base_path = '', $asset_base_url = '') {
		$content = file_get_contents($path);
		$title = basename($path);
		if (preg_match('#<title>(.*?)</title>#', $content, $matches))
			$title = $matches[1];
		
		foreach(array('#</?(body|html)[^>]*>#i', '#<head[^>]*>.*</head>#is', '#<!(?!--)[^>]+>#') as $pattern)
			$content = preg_replace ($pattern, '', $content);
		
		$cbx = new CallbackContext(array(__CLASS__, 'rebase_href'), array($request_base));
		$content = preg_replace_callback('#href="([^"]+)"#', $cbx->cb(), $content);
		
		$cbx = new CallbackContext(array(__CLASS__, 'rebase_src'), array($asset_base_path, $asset_base_url));
		$content = preg_replace_callback('#src="([^"]+)"#', $cbx->cb(), $content);
		
		return array('title' => $title, 'content' => $content);
	}
	
	public static function help() {
		global $current_user;
		
		if (isset($_REQUEST['entry'])) {
			list($who, $what) = explode('/', $_REQUEST['entry'], 2);
			error_log("$who $what");
			$plugin = array_shift(preg_grep("#{$who}#", self::$plugins));
			if ($who == 'wordpress') {
				$base_path = get_home_path();
				$base_url = get_site_url();
			} elseif ($who == 'you') {
				$upd = wp_upload_dir();
				$base_path = $upd['basedir'];
				$base_url = $upd['baseurl'];
			} else {
				$base_path = dirname($plugin);
				$base_url = plugin_dir_url($plugin);
			}
			$file = lookup($what, array(
				__join_path($base_path, 'help', get_locale()),
				__join_path($base_path, 'help', 'en_US')
			));
			if ($file) {
				$file = self::load_file($file, $who, $base_path, $base_url);
				echo "<h1>{$file['title']}</h1><p>{$file['content']}</p>";
			}
		} else {
			echo '<h1>' . sprintf(__('Welcome %s', self::$domain), $current_user->data->display_name) . '</h1>';
			
			
			echo '<h3>' . __('General', self::$domain) . '</h3>';
			$index = lookup('index.html', array(
				__join_path(get_home_path(), 'help', get_locale()),
				__join_path(get_home_path(), 'help', 'en_US'),
			));
			if ($index) {
					$index = self::load_file($index, 'wordpress', get_home_path(), get_site_url());
					echo "<p>{$index['content']}</p>";
			}
			
			if (is_multisite()) {
				echo '<h3>' . __('Especially for you', self::$domain) . '</h3>';
				$upd = wp_upload_dir();
				$index = lookup('index.html', array(
					__join_path($upd['basedir'], 'help', get_locale()),
					__join_path($upd['basedir'], 'help', 'en_US'),
				));
				if ($index) {
						$index = self::load_file($index, 'you', $upd['basedir'], $upd['baseurl']);
						echo "<p>{$index['content']}</p>";
				}
			}
			
			
			echo '<h3>' . __('Plugins', self::$domain) . '</h3>';
			echo '<ol>';
			foreach (self::$plugins as $plugin) {
				$base_path = dirname($plugin);
				$index = lookup('index.html', array(
					__join_path($base_path, 'help', get_locale()),
					__join_path($base_path, 'help', 'en_US')
				));
				if ($index) {
					$p = get_plugin_data($plugin);
					$index = self::load_file($index, basename($base_path), $base_path, plugin_dir_url($plugin));
					echo "<li><h4>{$p['Name']}</h4><p>{$index['content']}</p></li>";
				}
			}
			echo '</ol>';
		}
	}
}

Wordpress_Online_Admin_Help::init();
?>