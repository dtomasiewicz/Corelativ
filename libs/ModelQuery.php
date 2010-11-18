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
		
		public function fetchAll($params = array(), $connection = null) {
			$statement = Data::connection($this->_connectionName($connection))->prepare($this);
			$statement->execute((array)$params);
			
			$class = $this->_className;
			$set = new ModelSet($class);
			foreach($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
				$set->push(new $class($row));
			}
			return $set;
		}
		
		public function fetch($params = array(), $connection = null) {
			$statement = Data::connection($this->_connectionName($connection))->prepare($this);
			$statement->execute((array)$params);
			$class = $this->_className;
			if($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
				return new $class($row);
			} else {
				return false;
			}
		}
		
		public function getSQL($connection = null) {
			return Data::connection($this->_connectionName($connection))->getSQL($this);
		}
		
		public function execute($params = array(), $connection = null) {
			return parent::execute($params, $this->_connectionName($connection));
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