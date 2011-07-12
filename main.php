<?php
/*
Plugin Name:	WP Easy Help
Plugin URI:		http://tacosw.com/htmledit/download.php
Description:	A help system that integrates into the wordpress admin panel.
Version:		1.0.0
Author:			Benjamin Kleiner, Christoph Fritsch
Author URI:		https://github.com/bizzl-greekdog
License:		LGPL3
*/
/*
    Copyright (c) 2011 Benjamin Kleiner <bizzl@users.sourceforge.net>
    Copyright (c) 2011 Christoph Fritsch <christoph@orange-d.net>
 
    This file is part of WP Easy Help.

    WP Easy Help is free software: you can redistribute it and/or modify
    it under the terms of the GNU Lesser General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    WP Easy Help is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Lesser General Public License for more details.

    You should have received a copy of the GNU Lesser General Public License
    along with WP Easy Help. If not, see <http://www.gnu.org/licenses/>.
*/

if (!function_exists('join_path')) {

	// This is an implementation of pythons sys.path.join()
	// As the name suggest, join_path takes all arguments and joins
	// them using the directory separator.
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

/**
 * LookUpPath allows us to easily search a file in multiple pathes,
 * abstracting all that looping hassle.
 */
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

// A functional interface to LookUpPath.
function lookup($filename, $pathes) {
	$lup = new LookUpPath($pathes);
	return $lup->lookup($filename);
}

/**
 * CallbackContext allows for predefined arguments for callback functions.
 */
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

/**
 * Another abstraction. This one encapsulates both wordpress options interface
 * and the serialization/deserialization stuff.
 */
if (!class_exists('WpOption')) {
	class WpOption {
		private $key = '';
		private $default = '';
		
		public function	__construct($key, $default) {
			$this->key = $key;
			$this->default = $default;
		}
		
		public function get() {
			$v = get_option($this->key, NULL);
			if ($v === NULL)
				$v = $this->default;
			if (is_serialized($v))
				$v = unserialize($v);
			return $v;
		}
		
		public function set($new) {
			return update_option($this->key, maybe_serialize($new));
		}
		
		public function __invoke($p = NULL) {
			if ($p === NULL)
				return $this->get();
			else
				return $this->set($p);
		}
	}
}

/**
 * Our main class and the actual plugin.
 */
class WP_Easy_Help {

	protected static $domain = 'wp-easy-help';
	protected static $base = '';
	protected static $plugins = array();
	
	protected static $display_options = 'wp_easy_help_display';
	protected static $display_options_default = array(
				'show-general' => true,
				'show-custom' => true,
				'show-plugin' => true,
	);
	
	protected static $filter_options = 'wp_easy_help_filter';
	protected static $filter_options_default = array(
		'administrator' => array(),
		'author' => array(),
		'editor' => array(),
		'contributor' => array(),
		'subscriber' => array()
	);

	/// Caches the base directory.
	protected static function init_base() {
		self::$base = basename(dirname(__FILE__));
	}

	/// Loads the text domain for gettext.
	protected static function init_l10n() {
		$j = join_path(self::$base, 'locale');
		load_plugin_textdomain(self::$domain, false, $j);
	}

	/// 
	public static function init() {
		self::init_base();
		self::init_l10n();
		self::$display_options = new WpOption(self::$display_options, self::$display_options_default);
		self::$filter_options = new WpOption(self::$filter_options, self::$filter_options_default);
		if (function_exists('wp_get_active_network_plugins'))
			self::$plugins = wp_get_active_network_plugins();
		self::$plugins = array_merge(self::$plugins, wp_get_active_and_valid_plugins());
		add_action('admin_menu', array(__CLASS__, 'menu_init'));
		add_action('wp_print_scripts', array(__CLASS__, 'print_scripts'));
		add_action('admin_init', array(__CLASS__, 'init_scripts'));
	}
	
	/**
	 * Loads necessary scripts and styles. See action @admin_init.
	 * This function also handles the saving of screen options.
	 *
	 * Sorry.
	 */
	public static function init_scripts() {
		if (!isset($_REQUEST['entry']))
			add_filter('screen_settings', array(__CLASS__, 'screen_options'), 10, 2);
		wp_enqueue_style('woah-main', join_path(plugin_dir_url(__FILE__), 'css', 'main.css'));
		wp_enqueue_script('jquery');
		
		
		$px = self::$domain . '-screen-options-';
		if (isset($_POST["{$px}screen-options"])) {
			self::$display_options->set(array_merge(
				self::$display_options->get(),
				array(
					'show-general' => isset($_POST["{$px}show-general"]),
					'show-custom' => isset($_POST["{$px}show-custom"]),
					'show-plugin' => isset($_POST["{$px}show-plugin"]),
				)
			));
			die();
		}
	}
	
	/// Gets filters for the current users role.
	public static function get_filters() {
		foreach (self::$filter_options->get() as $role => $filters)
			if (current_user_can($role))
				return $filters;
		return array();
	}

	/// Prints the inside of the screen options. See filter @screen_settings. 
	public static function screen_options($current, $screen){
		if ($screen->parent_file != 'online-help')
			return $current;
		$px = self::$domain . '-screen-options-';
		$p = self::$display_options->get();
		return group(
			script(array(
				'code' => <<<EOF
jQuery(function($) {
		$("input[type=checkbox][id*={$px}show-]").change(function() {
			var b = $(this).attr("id").replace(/^{$px}show-/, "");
			$("#" + b + "-help").css('display', $(this).attr("checked") ? "block" : "none");
			b = $("#adv-settings").serialize();
			$("#adv-settings input").attr("disabled", "disabled");
			$.ajax({
				type: "POST",
				cached: false,
				url: window.location.href,
				data: b,
				success: function(msg) {
					$("#adv-settings input").removeAttr("disabled");
				}
			});
		});
});
EOF
			)),
			tag('input')->attr(array(
				'type' => 'hidden',
				'name' => "{$px}screen-options",
				'value' => 'Test'
			)),
			tag('ul')->append(
				tag('li')->append(checkbox("{$px}show-general", "{$px}show-general", $p['show-general'], __("Show general help", self::$domain))),
				tag('li')->append(checkbox("{$px}show-custom", "{$px}show-custom", $p['show-custom'], __("Show custom help", self::$domain))),
				tag('li')->append(checkbox("{$px}show-plugin", "{$px}show-plugin", $p['show-plugin'], __("Show plugin help", self::$domain)))
			)
		);
	}

	/**
	 * No clue why wp_enqueue_script doesn't like flattr.
	 * See action @wp_print_scripts.
	 */
	public static function print_scripts() {
		echo script(array('src' => 'http://api.flattr.com/js/0.6/load.js?mode=auto'))->attr('id', 'flattr');
	}

	/// Adds pages for content and options. See action @admin_menu.
	public static function menu_init() {
		add_options_page(__('WP Easy Help', self::$domain), __('WP Easy Help', self::$domain), 'manage_options', 'online-help-settings', array(__CLASS__, 'help_settings'));
		add_menu_page(__('Help', self::$domain), __('Help', self::$domain), 'edit_posts', 'online-help', array(__CLASS__, 'help'), '', 3);
	}

	/// Creates a paypal button.
	public static function do_paypal_button($id) {
		list($u, $l) = explode('_', get_locale(), 2);
		return tag('form')
						->addClass('donate')
						->attr(array('action' => 'https://www.paypal.com/cgi-bin/webscr', 'method' => 'post'))
						->append(
								tag('input')->attr(array('type' => 'hidden', 'name' => 'cmd', 'value' => '_s-xclick')), tag('input')->attr(array('type' => 'hidden', 'name' => 'hosted_button_id', value => $id)), tag('input')->attr(array('type' => 'image', 'src' => "https://www.paypalobjects.com/{$u}_{$l}/{$l}/i/btn/btn_donate_LG.gif", 'border' => 0, 'name' => 'submit', alt => 'donate')), img("https://www.paypalobjects.com/{$u}_{$l}/i/scr/pixel.gif", '')->attr(array('width' => 1, 'height' => 1, 'border' => 0))
		);
	}

	/// Creates a flattr button.
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

	/**
	 * Callback for the /src/-preg_replace. Rebases the src attributes so they
	 * point to the right files.
	 */
	public static function rebase_src($matches, $request_base, $asset_base_path, $asset_base_url) {
		if (preg_match('#^[a-z0-9]+://#', $matches[1])) // Keep remote assets.
			return $matches[0];
		if (preg_match('#^(\.\./)+assets#', $matches[1])) // The url points to the assets dir, so rebasing is easy.
			$src = join_path($asset_base_url, 'help', preg_replace('#^(\.\./)+#', '', $matches[1]));
		else
			// Looks like we have to search.
			$src = lookup($matches[1], array(
				join_path($asset_base_path, 'help', get_locale()),
				join_path($asset_base_path, 'help', 'en_US'),
				join_path($asset_base_path, 'help', 'assets')
					));

		// Prepare the base url so we can turn absolute pathes into absolute urls
		if ($asset_base_url[strlen($asset_base_url) - 1] != '/')
			$asset_base_url .= '/';
			
		if ($src)
			$src = str_replace($asset_base_path, $asset_base_url, $src);
		else
			$src = 'Whoops, looks like the developer fucked it up!';
			
		return "src=\"{$src}\"";
	}

	/**
	 * Callback for /href/-preg_replace. Rebases the href attributes so they
	 * point to the right pages.
	 */
	public static function rebase_href($matches, $request_base, $asset_base_path, $asset_base_url) {
		if (preg_match('#^[a-z0-9]+://#', $matches[1]) || preg_match('/^#/', $matches[1])) // Keep outgoing and anchor links.
			return $matches[0];
			
		if (preg_match('#^(\.\./)+assets#', $matches[1])) // looks like the url is pointing to an asset.
			return 'href="' . join_path($asset_base_url, 'help', preg_replace('#^(\.\./)+#', '', $matches[1])) . '" target="_blank"';
			
		return "href=\"admin.php?page=online-help&entry={$request_base}/{$matches[1]}\"";
	}

	/**
	 * Loads a file and processes it so all src and href attributes
	 * point to the right places and illegal tags get removed.
	 */
	public static function load_file($path, $request_base = 'wordpress', $asset_base_path = '', $asset_base_url = '') {
		$type = mime_content_type($path);
		$title = basename($path);
		$donate = array();
		
		if (preg_match('#^text/#', $type)) {
			$content = file_get_contents($path);
			
			if (preg_match('#<title>(.*?)</title>#s', $content, $matches)) // Of course we need the files title...
				$title = $matches[1];
				
			if (preg_match_all('#<meta[^>]+>#s', $content, $metas)) // ... and some meta tags.
				foreach ($metas[0] as $meta)
					if (preg_match('#name="donate"#', $meta) && preg_match('#content="([^"]+)"#', $meta, $matches))
						$donate[] = $matches[1];

			// Remove all framing tags. We need no head, no body and html tags and doctypes.
			foreach (array('#</?(body|html)[^>]*>#i', '#<head[^>]*>.*</head>#is', '#<!(?!--)[^>]+>#') as $pattern)
				$content = preg_replace($pattern, '', $content);
			
			// Remove all illegal tags. embeds, objects, links and remote scripts can
			// introduce trojan horses. Use video and audio for multimedia assets.
			foreach (array('script[^>]*src=', 'embed', 'object', 'link') as $forbidden)
				$content = preg_replace("#<{$forbidden}[^>]*>(?:.*?</{$forbidden}[^>]*>)?#i", '', $content);

			// Rebase external urls.
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

	/**
	 * Produces the help page.
	 */
	public static function help() {
		global $current_user;
		$page = div()->addClass('help');
		$display_options = self::$display_options->get();

		if (isset($_REQUEST['entry'])) { // The user requested a help page.
			// Split request into search domain and actual file.
			list($who, $what) = explode('/', $_REQUEST['entry'], 2);
			$plugin = array_shift(preg_grep("#{$who}#", self::$plugins));
			
			if ($who == 'wordpress') { // 'wordpress' is for general help.
				$base_path = get_home_path();
				$base_url = get_site_url();
			} elseif ($who == 'you') { // 'you' is for custom help.
				$upd = wp_upload_dir();
				$base_path = $upd['basedir'];
				$base_url = $upd['baseurl'];
			} else { // Everything else must be a plugin...
				$base_path = dirname($plugin);
				$base_url = plugin_dir_url($plugin);
			}
			
			// The sidebar contains the currents search domains index.
			$sidebar = div()->addClass('sidebar');

			$file = lookup('index.html', array(
				join_path($base_path, 'help', get_locale()),
				join_path($base_path, 'help', 'en_US'),
				join_path($base_path, 'help', 'assets')
					));
			if ($file) {
				$file = self::load_file($file, $who, $base_path, $base_url);
				$sidebar->append(h($file['title'], 4)->addClass('title'), div($file['content'])->addClass('index'));
				if ($file['donate']) { // We got donation targets? add necessary buttons.
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

			// Lookup the actually requested file.
			// Maybe we shouldn't use the functional interface?
			$file = lookup($what, array(
				join_path($base_path, 'help', get_locale()),
				join_path($base_path, 'help', 'en_US'),
				join_path($base_path, 'help', 'assets')
					));
			if ($file) {
				$file = self::load_file($file, $who, $base_path, $base_url);
				$page->append(div(h($file['title'], 1), $file['content'])->addClass('content'));
			} else {
				$page->append(div(__('Whoops, looks like this page is missing!', self::$domain))->addClass('content'));
			}
			$page->append($sidebar);
		} else { // Get the main index.
			$p = self::$display_options->get();
			$page->addClass('index');
			$page->append(h(sprintf(__('Welcome %s', self::$domain), $current_user->data->display_name), 1));

			// Lookup and fetch the index of the general help.
			$index = lookup('index.html', array(
				join_path(get_home_path(), 'help', get_locale()),
				join_path(get_home_path(), 'help', 'en_US'),
					));
			if ($index) {
				$index = self::load_file($index, 'wordpress', get_home_path(), get_site_url());
				$index = div(
					h(__('General', self::$domain), 3),
					p($index['content'])
				)->attr('id', 'general-help');
				if (!$display_options['show-general'])
					$index->css('display', 'none');
				$page->append($index);
			}

			// In case we have a network, we have the possibility of custom help files
			// for certain blogs.
			if (is_multisite()) {
				$upd = wp_upload_dir();
				$index = lookup('index.html', array(
					join_path($upd['basedir'], 'help', get_locale()),
					join_path($upd['basedir'], 'help', 'en_US'),
						));
				if ($index) {
					$index = self::load_file($index, 'you', $upd['basedir'], $upd['baseurl']);
					$index = div(
						h(__('Individual', self::$domain), 3),
						p($index['content'])
					)->attr('id', 'custom-help');
					if (!$display_options['show-custom'])
						$index->css('display', 'none');
					$page->append($index);
				}
			}

			// Lookup and fetch the help for all enabled plugins.
			$plugins_div = div()->attr('id', 'plugin-help');
			$plugins_div->append(h(__('Plugins', self::$domain), 3));
			$plugins_div->append($list = tag('ol'));
			$filters = self::get_filters();
			foreach (self::$plugins as $plugin) {
				$base_path = dirname($plugin);
				$base_name = basename(dirname($plugin));
				if (in_array($base_name, $filters))
					continue;
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
			if (!$display_options['show-plugin'])
				$plugins_div->css('display', 'none');
			$page->append($plugins_div);
			
		}
		echo $page; // print the whole shit.
	}
	
	// Prints the help settings, allowing the admin to hide the help for certain plugins.
	public static function help_settings() {
		
		$nonce_field = 'wp-easy-help-settings';
		$filters = self::$filter_options->get();
		
		if (isset($_POST['set_filter']) && wp_verify_nonce($_POST[$nonce_field], $nonce_field)) {
			foreach ($filters as $role => $_)
				$filters[$role] = $_POST[$role];
			self::$filter_options->set($filters);
		}
		
		$roles = array(
			'administrator' => __('Administrator', self::$domain),
			'editor' => __('Editor', self::$domain),
			'author' => __('Author', self::$domain),
			'contributor' => __('Contributor', self::$domain),
			'subscriber' => __('Subscriber', self::$domain),
		);
		
		$header = tag('tr')->append(tag('th')->addClass('manage-column')->attr('scope', 'col')->append('&nbsp;'));
		foreach ($roles as $role => $display_name)
			$header->append(tag('th')->addClass('manage-column')->attr('scope', 'col')->append($display_name));
			
		$checkboxes = group();
		foreach (self::$plugins as $plugin) {
			$base_path = dirname($plugin);
			$base_name = basename(dirname($plugin));
			$index = lookup('index.html', array(
				join_path($base_path, 'help', get_locale()),
				join_path($base_path, 'help', 'en_US')
			));
			if ($index) {
				$p = get_plugin_data($plugin);
				$l = tag('tr')->addClass('alternate', 'author-self', 'status-inherit')->append(tag('td')->append($p['Name']));
				foreach ($roles as $role => $_)
					$l->append(
						tag('td')->append(
							checkbox("{$role}[]", "{$role}-{$b}", in_array($base_name, $filters[$role]), false, $base_name)
						)
					);
				$checkboxes->append($l);
			}
		}
		
		echo div(tag('form')->attr('method', 'post')->append(
			wp_nonce_field($nonce_field, $nonce_field, true, false),
			p(__('Do not show the following plugins in the index table:', self::$domain)),
			tag('table')->append(
				tag('thead')->append($header),
				tag('tbody')->append($checkboxes)
			)->addClass('wp-list-table', 'widefat', 'fixed'),
			'<br />',
			tag('button')->attr(array('type' => 'submit', 'name' => 'set_filter'))->append(__('Submit', self::$domain))->addClass('button-secondary')
		))->addClass('wrap', 'help-settings');
	}

}

WP_Easy_Help::init();