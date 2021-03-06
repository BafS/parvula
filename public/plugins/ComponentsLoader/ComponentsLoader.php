<?php

namespace Plugins\ComponentsLoader;

use Exception;
use Parvula\Plugin;
use Parvula\Models\Page;
use function Parvula\pluginsPath;

if (!defined('_APP_')) exit;

// Dev.4
class ComponentsLoader extends Plugin {

	/**
	 * @var array Components in the page
	 */
	private $components = [];

	const COMPONENTS_DIR = '_components';

	public function onPage(Page &$page) {
		foreach ($page->sections as $id => $section) {
			if ($section->name[0] === ':') {
				$section->component = ltrim($section->name, ':');
			}

			if (isset($section->component)) {
				$componentName = str_replace('../', '', $section->component);
				$path = $this->getComponentPath($componentName);

				// Simple file component
				if (is_readable($path)) {
					$data = [
						'page' => $page,
						'section' => $section
					];
					$arr = $this->renderComponent($componentName, $data);
					$page->sections[$id]->content = $arr['render'];
					$this->components[$section->name] = [
						'section' => $section,
						'instance' => $arr
					];
				}
			}
		}
	}

	public function onPostRender(&$out) {
		foreach ($this->components as $component) {
			$obj = $component['instance'];
			$componentName = $component['section']->component;

			if (isset($obj['header'])) {
				$header = $obj['header'];
				$out = $this->appendToHeader($out, $header($this->getUri('../' . $componentName . '/')));
			}

			if (isset($obj['body'])) {
				$body = $obj['body'];
				$out = $this->appendToBody($out, $body($this->getUri('../' . $componentName . '/')));
			}
		}
	}

	private function renderComponent($componentName, array $data) {
		if (is_readable($filePath = $this->getComponentPath($componentName))) {
			$plugin = null;
			if (($pluginName = dirname($componentName)) !== '.') {
				$pluginsMediator = $this->app['plugins'];
				$plugin = $pluginsMediator->getPlugin(getPluginClassname($pluginName));
			}

			$data['uri'] = $this->getUri('../' . $componentName . '/');

			return $this->render($filePath, $data, $plugin);
		}
	}

	/**
	 * Render a given component
	 *
	 * @param  string  $path Component file path
	 * @param  array   $data Data to pass to the component
	 * @param  mixed   $bind To bind $this to the given class
	 * @return array
	 */
	private function render($path, array $data, $bind = null) {
		ob_start();
		$fun = (function () use ($path, $data) {
			extract($data);
			return require $path;
		});
		$fun = $fun->bindTo($bind);
		$arr = $fun();
		$out = ob_get_clean();

		if ($arr !== (array) $arr) {
			$arr = [];
		}

		if (!isset($arr['render'])) {
			$arr['render'] = $out;
		} else {
			$arr['render'] = $arr['render']($data, $out);
		}

		return $arr;
	}

	public function onRouterAPI(&$router) {
		$components = $this->components;
		$render = function ($componentName, $section) {
			return $this->renderComponent($componentName, $section);
		};

		$getComponentPath = function ($path) {
			return $this->getComponentPath($path);
		};

		$router->group('/components', function () use ($getComponentPath, $components, $render) {
			require 'api.php';
		});
	}

	/**
	 * Get component path
	 *
	 * @param  string $component
	 * @return string
	 */
	protected function getComponentPath($component) {
		$file = basename($component) . '.php';
		$path = rtrim(dirname($component), '/') . '/';
		return pluginsPath($path . self::COMPONENTS_DIR . '/' . $file);
	}
}
