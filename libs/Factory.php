<?php
	namespace Corelativ;
	use \Frawst\Exception,
	    \DataPane,
	    \DataPane\Data;
	
	class Factory {
		
		/**
		 * @var string The class name of the object
		 */
		private $className;
		
		/**
		 * @var string The name of the object's table
		 */
		private $tableName;
		
		/**
		 * @var string The alias to be used for the object's table in queries
		 */
		private $tableAlias;
		
		/**
		 * @var string The object's primary key field
		 */
		private $primaryKeyField;
		
		/**
		 * @var string The object's connection name
		 */
		private $connectionName;
		
		/**
		 * @var array (sub)relations
		 */
		private $relations = array();
		
		public function __construct($config) {
			$this->className = '\Corelativ\Model\\'.$config['model'];
			$c = $this->className;
			$this->tableName = $c::tableName();
			$this->primaryKeyField = $c::primaryKeyField();
			$this->connectionName = $c::connectionName();
			$this->related = $c::relations();
			$this->tableAlias = isset($config['tableAlias'])
				? $config['tableAlias']
				: null;
		}
		
		public function __get($name) {
			if(isset($this->relations[$name])) {
				return $this->relations[$name];
			} elseif($relation = $this->relate($name)) {
				return $relation;
			} else {
				//@todo exception
				exit('invalid relation: '.$name);
			}
		}
		
		public function create($properties = null) {
			$class = $this->className;
			$new = new $class();
			if($properties !== null) {
				$new->set($properties);
			}
			return $new;
		}
		
		public function relate($alias, $as = null) {
			if ($as === null && isset($this->relations[$alias])) {
				return $this->relations[$alias];
			} elseif(isset($this->related[$alias])) {
				// create the relation object
				$related = $this->related[$alias];
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
				$class = '\Corelativ\Factory\\'.$related['type'];
				$relation = new $class($related, $this);
				
				// if not using a table alias, store this for future use
				if($as === null) {
					$this->relations[$alias] = $relation;
				}
				
				return $relation;
			} else {
				return false;
			}
		}
		
		public function find($where = null) {
			$query = new ModelQuery(ModelQuery::SELECT, $this->className);
			$query->fields('`'.$this->tableAlias().'`.*')->from($this->from());
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
			$query = new ModelQuery(ModelQuery::DELETE, $this->className);
			$query->from($this->from());
			if($where !== null) {
				$query->where($where);
			}
			return $query;
		}
		
		public function tableName($set = false) {
			if($set !== false) {
				$this->tableName = $set;
			}
			return $this->tableName;
		}
		
		public function tableAlias($set = false) {
			if($set !== false) {
				$this->tableAlias = $set;
			}
			return $this->tableAlias === null ? $this->tableName : $this->tableAlias;
		}
		
		/**
		 * @return string The from clause-- table name and optionally, table alias,
		 *                for this factory
		 */
		protected function from() {
			$from = '`'.$this->tableName.'`';
			if($this->tableAlias !== null) {
				$from .= ' AS `'.$this->tableAlias.'`';
			}
			return $from;
		}
		
		public function className($set = false) {
			if($set !== false) {
				$this->className = $set;
			}
			return $this->className;
		}
		
		public function primaryKeyField($set = false) {
			if($set !== false) {
				$this->primaryKeyField = $set;
			}
			return $this->primaryKeyField;
		}
		
		public function relations() {
			$c = $this->className;
			return $c::relations();
		}
		
		public function connectionName($set = false) {
			if($set !== false) {
				$this->connectionName = $set;
			}
			return $this->connectionName;
		}
		
		public function __call($method, $args) {
			if(method_exists($this->className, $method)) {
				return call_user_func_array(array($this->className, $method), $args);
			} else {
				throw new \Exception('Invalid factory method: '.$method);
			}
		}
	}