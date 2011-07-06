<?php

require_once('tag.class.php');

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
		return $p->append($text);
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

	function pre() {
		$pre = tag('pre');
		$elements = func_get_args();
		foreach ($elements as $element)
			$pre->append("\n", htmlentities2(print_r($element, true)));
		return $pre;
	}

}

if (!function_exists('code')) {

	function code() {
		$pre = tag('code')->css(array(
			'white-space' => 'pre'
		));

		$elements = func_get_args();
		foreach ($elements as $element)
			$pre->append("\n", $code);
		return $pre;
	}

}

if (!function_exists('options')) {

	function options($elements, $selected) {
		$g = group();
		foreach ($elements as $value => $key) {
			$m = tag('option')->attr('value', $value)->append($key);
			if ($selected == $value)
				$m->attr('selected', 'selected');
			$g->append($m);
		}
		return $g;
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

	function img($src, $alt = '') {

		return tag('img')->attr(array(
					'src' => $src,
					'alt' =>
					$alt
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