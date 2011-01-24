<?php
	namespace Corelativ;
	use \Frawst\Matrix;
	
	/**
	 * Hierarchy-based model collection class to allow for easy hierarchy
	 * management and caching (automatically pulls all children recursively).
	 * Assumes your model has a self-referencing hasMany relationship aliased
	 * "Children."
	 */
	class Hierarchy extends ModelSet {
		protected $_structure;
		protected $_lookup;
		
		/**
		 * If a ModelSet is passed as the first parameter, the type will be
		 * automatically pulled from the ModelSet.
		 */
		public function __construct($type, $roots = array()) {
			if ($type instanceof ModelSet) {
				$roots = $type;
				$type = $roots->modelType();
			}
			parent::__construct($type);
			
			$this->_structure = array();
			$this->_lookup = array();
			
			$roots->indexByPrimaryKey();
			$this->computeStructure($roots);
		}
		
		/**
		 * Recursively indexes the hierarchy when it is created.
		 */
		private function computeStructure($parents, $base = '') {
			$parents->indexByPrimaryKey();
			
			foreach ($parents as $id => $parent) {
				Matrix::pathSet($this->_structure, $base.$id, array());
				$this->_lookup[$id] = $base.$id;
				$this->computeStructure($parent->Children->fetchAll(), $base.$id.'.');
			}
			
			$this->merge($parents, true);
		}
		
		/**
		 * Gets the children of the specified model. If $deep is true,
		 * will get a recursive list of children.
		 */
		public function childrenOf($id, $deep = false) {
			if ($id instanceof Model) {
				$id = $id->primaryKey();
			}
			
			$lookup = $id == 0 ? null : $this->_lookup[$id];
			$children = array_keys(Matrix::pathGet($this->_structure, $lookup));
			$set = new ModelSet($this->modelType());
			
			foreach ($children as $id) {
				$set[$id] = $this[$id];
				if ($deep) {
					$set->merge($this->childrenOf($id), $deep);
				}
			}
			return $set;
		}
		
		/**
		 * Returns the parent of the specified model.
		 */
		public function parentOf($id) {
			if ($id instanceof Model) {
				$id = $id->primaryKey();
			}
			$lookup = explode('.', $this->_lookup[$id]);
			array_pop($lookup);
			if (!is_null($parent = array_pop($lookup))) {
				return $this[$parent];
			} else {
				return false;
			}
		}
		
		/**
		 * Returns the path to the specified model.
		 */
		public function pathTo($id, $includeThis = true) {
			if ($id instanceof Model) {
				$id = $id->primaryKey();
			}
			$lookup = explode('.', $this->_lookup[$id]);
			if (!$includeThis) {
				array_pop($lookup);
			}
			$set = new ModelSet($this->modelType());
			foreach ($lookup as $path) {
				$set[] = $this[$path];
			}
			return $set;
		}
	}