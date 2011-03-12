<?php
	namespace Corelativ;
	use \DataPane\Data,
	    \DataPane\Query;
	
	class ModelQuery extends Query {
		private $className;
		
		public function __construct($type, $className) {
			parent::__construct($type);
			$this->className = $className;
		}
		
		public function fetchAll($params = array(), $connection = null, $driverOptions = array()) {
			$class = $this->className;
			$set = new ModelSet($class);
			foreach(parent::fetchAll($params, $this->connectionName($connection)) as $row) {
				$set->push(new $class($row));
			}
			return $set;
		}
		
		public function fetch($params = array(), $connection = null, $driverOptions = array()) {
			if($row = parent::fetch($params, $this->connectionName($connection))) {
				$class = $this->className;
				return new $class($row);
			} else {
				return false;
			}
		}
		
		public function getSQL($connection = null) {
			return $this->getSQL($this->connectionName($connection));
		}
		
		public function execute($params = array(), $connection = null, $driverOptions = array()) {
			return parent::execute($params, $this->connectionName($connection), $driverOptions);
		}
		
		protected function connectionName($override = null) {
			if($override !== null) {
				return $override;
			} else {
				$c = $this->className;
				return $c::connectionName();
			}
		}
	}