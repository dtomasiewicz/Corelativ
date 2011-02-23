<?php
	namespace Corelativ;
	use \DataPane\Data,
	    \DataPane\Query;
	
	class ModelQuery extends Query {
		protected $_className;
		
		public function __construct($type, $className) {
			parent::__construct($type);
			$this->_className = $className;
		}
		
		public function fetchAll($params = array(), $connection = null, $driverOptions = array()) {
			$class = $this->_className;
			$set = new ModelSet($class);
			foreach(parent::fetchAll($params, $this->_connectionName($connection)) as $row) {
				$set->push(new $class($row));
			}
			return $set;
		}
		
		public function fetch($params = array(), $connection = null, $driverOptions = array()) {
			if($row = parent::fetch($params, $this->_connectionName($connection))) {
				$class = $this->_className;
				return new $class($row);
			} else {
				return false;
			}
		}
		
		public function getSQL($connection = null) {
			return $this->getSQL($this->_connectionName($connection));
		}
		
		public function execute($params = array(), $connection = null, $driverOptions = array()) {
			return parent::execute($params, $this->_connectionName($connection), $driverOptions);
		}
		
		protected function _connectionName($override = null) {
			if($override !== null) {
				return $override;
			} else {
				$c = $this->_className;
				return $c::connectionName();
			}
		}
	}