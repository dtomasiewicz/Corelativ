<?php
	namespace Corelativ\Factory;
	use \Corelativ\ModelQuery;
	
	abstract class Relation extends \Corelativ\Factory {
		protected $_Subject;
		protected $_foreignKeyField;
		protected $_alias;
		
		public function __construct($config, $subject) {
			parent::__construct($config);
			$this->_Subject = $subject;
			$this->_alias = isset($config['alias'])
				? $config['alias']
				: $config['model'];
		}
		
		public function alias() {
			return $this->_alias;
		}
		
		public function find() {
			$query = new ModelQuery(ModelQuery::SELECT, $this->_Object);
			$query->fields($this->tableName().'.*')
				->from($this->getFrom());
			
			if($filters = $this->getFilters()) {
				$query->where($filters);
			}
			
			if(count($args = func_get_args())) {
				call_user_func_array(array($query, 'where'), $args);
			}
			
			return $query;
		}
		
		public function delete() {
			$query = new ModelQuery(ModelQuery::DELETE, $this->_Object);
			$query->from($this->getFrom());
			
			if($filters = $this->getFilters()) {
				$query->where($filters); 
			}
			
			if(count($args = func_get_args())) {
				call_user_func_array(array($query, 'where'), $args);
			}
			
			return $query;
		}
		
		public function foreignKeyField() {
			return $this->_foreignKeyField;
		}
		
		abstract public function getFilters();
		abstract public function getFrom();
	}