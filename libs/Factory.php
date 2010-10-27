<?php
	namespace Corelativ;
	use \Frawst\Exception,
	    \DataPane,
	    \DataPane\Data;
	
	class Factory {
		protected $_Object;
		protected $_relations = array();
		
		public function __construct($config) {
			$class = '\\Corelativ\\Model\\'.$config['model'];
			$this->_Object = new $class($this);
		}
		
		public function __get($name) {
			$rels = $this->_Object->related();
			if(isset($this->_relations[$name])) {
				return $this->_relations[$name];
			} elseif(isset($rels[$name])) {
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
			$query = new ModelQuery(ModelQuery::SELECT, $this->modelName());
			$query->fields($this->_Object->tableName().'.*')->from($this->_Object->tableName());
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
			$query = new ModelQuery(ModelQuery::DELETE, $this->modelName());
			$query->from($this->tableName());
			if($where !== null) {
				$query->where($where);
			}
			return $query;
		}
		
		public function modelName() {
			return get_class($this->_Object);
		}
		
		public function tableName() {
			return $this->_Object->tableName();
		}
		
		public function primaryKeyField() {
			return $this->_Object->primaryKeyField();
		}
		
		/**
		 * Magic methods to allow this factory to behave transparently
		 * as an empty instance of the model it creates.
		 */
		public function __call($method, $args) {
			if (method_exists($this->_Object, $method)) {
				return call_user_func_array(array($this->_Object, $method), $args);
			} else {
				//@todo exception
				exit('Invalid factory method: '.$method);
			}
		}
	}