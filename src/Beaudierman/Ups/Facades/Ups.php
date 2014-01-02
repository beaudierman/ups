<?php namespace Beaudierman\Ups\Facades;

use Illuminate\Support\Facades\Facade;

class Ups extends Facade {

	/**
	 * Get the registered name of the component.
	 *
	 * @return string
	 */
	protected static function getFacadeAccessor() { return 'ups'; }

}