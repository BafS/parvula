<?php

namespace Parvula\Models;

use Closure;
use Parvula\AccessorTrait;
use Parvula\ArrayableInterface;

/**
 * This abstract class represents a Model.
 *
 * @version 0.8.0
 * @since 0.8.0
 * @author Fabien Sa
 * @license MIT License
 */
abstract class Model implements ArrayableInterface
{
	use AccessorTrait;

	protected $lazy = [];

	/**
	 * Transform the instance fields to an array.
	 *
	 * @return array Array of instance's fields
	 */
	public function toArray(): array {
		$arr = $this->getVisibleFields();

		foreach ($arr as $key => $value) {
			if ($value instanceof Model) {
				$arr[$key] = $value->toArray();
			} elseif ($value instanceof Closure) {
				$arr[$key] = $value();
			}
		}

		// Resolve lazy functions
		foreach ($this->lazy as $key => $value) {
			if ($value instanceof Closure) {
				$arr[$key] = $value();
			}
		}

		return $arr;
	}

	/**
	 * Get all visible fields.
	 *
	 * @return array Visible fields
	 */
	protected function getVisibleFields(?bool $removeNull = false): array {
		$fields = $this->getAllFields();
		$res = [];

		if (isset($this->visible)) {
			$res = array_intersect_key($fields, array_flip($this->visible));
		} elseif (isset($this->invisible)) {
			// Notice: It will also remove the 'invisible' and 'lazy' field
			$this->invisible[] = 'invisible';
			$this->invisible[] = 'lazy';
			$res = array_diff_key($fields, array_flip($this->invisible));
		}

		if ($removeNull) {
			return array_filter($res, function ($value) {
				return $value !== null;
			});
		}

		return $res;
	}

	/**
	 * Get all fields from an instance.
	 *
	 * @return array
	 */
	private function getAllFields() {
		$acc = [];
		foreach ($this as $key => $value) {
			$acc[$key] = $value;
		}

		return $acc;
	}

	/**
	 * Transform model.
	 *
	 * @param  callable $fun Callback function for the model
	 * @return mixed
	 */
	public function transform(callable $fun) {
		return $fun($this);
	}

	/**
	 * @param  string $name
	 * @return mixed
	 */
	public function __get(string $name) {
		if (isset($this->lazy[$name]) && $this->lazy[$name] instanceof Closure) {
			return $this->lazy[$name]();
		}
	}

	/**
	 * @param  string  $name
	 * @param  Closure $val
	 * @return mixed
	 */
	public function __set(string $name, $val) {
		if ($val instanceof Closure) {
			return $this->lazy[$name] = $val;
		}

		return $this->$name = $val;
	}

	/**
	 * @param  string $name
	 * @return mixed
	 */
	public function __isset($name) {
		return isset($this->lazy[$name]) && $this->lazy[$name] instanceof Closure;
	}

	/**
	 * @param  string $name
	 * @return mixed
	 */
	public function __unset($name) {
		if (isset($this->lazy[$name]) && $this->lazy[$name] instanceof Closure) {
			unset($this->lazy[$name]);
		}
    }
}
