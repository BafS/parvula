<?php

namespace Parvula;

use DOMDocument;

/**
 * Plugin class @TODO
 * Abstract class, need to be inherited to create a new plugin.
 *
 * Minimal exemple :
 * ```
 * namespace Plugins\Slider;
 *
 * class Slider extends \Parvula\Plugin { ... }
 * ```
 *
 * @version 0.8.0
 * @since 0.5.0
 * @author Fabien Sa
 * @license MIT License
 */
abstract class Plugin
{
	/**
	 * @var string Plugin path
	 */
	protected $pluginPath;

	/**
	 * @var string Plugin URI
	 */
	protected $pluginUri;

	/**
	 * @var \Pimple\Container Application to avoid global variables or static class
	 */
	protected $app;

	public function __construct() {
		$this->app = app();
		$this->pluginPath = $this->getPluginPath();

		$prefixPlugin = $this->app['config']->get('pluginsUrlPrefix', '');
		$this->pluginUri = $prefixPlugin . $this->getPluginUri();
	}

	/**
	 * Get the current plugin path, useful for the backend part.
	 *
	 * @param  string $suffix optional Suffix
	 * @return string the current plugin path
	 */
	protected function getPath($suffix = '') {
		$class = str_replace('\\', '/', static::class);
		$class = dirname($class);
		$class = str_replace('Plugins/', '', $class);

		return _PLUGINS_ . $class . '/' . $suffix;
	}

	/**
	 * Alias for getPath.
	 *
	 * @see getPath
	 */
	protected function getPluginPath($suffix = '') {
		return $this->getPath($suffix);
	}

	/**
 	 * Get the current plugin URI, useful for the client part.
 	 *
 	 * @param string $suffix optional Suffix
 	 * @return string the current URI path
 	 */
	protected function getUri($suffix = '') {
		return url($this->getPluginPath() . $suffix);
	}

	/**
	 * Alias for getUri.
	 *
	 * @see getUri
	 */
	protected function getPluginUri($suffix = '') {
		return $this->getUri($suffix);
	}

	/**
	 * Append string to the given element.
	 *
	 * @param  string $html
	 * @param  string $append
	 * @return string Html ouput
	 */
	protected function appendToElement($element, $html, $append) {
		// @TODO a bit hacky, need to clean and find correctly the `</head>`

		if (strlen($html) < 10) {
			return false;
		}

		libxml_use_internal_errors(true); // html5 ok
		$dom = new DOMDocument();
		$dom->loadHTML($html);
		$node = $dom->getElementsByTagName($element)->item(0);

		$lineNo = 0;
		if ($node) {
			$lineNo = $node->lastChild->getLineNo();
		}

		$outArr = explode("\n", $html);

		// Lines to skip to go to the end of the node
		$linesToSkip = substr_count($node->nodeValue, "\n");
		$outArr[($lineNo - 1) + $linesToSkip] .= PHP_EOL . $append;

		return implode($outArr, "\n");
	}

	/**
	 * Append string to the header element (<head>).
	 *
	 * @param  string $html   Html to modify
	 * @param  string $append Html to append
	 * @return string Html ouput
	 */
	protected function appendToHeader($html, $append) {
		return preg_replace('/(< ?\\/ ?head)/', $append . '$1', $html);
	}

	/**
	 * Append string to the end of the body element (right before </body>).
	 *
	 * @param  string $html   Html to modify
	 * @param  string $append Html to append
	 * @return string Html ouput
	 */
	protected function appendToBody($html, $append) {
		return preg_replace('/(< ?\\/ ?body)/', $append . '$1', $html);
	}
}
