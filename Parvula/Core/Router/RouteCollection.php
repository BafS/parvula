<?php

namespace Parvula\Core\Router;

use FastRoute\DataGenerator;
use FastRoute\DataGenerator\GroupCountBased as GroupCountBasedDataGenerator;
use FastRoute\RouteCollector;
use FastRoute\RouteParser;
use FastRoute\RouteParser\Std as StdRouteParser;

/**
 * RouteCollection
 * Wraper for FastRoute\RouteCollector
 *
 * @package Parvula
 * @version 0.5.0
 * @since 0.5.0
 * @author Fabien Sa
 * @license MIT License
 */
class RouteCollection extends RouteCollector {

	/**
	 * @var string Prefix used for groups
	 */
	private $prefix = '';

	/**
	 * Constructor
	 *
	 * @param RouteParser $parser (optional)
	 * @param DataGenerator $generator (optional)
	 */
	public function __construct(RouteParser $parser = null, DataGenerator $generator = null) {
		$parser    = ($parser instanceof RouteParser) ? $parser : new StdRouteParser;
		$generator = ($generator instanceof DataGenerator) ? $generator : new GroupCountBasedDataGenerator;

		parent::__construct($parser, $generator);
	}

	/**
	 * Add a new route with "GET" method
	 *
	 * @param string $route
	 * @param callable $handler
	 */
	public function get($route, callable $handler) {
		$this->addRoute('GET', $this->prefix . $route, $handler);
	}

	/**
	 * Add a new route with "POST" method
	 *
	 * @param string $route
	 * @param callable $handler
	 */
	public function post($route, callable $handler) {
		$this->addRoute('POST', $this->prefix . $route, $handler);
	}

	/**
	 * Add a new route with "PUT" method
	 *
	 * @param string $route
	 * @param callable $handler
	 */
	public function put($route, callable $handler) {
		$this->addRoute('PUT', $this->prefix . $route, $handler);
	}

	/**
	 * Add a new route with "DELETE" method
	 *
	 * @param string $route
	 * @param callable $handler
	 */
	public function delete($route, callable $handler) {
		$this->addRoute('DELETE', $this->prefix . $route, $handler);
	}

	/**
	 * Add a new route with "HEAD" method
	 *
	 * @param string $route
	 * @param callable $handler
	 */
	public function head($route, callable $handler) {
		$this->addRoute('HEAD', $this->prefix . $route, $handler);
	}

	/**
	 * Add a new route with "PATCH" method
	 *
	 * @param string $route
	 * @param callable $handler
	 */
	public function patch($route, callable $handler) {
		$this->addRoute('PATCH', $this->prefix . $route, $handler);
	}

	/**
	 * Add a new route
	 *
	 * @param string $method
	 * @param string $route
	 * @param callable $handler
	 */
	public function map($method, $route, callable $handler) {
		if (is_string($method)) {
			$method = explode('|', $method);
		}
		$this->addRoute($method, $this->prefix . $route, $handler);
	}

	/**
	 * Group, to prefix a group of routes
	 *
	 * @param string $prefix
	 * @param callable $handler
	 */
	public function group($prefix, callable $handler) {
		$that = clone $this;
		$that->prefix = $prefix;
		$handler($that);
	}
}
