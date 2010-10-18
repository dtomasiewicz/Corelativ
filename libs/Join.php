<?php
	namespace Corelativ;
	
	/**
	 * Generic Join class that impersonates a Model _only_ as much
	 * as is necessary for Relations to treat it as such. Does NOT
	 * extend model because a model must have a unique table, whereas
	 * different types of joins will have different tables.
	 */
	class Join {
		protected $_data;
		
		public function __construct($data = array()) {
			$this->_data = $data;
		}
		public function __isset($name) {
			return array_key_exists($name, $this->_data);
		}
		public function __unset($name) {
			unset($this->_data[$name]);
		}
		public function __get($name) {
			return $this->_data[$name];
		}
		public function __set($name, $value) {
			$this->_data[$name] = $value;
		}
	}