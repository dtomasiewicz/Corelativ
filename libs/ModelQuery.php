<?php
	namespace Corelativ;
	use \DataPane\Data,
	    \DataPane\Query;
	
	class ModelQuery extends Query {
		protected $_Object;
		
		public function __construct($type, $object) {
			parent::__construct($type);
			$this->_Object = $object;
		}
		
		public function fetchAll($params = array()) {
			$statement = Data::connection($this->_Object->connectionName())->prepare($this);
			$statement->execute($params);
			$class = get_class($this->_Object);
			$set = new ModelSet(get_class($this->_Object));
			foreach($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
				$set->add(new $class($row));
			}
			return $set;
		}
		
		public function fetch($params = array()) {
			$statement = Data::connection($this->_Object->connectionName())->prepare($this);
			$statement->execute($params);
			$class = get_class($this->_Object);
			if($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
				return new $class($row);
			} else {
				return false;
			}
		}
	}