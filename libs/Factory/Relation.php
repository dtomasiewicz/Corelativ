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
		
		public function __get($name) {
			if($name == 'Subject') {
				return $this->_Subject;
			} else {
				return parent::__get($name);
			}
		}
		
		public function alias() {
			return $this->_alias;
		}
		
		/**
		 * If provided, $target should be the alias of a parent relationship.
		 * For example if you wanted to find all of the courses in which a student
		 * got a grade of A+, you could do something like this:
		 * 
		 * $student->Courses->CourseGrades->Grade->find('Grade.grade = "A+"', 'Courses');
		 * 
		 * And this will return the Course objects instead of the Grade objects.
		 */
		public function find($where = null, $target = null) {
			$object = $target === null
				? $this
				: $this->_backReference($this, $target);
			
			$query = new ModelQuery(ModelQuery::SELECT, $object->modelName());
			$query->fields($object->tableName().'.*')
				->from($this->getFrom());
			
			if($filters = $this->getFilters()) {
				$query->where($filters);
			}
			
			if($where !== null) {
				$query->where($where);
			}
			
			return $query;
		}
		
		protected function _backReference($object, $target) {
			while($object instanceof Relation) {
				if($object->alias() == $target) {
					return $object;
				}
				$object = $object->Subject;
			}
			//@todo exception
			exit('Invalid backward identity: '.$target);
		}
		
		public function findOne($where = null, $target = null) {
			return $this->find($where, $target)->limit(1);
		}
		
		public function delete($where = null) {
			$query = new ModelQuery(ModelQuery::DELETE, $object->modelName());
			$query->from($this->getFrom());
			
			if($filters = $this->getFilters()) {
				$query->where($filters); 
			}
			
			if($where !== null) {
				$query->where($where);
			}
			
			return $query;
		}
		
		public function foreignKeyField() {
			return $this->_foreignKeyField;
		}
		
		abstract public function getFilters();
		abstract public function getFrom();
	}