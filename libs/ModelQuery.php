<?php
	namespace Corelativ;
	use \DataPane\Data,
	    \DataPane\Query;
	
	class ModelQuery extends Query {
		protected $_modelClass;
		
		public function __construct($type, $modelClass) {
			parent::__construct($type);
			$this->_modelClass = $modelClass;
		}
		
		public function fetchAll($params = array()) {
			$statement = Data::connection(call_user_func(array($this->_modelClass, 'connectionName')))->prepare($this);
			$statement->execute((array)$params);
			
			$class = $this->_modelClass;
			$set = new ModelSet($class);
			foreach($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
				$set->push(new $class($row));
			}
			return $set;
		}
		
		public function fetch($params = array()) {
			$statement = Data::connection(call_user_func(array($this->_modelClass, 'connectionName')))->prepare($this);
			$statement->execute((array)$params);
			$class = $this->_modelClass;
			if($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
				return new $class($row);
			} else {
				return false;
			}
		}
		
		public function getSQL($connection = null) {
			if($connection === null) {
				$connection = call_user_func(array($this->_modelClass, 'connectionName'));
			}
			return Data::connection($connection)->getSQL($this);
		}
	}