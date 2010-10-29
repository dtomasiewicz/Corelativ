<?php
	namespace Corelativ;
	use \Frawst\Exception,
	    \DataPane,
	    \DataPane\Data;
	
	class Factory {
		protected $_modelClass;
		protected $_relations = array();
		
		public function __construct($config) {
			$this->_modelClass = '\\Corelativ\\Model\\'.$config['model'];
		}
		
		public function __get($name) {
			if(isset($this->_relations[$name])) {
				return $this->_relations[$name];
			} elseif(($rels = $this->related()) && isset($rels[$name])) {
				$related = $rels[$name];
				if(is_string($related)) {
					return $related = array('type' => $related);
				}
				$related['alias'] = $name;
				if(!isset($related['model'])) {
					$related['model'] = $name;
				}
				$class = '\\Corelativ\\Factory\\'.$related['type'];
				return new $class($related, $this);
			} else {
				//@todo exception
				exit('invalid relation: '.$name);
			}
		}
		
		public function find($where = null) {
			$query = new ModelQuery(ModelQuery::SELECT, $this->_modelClass);
			$query->fields($this->tableName().'.*')->from($this->tableName());
			if($where !== null) {
				$query->where($where);
			}
			return $query;
		}
		
		public function findOne($where = null) {
			return $this->find($where)->limit(1);
		}
		
		public function fetch() {
			return $this->findOne()->fetch();
		}
		
		public function fetchAll() {
			return $this->find()->fetchAll();
		}
		
		public function delete($where = null) {
			$query = new ModelQuery(ModelQuery::DELETE, $this->_modelClass);
			$query->from($this->tableName());
			if($where !== null) {
				$query->where($where);
			}
			return $query;
		}
		
		public function tableName() {
			$c = $this->_modelClass;
			return $c::tableName();
		}
		
		public function modelClass() {
			return $this->_modelClass;
		}
		
		public function primaryKeyField() {
			$c = $this->_modelClass;
			return $c::primaryKeyField();
		}
		
		public function related() {
			$c = $this->_modelClass;
			return $c::related();
		}
		
		public function connectionName() {
			$c = $this->_modelClass;
			return $c::connectionName();
		}
	}