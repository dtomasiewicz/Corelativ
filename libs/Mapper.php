<?php
	namespace Corelativ;
	use \Corelativ\Factory;
	
	/**
	 * The main Corelativ ORM wrapper.
	 */
	abstract class Mapper {
		private static $config;
		private static $factories = array();
		
		public static function init($config = array()) {
			self::$config = $config;
		}
		
		public static function factory($modelName) {
			if (!isset(self::$factories[$modelName])) {
				if (class_exists('Corelativ\Model\\'.$modelName)) {
					self::$factories[$modelName] = new Factory(array('model' => $modelName));
				} else {
					self::$factories[$modelName] = false;
				}
			}
			
			return self::$factories[$modelName];
		}
	}