<?php

//if (!function_exists('tag')) {
//
//class Tag {
//
//private $name = '';
//private $forceClose = false;
//private $attributes = array();
//protected $children = array();
//private $style = array();
//private $classes = array();
//
//private function parseCSS($input) {
//$input = strpos($input, ';') > -1 ? explode($input, ';') : array($input);
//foreach ($input as $entry) {
//$m = explode(':', $entry, 2);
//if ($m)
//$this->style[trim($m[0])] = trim($m[1]);
//}
//}
//
//public function __construct($tagName, $forceClose = false) {
////		parent::__construct();
//$this->name = $tagName;
//$this->forceClose = $forceClose;
//}
//
//public function attr($name, $value = NULL) {
//if ($value !== NULL)
//$this->attributes[$name] = $value;
//elseif (is_array($name))
//$this->attributes = array_merge($this->attributes, $name);
//else
//if ($value == 'style')
//return clone $this->style;
//elseif ($value == 'class')
//return clone $this->classes;
//else
//return $this->attributes[$name];
//if (isset($this->attributes['style'])) {
//$this->parseCSS($this->attributes['style']);
//unset($this->attributes['style']);
//}
//if (isset($this->attributes['class'])) {
//$this->addClass($this->attributes['class']);
//unset($this->attributes['class']);
//}
//return $this;
//}
//
//public function css($name, $value = NULL) {
//if ($value !== NULL)
//$this->style[$name] = $value;
//elseif (is_array($name))
//$this->style = array_merge
//($this->style, $name);
//elseif (strpos($name, ':') > -1)
//$this->parseCSS($name);
//else
//return $this->style[$name];
//return $this;
//}
//
//private function cleanClasses($args) {
////			$args = func_get_args();
//$classes = array();
//foreach ($args as $arg)
//if (is_array($arg))
//$classes = array_merge($classes, $this->cleanClasses($arg));
//elseif (strstr($arg, ' ') > -1)
//$classes = array_merge($cla
//sses, $this->cleanClasses
//(explode(' ', $arg)));
//else
//$classes[] = $arg;
//return
//
//$classes;
//}
//
//public funct
//ion addClass() {
//
//$classes =
//
//func_get_args();
//$this->classes = array_merge($this->classes, $this->cleanClasses($classes));
//$this->classes = array_unique($this->classes);
//return $this;
//}
//
//public function removeClass($classes) {
//if (!is_array($classes))
//if (strstr($classes, ' ') > -1)
//$classes = explode(' ', $classes);
//else
//$classes = array($classes);
//foreach ($classes as $class)
//$this->classes = array_diff($this->classes, $classes);
//return
//
//$this;
//}
//
//public function append($elements = array()) {
//
//if(func_num_args() > 1)
//$elements = func_get_args();
//if(is_array($elements))
//$this->children = array_merge($this->children, $elements);
//else
//array_push($this->children, $elements);
//return $this;
//}
//
//public function __toString() {
//if($this->name) {
//$result = '<' . $this->name;
//foreach($this->attributes as $key => $value)
//$result .= ' ' . $key . '="' . htmlentities2($value) . '"';
//if(count($this->style)) {
//$css = array();
//foreach ( $this->style as $key => $value) {
//if (!$value)
//continue;
//if (intval($value))
//$value = "{$value}px";
//elseif (floatval($value))
//$value = "{$value}pt";
//elseif (is_array($value))
//$value = implode(' ', $value);
//else
//$value = strval($value);
//array_push($css, "{$key}: {$value}");
//}
//$css = implode('; ', $css);
//$result .= ' style="' . htmlentities2($css) . '"';
//}
//if (count($this->classes)) {
//
//$result .= ' class="' . htmlentities2(implode(' ', $this->classes)) . '"';
//}
//}
//if (count($this->children) || $this->forceClose) {
//$result .= $this->name ? '>' : '';
//foreach ($this->children as $child)
//$result .= $child;
//$result .= $this->name ? "</{$this->name}>" : '';
//} else
//$result .= $this->name ? ' />' : '';
//return
//
//$result;
//}
//}
//
//class TagGroup extends Tag {
//
//public function __construct() {
//parent::__construct('');
//}
//
//private function apply($method, $args) {
//foreach ($this->children as $child)
//call_user_method($method, $child, $args);
//}
//
//public function addClass($classes) {
//$args = func_get_args();
//$this->apply('addClass', $args);
//return
//
//$this;
//}
//
//public funct
//ion removeClass($classes) {
//
//$args
//
//= func_get_args();
//$this->apply('removeClass', $args);
//return $this;
//}
//
////		public function append($elements) {
////			$args = func_get_args();
////			$this->apply('addClass', $args);
////		}
//
//public function attr($name, $value = NULL) {
//$args = func_get_args();
//$this->apply('attr', $args);
//return $this;
//}
//
//public function css($name, $value = NULL) {
//$args = func_get_args();
//$this->apply('css', $args);
//return $this;
//}
//
//}
//
//// TODO Move this to __invoke?
//function tag($tagName, $forceClose = false) {
//return new Tag($tagName, $forceClose);
//}
//
//// TODO Move this to __invoke?
//function group() {
//$elements = func_get_args();
//$g = new TagGroup();
//return $g->append($elements);
//}
//}

if (!function_exists('br')) {

	function br() {
		return tag('br', false);
	}

}

if (!function_exists('hr')) {

	function hr() {
		return tag('hr', false);
	}

}

if (!function_exists('div')) {

	function div() {
		$elements = func_get_args();
		$div = tag('div', true);
		return call_user_func_array(array(& $div, 'append'), $elements);
	}

}

if (!function_exists('p')) {

	function p($text) {
		$p = tag('p', true);
		return

				$p->append($text);
	}

}

if (!function_exists('span')) {

	function span($text) {
		$span = tag('span', true);
		return $span->append($text);
	}

}

if (!function_exists('h')) {

	function h($text, $size = 1) {
		$span = tag('h' . $size, true);
		return $span->append($text);
	}

}

if (!function_exists('iframe')) {

	function iframe($src, $noframe = null) {
		if (!$noframe)
			$noframe = __('This feature requires inline frames. You have iframes disabled or your browser does not support them.');
		$iframe = tag('iframe', true);
		return $iframe->attr('src', $src)->append($noframe);
	}

}

if (!function_exists('script')) {

	function script($parameters) {
		extract(array_merge(array(
					'src' => null,
					'type' => 'text/javascript',
					'code' => null,
						), $parameters));
		$m = tag('script', true);
		if ($src)
			$m->attr('src', $src);
		if ($code)
			$m->append("//<!--\n", $code, "\n//-->");
		return $m->attr('type', $type);
	}

}

if (!function_exists('pre')) {

	function
	pre() {
		$pre = tag('pre');
		$elements = func_get_args();
		foreach ($elements as $element)
			$pre->append("\n", htmlentities2(print_r($element, true)));
		return

				$pre;
	}

}

if (!function_exists('label')) {

	function label($for, $text) {
		return tag('label')->attr('for', $for)->append($text);
	}

}

if (!function_exists('checkbox')) {

	function checkbox($name, $id, $checked = false, $text = false, $value = false) {
		$cb = tag('input')->attr(array(
					'type' => 'checkbox',
					'id' => $id,
					'name' => $name
				));
		if ($checked)
			$cb->attr('checked', 'checked');
		if ($value)
			$cb->attr('value', $value);
		if ($text)
			$cb = group($cb, label($id, $text), br());
		return $cb;
	}

}

if (!function_exists('img')) {

	function img
	($src, $alt = '') {

		return tag('img')->attr(array(
					'src' => $src,
					'alt' => $alt
				));
	}

}

if (!function_exists('video')) {

	function video($src) {
		return tag('video')->attr('src', $src);
	}

}

if (!function_exists('audio')) {

	function audio($src) {
		return tag('audio')->attr('src', $src);
	}

}