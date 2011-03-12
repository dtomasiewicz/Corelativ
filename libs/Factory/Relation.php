<?php
	namespace Corelativ\Factory;
	use \Corelativ\ModelQuery;
	
	abstract class Relation extends \Corelativ\Factory {
		
		/**
		 * @var Model|Factory The subject of this relationship
		 */
		private $Subject;
		
		/**
		 * @var string The name of the foreign key used in this relationship.
		 *             Should be set in the constructor of the extending class.
		 */
		private $foreignKeyField;
		
		/**
		 * @var string The aliased relationship name (used for detecing
		 *             foreign key names)
		 */
		private $alias;
		
		public function __construct($config, $subject) {
			parent::__construct($config);
			$this->Subject = $subject;
			$this->alias = isset($config['alias'])
				? $config['alias']
				: $config['model'];
			$this->tableAlias(isset($config['tableAlias'])
				? $config['tableAlias']
				: $this->alias);
		}
		
		public function __get($name) {
			if($name == 'Subject') {
				return $this->Subject;
			} else {
				return parent::__get($name);
			}
		}
		
		public function alias($set = false) {
			if($set !== false) {
				$this->alias = $set;
			}
			return $this->alias;
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
				: $this->backReference($this, $target);
			
			$query = new ModelQuery(ModelQuery::SELECT, $object->className());
			$query->fields('`'.$object->tableAlias().'`.*')
				->from($this->getFrom());
			
			if($filters = $this->getFilters()) {
				$query->where($filters);
			}
			
			if($where !== null) {
				$query->where($where);
			}
			
			return $query;
		}
		
		public function findOne($where = null, $target = null) {
			return $this->find($where, $target)->limit(1);
		}
		
		public function delete($where = null, $target = null) {
			$object = $target === null
				? $this
				: $this->backReference($this, $target);
				
			$query = new ModelQuery(ModelQuery::DELETE, $object->className());
			$query->table($object->tableName());
			$query->from($this->getFrom());
			
			if($filters = $this->getFilters()) {
				$query->where($filters); 
			}
			
			if($where !== null) {
				$query->where($where);
			}
			
			return $query;
		}
		
		public function subject() {
			return $this->Subject;
		}
		
		public function foreignKeyField($set = false) {
			if($set !== false) {
				$this->foreignKeyField = $set;
			}
			return $this->foreignKeyField;
		}
		
		protected function backReference($object, $target) {
			while($object instanceof Relation) {
				if($object->tableAlias() == $target) {
					return $object;
				}
				$object = $object->Subject;
			}
			//@todo exception
			exit('Invalid backward identity: '.$target);
		}
		
		abstract public function getFilters();
		abstract public function getFrom();
	}