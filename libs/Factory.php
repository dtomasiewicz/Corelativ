<?php
	namespace Corelativ;
	use \Frawst\Exception,
	    \DataPane,
	    \DataPane\Data;
	
	class Factory {
		
		/**
		 * @var string The class name of the object
		 */
		protected $_className;
		
		/**
		 * @var string The name of the object's table
		 */
		protected $_tableName;
		
		/**
		 * @var string The alias to be used for the object's table in queries
		 */
		protected $_tableAlias;
		
		/**
		 * @var string The object's primary key field
		 */
		protected $_primaryKeyField;
		
		/**
		 * @var string The object's connection name
		 */
		protected $_connectionName;
		
		/**
		 * @var array (sub)relations
		 */
		protected $_relations = array();
		
		public function __construct($config) {
			$this->_className = '\\Corelativ\\Model\\'.$config['model'];
			$c = $this->_className;
			$this->_tableName = $c::tableName();
			$this->_primaryKeyField = $c::primaryKeyField();
			$this->_connectionName = $c::connectionName();
			$this->_related = $c::relations();
			$this->_tableAlias = isset($config['tableAlias'])
				? $config['tableAlias']
				: $this->_tableName;
		}
		
		public function __get($name) {
			if(isset($this->_relations[$name])) {
				return $this->_relations[$name];
			} elseif($relation = $this->relate($name)) {
				return $relation;
			} else {
				//@todo exception
				exit('invalid relation: '.$name);
			}
		}
		
		public function relate($alias, $as = null) {
			if ($as === null && isset($this->_relations[$alias])) {
				return $this->_relations[$alias];
			} elseif(isset($this->_related[$alias])) {
				// create the relation object
				$related = $this->_related[$alias];
				if (is_string($related)) {
					$related = array('type' => $related);
				}
				if($as !== null) {
					$related['tableAlias'] = $as;
				}
				$related['alias'] = $alias;
				if (!isset($related['model'])) {
					$related['model'] = $alias;
				}
				$class = '\\Corelativ\\Factory\\'.$related['type'];
				$relation = new $class($related, $this);
				
				// if not using a table alias, store this for future use
				if($as === null) {
					$this->_relations[$alias] = $relation;
				}
				
				return $relation;
			} else {
				return false;
			}
		}
		
		public function find($where = null) {
			$query = new ModelQuery(ModelQuery::SELECT, $this->_className);
			$query->fields($this->tableAlias().'.*')->from($this->_from());
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
			$query = new ModelQuery(ModelQuery::DELETE, $this->_className);
			$query->from($this->_from());
			if($where !== null) {
				$query->where($where);
			}
			return $query;
		}
		
		public function tableName() {
			return $this->_tableName;
		}
		
		public function tableAlias() {
			return $this->_tableAlias;
		}
		
		/**
		 * @return string The from clause-- table name and optionally, table alias,
		 *                for this factory
		 */
		protected function _from() {
			return $this->_tableName.' AS '.$this->_tableAlias;
		}
		
		public function className() {
			return $this->_className;
		}
		
		public function primaryKeyField() {
			return $this->_primaryKeyField;
		}
		
		public function relations() {
			$c = $this->_className;
			return $c::relations();
		}
		
		public function connectionName() {
			return $this->_connectionName;
		}
	}