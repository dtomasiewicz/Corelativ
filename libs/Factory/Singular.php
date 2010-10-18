<?php
	namespace Corelativ\Factory;
	use \Corelativ\Model,
		\DataPane\ConditionSet,
		\DataPane\Query;
	
	abstract class Singular extends Relation {
		protected $_related = false;
		protected $_changed = false;
		
		protected function _uniqueCondition() {
			return new ConditionSet($this->_uniqueProperty());
		}
		
		public function set($id = null) {
			if ($id instanceof Model) {
				$this->_related = $id;
			} elseif (is_null($id) || $id == 0) {
				$this->_related = null;
			} else {
				// allow them to change properties on the fly
				$properties = array();
				if (is_array($id)) {
					$properties = $id;
					$id = $properties[$this->_Object->primaryKeyField()];
				}
				
				// want to find WITHOUT restrictions
				$this->_related = parent::find(new ConditionSet(array(
					$this->_Object->primaryKeyField() => $id
				)));
				
				if ($this->_related) {
					$this->_related->set($properties);
				}
			}
			
			$this->_changed = true;
		}
		
		public function validate() {
			if ($this->_related instanceof Model) {
				return $this->_related->validate() ? true : $this->related->errors();
			} else {
				return true;
			}
		}
		
		abstract protected function _uniqueProperty();
	}