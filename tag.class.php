<?php

if (!function_exists('tag')) {

	class Tag {

		protected $name = '';
		protected $forceClose = false;
		protected $attributes = array();
		protected $children = array();
		protected $style = array();
		protected $classes = array();

		private function parseCSS($input) {
			$input = strpos($input, ';') > -1 ? explode($input, ';') : array($input);
			foreach ($input as $entry) {
				$m = explode(':', $entry, 2);
				if ($m)
					$this->style[trim($m[0])] = trim($m[1]);
			}
		}

		public function __construct($tagName, $forceClose = false) {
			$this->name = $tagName;
			$this->forceClose = $forceClose;
		}

		public function attr($name, $value = NULL) {
			if ($value !== NULL) {
				$this->attributes[$name] = $value;
			} elseif (is_array($name)) {
				$this->attributes = array_merge($this->attributes, $name);
			} elseif ($value == 'style') {
				return clone $this->style;
			} elseif ($value == 'class') {
				return clone $this->classes;
			} else {
				return $this->attributes[$name];
			}

			if (isset($this->attributes['style'])) {
				$this->parseCSS($this->attributes['style']);
				unset($this->attributes['style']);
			}

			if (isset($this->attributes['class'])) {
				$this->addClass($this->attributes['class']);
				unset($this->attributes['class']);
			}

			return $this;
		}

		public function css($name, $value = NULL) {
			if ($value !== NULL) {
				$this->style[$name] = $value;
			} else if (is_array($name)) {
				$this->style = array_merge($this->style, $name);
			} else if (strpos($name, ':') > -1) {
				$this->parseCSS($name);
			} else {
				return $this->style[$name];
			}
			return $this;
		}

		private function cleanClasses($args) {
	//			$args = func_get_args();
			$classes = array();
			foreach ($args as $arg) {
				if (is_array($arg)) {
					$classes = array_merge($classes, $this->cleanClasses($arg));
				} elseif (strstr($arg, ' ') > -1) {
					$classes = array_merge($classes, $this->cleanClasses(explode(' ', $arg)));
				} else {
					$classes[] = $arg;
				}
			}
			return $classes;
		}

		public function addClass() {
			$classes = func_get_args();
			$this->classes = array_merge($this->classes, $this->cleanClasses($classes));
			$this->classes = array_unique($this->classes);
			return $this;
		}

		public function removeClass($classes) {
			if (!is_array($classes)) {
				if (strstr($classes, ' ') > -1) {
					$classes = explode(' ', $classes);
				} else {
					$classes = array($classes);
				}
			}
			foreach ($classes as $class) {
				$this->classes = array_diff($this->classes, $classes);
			}
			return $this;
		}

		public function append($elements) {
			if (func_num_args() > 1) {
				$elements = func_get_args();
			}
			if (is_array($elements)) {
				for ($i = 0; $i < count($elements); $i++)
					if (is_array($elements[$i])) {
						array_splice($elements, $i, 1, $elements[$i]);
					}
				$this->children = array_merge($this->children, $elements);
			} else {
				$this->children[] = $elements;
			}
			return $this;
		}

		public function __toString() {
			if ($this->name) {
				$result = '<' . $this->name;
				foreach ($this->attributes as $key => $value) {
					$result .= ' ' . $key . '="' . htmlentities2($value) . '"';
				}
				if (count($this->style)) {
					$css = array();
					foreach ($this->style as $key => $value) {
						if (!$value) {
							continue;
						}
						if (intval($value)) {
							$value = "{$value}px";
						} elseif (floatval($value)) {
							$value = "{$value}pt";
						} elseif (is_array($value)) {
							$value = implode(' ', $value);
						} else {
							$value = strval($value);
						}
						array_push($css, "{$key}: {$value}");
					}
					$css = implode('; ', $css);
					$result .= ' style="' . htmlentities2($css) . '"';
				}

				if (count($this->classes)) {
					$result .= ' class="' . htmlentities2(implode(' ', $this->classes)) . '"';
				}
			}
			if (count($this->children) || $this->forceClose) {
				$result .= $this->name ? '>' : '';
				foreach ($this->children as $child) {
					$result .= $child;
				}
				$result .= $this->name ? "</{$this->name}>" : '';
			} else {
				$result .= $this->name ? ' />' : '';
			}
			return $result;
		}

	}

	class TagGroup extends Tag {

		public function __construct() {
			parent::__construct('');
		}

		private function apply($method, $args) {
			foreach ($this->children as $child) {
				if (method_exists($child, $method))
					call_user_method($method, $child, $args);
			}
		}

		public function addClass($classes) {
			$args = func_get_args();
			parent::addClass($args);
			$this->apply('addClass', $this->classes);
			return $this;
		}

		public function removeClass($classes) {
			$args = func_get_args();
			parent::removeClass($args);
			$this->apply('removeClass', $args);
			return $this;
		}

		public function append($elements) {
			$args = func_get_args();
			parent::append($args);
			$this->apply('addClass', $this->classes);
			return $this;
		}

		public function attr($name, $value = NULL) {
			$args = func_get_args();
			$this->apply('attr', $args);
			return $this;
		}

		public function css($name, $value = NULL) {
			$args = func_get_args();
			$this->apply('css', $args);
			return $this;
		}

	}

	// TODO Move this to __invoke?
	function tag($tagName, $forceClose = false) {
		return new Tag($tagName, $forceClose);
	}

	// TODO Move this to __invoke?
	function group() {
		$elements = func_get_args();
		$g = new TagGroup();
		return $g->append($elements);
	}

}																			 