<?php

namespace Parvula\Core;

/**
 * Page type
 * 
 * @package Parvula
 * @version 0.1.0
 * @author Fabien Sa
 * @license MIT License
 */
class Page {
//implements Serializable {
	public $title;
	public $description;
	public $author;
	public $date;
	public $robots;
	public $content;

	/**
	 * Overrid when print this object
	 * @return string
	 */
	public function __tostring() {
		return json_encode($this);
	}

}
