<?php
	namespace Corelativ;
	use \Corelativ\Factory;
	
	/**
	 * The main Corelativ ORM wrapper.
	 */
	abstract class Mapper {
		protected static $_config;
		protected static $_factories = array();
		
		public static function init($config = array()) {
			self::$_config = $config;
		}
		
		public static function factory($modelName) {
			if (!isset(self::$_factories[$modelName])) {
				if (class_exists('\\Corelativ\\Model\\'.$modelName)) {
					self::$_factories[$modelName] = new Factory(array('model' => $modelName));
				} else {
					self::$_factories[$modelName] = false;
				}
			}
			
			return self::$_factories[$modelName];
		}
	}