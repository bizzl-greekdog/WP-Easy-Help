<?php

/*
  Plugin Name: WP Easy Help
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
		for ($i = 0; $i < count($fuck); $i++)
			if (is_array($fuck[$i]))
				array_splice($fuck, $i, 1, $fuck[$i]);
		$f = implode(DIRECTORY_SEPARATOR, $fuck);
		return preg_replace('/(?<!:)\\' . DIRECTORY_SEPARATOR . '+/', DIRECTORY_SEPARATOR, $f);
	}

}


if (!function_exists('mime_content_type')) {
	
	// Sh***, mime_content_type is missing!
	// We'll have to emulate it!
	function mime_content_type($file) {
		if (preg_match('/\.(m4v|mp4|webm|mov|avi|ogv)$/i', $file))
			return 'video/any';
		if (preg_match('/\.(mp3|wav|au|snd|ogg|oga|aac)$/i', $file))
			return 'audo/any';
		if (preg_match('/\.(jp[eg]{1,2}|gif|png|apng|mng|webp)$/i', $file))
			return 'image/any';
		return 'text/any';
	}
	
}

require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'tag.php');

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
		$f2 = join_path($f);
		foreach ($this->pathes as $path) {
			$f = join_path($path, $f2);
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

class WP_Easy_Help {

	protected static $domain = 'wp-easy-help';
	protected static $base = '';
	protected static $plugins = array();

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
		if (function_exists('wp_get_active_network_plugins'))
			self::$plugins = wp_get_active_network_plugins();
		self::$plugins = array_merge(self::$plugins, wp_get_active_and_valid_plugins());
		add_action('admin_menu', array(__CLASS__, 'menu_init'));
		add_action('wp_print_scripts', array(__CLASS__, 'print_scripts'));
		add_action('admin_init', array(__CLASS__, 'init_scripts'));
	}
	
	public static function init_scripts() {
		error_log(join_path(plugin_dir_url(__FILE__), 'css', 'main.css'));
		wp_enqueue_style('woah-main', join_path(plugin_dir_url(__FILE__), 'css', 'main.css'));
	}

	public static function print_scripts() {
		echo script(array('src' => 'http://api.flattr.com/js/0.6/load.js?mode=auto'))->attr('id', 'flattr');
	}

	public static function menu_init() {
		add_menu_page(__('Help', self::$domain), __('Help System', self::$domain), 'edit_posts', 'online-help', array(__CLASS__, 'help'), '', 3);
	}

	public static function do_paypal_button($id) {
		list($u, $l) = explode('_', get_locale(), 2);
		return tag('form')
						->addClass('donate')
						->attr(array('action' => 'https://www.paypal.com/cgi-bin/webscr', 'method' => 'post'))
						->append(
								tag('input')->attr(array('type' => 'hidden', 'name' => 'cmd', 'value' => '_s-xclick')), tag('input')->attr(array('type' => 'hidden', 'name' => 'hosted_button_id', value => $id)), tag('input')->attr(array('type' => 'image', 'src' => "https://www.paypalobjects.com/{$u}_{$l}/{$l}/i/btn/btn_donate_LG.gif", 'border' => 0, 'name' => 'submit', alt => 'donate')), img("https://www.paypalobjects.com/{$u}_{$l}/i/scr/pixel.gif", '')->attr(array('width' => 1, 'height' => 1, 'border' => 0))
		);
	}

	public static function do_flattr_button($id) {
		if (!preg_match('/^flattr;/', $id))
			$id = "flattr;$id";
		return div(tag('a')->addClass('FlattrButton')->attr(array(
							'href' => 'http://' . $_SERVER["HTTP_HOST"] . $_SERVER['REQUEST_URI'], // TODO Maybe add an url parameter to the meta tag
							'title' => 'Donate via flattr',
							'lang' => get_locale(),
							'rev' => $id
						))->css('display', 'none')->append('Donate via flattr'))->addClass('donate');
	}

	public static function rebase_src($matches, $request_base, $asset_base_path, $asset_base_url) {
		if (preg_match('#^[a-z0-9]+://#', $matches[1]))
			return $matches[0];
		if (preg_match('#^(\.\./)+assets#', $matches[1]))
			$src = join_path($asset_base_url, 'help', preg_replace('#^(\.\./)+#', '', $matches[1]));
		else
			$src = lookup($matches[1], array(
				join_path($asset_base_path, 'help', get_locale()),
				join_path($asset_base_path, 'help', 'en_US'),
				join_path($asset_base_path, 'help', 'assets')
					));

		if ($asset_base_url[strlen($asset_base_url) - 1] != '/')
			$asset_base_url .= '/';
		if ($src)
			$src = str_replace($asset_base_path, $asset_base_url, $src);
		else
			$src = 'Whoops, looks like the developer fucked it up!';
		return "src=\"{$src}\"";
	}

	public static function rebase_href($matches, $request_base, $asset_base_path, $asset_base_url) {
//		error_log($asset_base_url);
		if (preg_match('#^[a-z0-9]+://#', $matches[1]) || preg_match('/^#/', $matches[1]))
			return $matches[0];
		if (preg_match('#^(\.\./)+assets#', $matches[1]))
			return 'href="' . join_path($asset_base_url, 'help', preg_replace('#^(\.\./)+#', '', $matches[1])) . '" target="_blank"';
		return "href=\"admin.php?page=online-help&entry={$request_base}/{$matches[1]}\"";
	}

	public static function load_file($path, $request_base = 'wordpress', $asset_base_path = '', $asset_base_url = '') {
		$type = mime_content_type($path);
		$title = basename($path);
		$donate = array();
//		error_log("Filetype of $path is $type");
		if (preg_match('#^text/#', $type)) {
			$content = file_get_contents($path);
			if (preg_match('#<title>(.*?)</title>#s', $content, $matches))
				$title = $matches[1];
			if (preg_match_all('#<meta[^>]+>#s', $content, $metas))
				foreach ($metas[0] as $meta)
					if (preg_match('#name="donate"#', $meta) && preg_match('#content="([^"]+)"#', $meta, $matches))
						$donate[] = $matches[1];

			foreach (array('#</?(body|html)[^>]*>#i', '#<head[^>]*>.*</head>#is', '#<!(?!--)[^>]+>#') as $pattern)
				$content = preg_replace($pattern, '', $content);

			$cbx = new CallbackContext(array(__CLASS__, 'rebase_href'), array($request_base, $asset_base_path, $asset_base_url));
			$content = preg_replace_callback('#href="([^"]+)"#', $cbx->cb(), $content);

			$cbx = new CallbackContext(array(__CLASS__, 'rebase_src'), array($request_base, $asset_base_path, $asset_base_url));
			$content = preg_replace_callback('#src="([^"]+)"#', $cbx->cb(), $content);
		} elseif (preg_match('#^image/#', $type)) {
			$content = img(str_replace($asset_base_path, $asset_base_url, $path), '');
		} elseif (preg_match('#^video/#', $type)) {
			$content = video(str_replace($asset_base_path, $asset_base_url, $path));
		} elseif (preg_match('#^audio/#', $type)) {
			$content = audio(str_replace($asset_base_path, $asset_base_url, $path));
		} else {
			$content = tag('a')->attr('href', str_replace($asset_base_path, $asset_base_url, $path))->append($type);
		}

		return array('title' => $title, 'content' => $content, 'donate' => $donate);
	}

	public static function help() {
		global $current_user;
		$page = div()->addClass('help');

		if (isset($_REQUEST['entry'])) {
			list($who, $what) = explode('/', $_REQUEST['entry'], 2);
//			error_log("$who $what");
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
			
			$sidebar = div()->addClass('sidebar');

			$file = lookup('index.html', array(
				join_path($base_path, 'help', get_locale()),
				join_path($base_path, 'help', 'en_US'),
				join_path($base_path, 'help', 'assets')
					));
			if ($file) {
				$file = self::load_file($file, $who, $base_path, $base_url);
				$sidebar->append(div($file['content'])->addClass('index'));
				if ($file['donate']) {
					$sidebar->append(h(__('Is this helpful? Then please donate!', self::$domain), 4)->addClass('donate'));
					foreach ($file['donate'] as $donate) {
						$donate = preg_split('#: *#', $donate, 2);
						if ($donate[0] == 'paypal')
							$sidebar->append(self::do_paypal_button($donate[1]));
						elseif ($donate[0] == 'flattr')
							$sidebar->append(self::do_flattr_button($donate[1]));
					}
				}
			}

			$file = lookup($what, array(
				join_path($base_path, 'help', get_locale()),
				join_path($base_path, 'help', 'en_US'),
				join_path($base_path, 'help', 'assets')
					));
			if ($file) {
				$file = self::load_file($file, $who, $base_path, $base_url);
				$page->append(div($file['content'])->addClass('content'));
			} else {
				$page->append(div(__('Whoops, looks like this page is missing!', self::$domain))->addClass('content'));
			}
			$page->append($sidebar);
		} else {
			$page->addClass('index');
			$page->append(h(sprintf(__('Welcome %s', self::$domain), $current_user->data->display_name), 1));

			$index = lookup('index.html', array(
				join_path(get_home_path(), 'help', get_locale()),
				join_path(get_home_path(), 'help', 'en_US'),
					));
			if ($index) {
				$index = self::load_file($index, 'wordpress', get_home_path(), get_site_url());
				$page->append(h(__('General', self::$domain), 3), p($index['content']));
			}

			if (is_multisite()) {
				$upd = wp_upload_dir();
				$index = lookup('index.html', array(
					join_path($upd['basedir'], 'help', get_locale()),
					join_path($upd['basedir'], 'help', 'en_US'),
						));
				if ($index) {
					$index = self::load_file($index, 'you', $upd['basedir'], $upd['baseurl']);
					$page->append(h(__('Especially for you', self::$domain), 1), p($index['content']));
				}
			}


			$page->append(h(__('Plugins', self::$domain), 3));
			$page->append($list = tag('ol'));
			foreach (self::$plugins as $plugin) {
				$base_path = dirname($plugin);
				$index = lookup('index.html', array(
					join_path($base_path, 'help', get_locale()),
					join_path($base_path, 'help', 'en_US')
						));
				if ($index) {
					$p = get_plugin_data($plugin);
					$index = self::load_file($index, basename($base_path), $base_path, plugin_dir_url($plugin));
					$list->append(
							tag('li')->append(
									h($p['Name'], 4), p($index['content'])
							)
					);
				}
			}
		}
		echo $page;
	}

}

WP_Easy_Help::init();