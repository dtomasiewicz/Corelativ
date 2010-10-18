<?php
	namespace Corelativ\Factory;
	use \Corelativ\Model,
		\DataPane,
		\DataPane\Data;
	
	class HasMany extends Multiple {
		//@todo add support for relating non-saved models?
		protected function _uniqueCondition() {
			// make sure it's not flagged for removal
			//@todo make this return entries in additions as well?
			$condition = new DataPane\ConditionSet($this->_uniqueProperty() + array(
				$this->_Object->primaryKeyField().' NOT IN' => array_keys($this->_removals)
			));
			return $condition;
		}
		
		protected function _uniqueProperty() {
			return array($this->_subjectKeyField => $this->_Subject->primaryKey());
		}
		
		public function add($object, $properties = array()) {
			if ($object instanceof Iterator || is_array($object)) {
				// adding multiple objects-- add each individually
				foreach ($object as $obj) {
					if (is_array($obj)) {
						$this->add($obj[$this->_objectPrimaryKeyField], $obj);
					} else {
						$this->add($obj);
					}
				}
			} else {
				if (!($object instanceof Model)) {
					// the actual adding
					$object = parent::find(new DataPane\ConditionSet(array(
						$this->_objectPrimaryKeyField => $object
					)));
				}
				
				if ($object instanceof Model) {
					$this->_additions[$object->primaryKey()] = $object;
					$object->set($properties);
					if (isset($this->_removals[$object])) {
						unset($this->_removals[$object]);
					}
				}
			}
		}
		
		public function remove($object) {
			if ($object instanceof \Iterator || is_array($object)) {
				// removing multiple objects-- remove each individually
				foreach ($object as $obj) {
					$this->remove($obj);
				}
			} else {
				if ($object instanceof Model) {
					$object = $object->primaryKey();
				}
				
				if ($object) {
					$this->_removals[$object] = $object;
					if (isset($this->_additions[$object])) {
						unset($this->_additions[$object]);
					}
				}
			}
		}
		
		public function create($data = array()) {
			return parent::create($this->_uniqueProperty() + $data);
		}
		
		public function validate() {
			$errors = array();
			foreach ($this->_additions as $addition) {
				$validate = $addition->validate();
				if (is_array($validate)) {
					$errors[$addition->primaryKey()] = $validate;
				}
			}
			return (count($errors) == 0) ? true : $errors;
		}
		
		public function save() {
			// if this represents ALL of the associated objects, de-associate any objects that aren't in additions
			if ($this->_allInclusive) {
				$delink = new DataPane\Query('update', $this->_objectTableName);
				$delink->values = array(
					$this->_subjectKeyField => 0
				);
				$delink->where = new DataPane\ConditionSet(array(
					$this->_subjectKeyField => $this->_Subject->primaryKey()
				));
				Data::source($this->_Object->dataSource())->query($delink);
			}
			
			// remove defuct associations
			if (count($this->_removals)) {
				$remove = new DataPane\Query('update', $objectTable);
				$remove->values = array(
					$this->_subjectKeyField => 0
				);
				$remove->where = new DataPane\ConditionSet(array(
					$this->_objectPrimaryKeyField => array_keys($this->_removals)
				));
				Data::source($this->_Object->dataSource())->query($remove);
			}
			
			// add new associations
			foreach ($this->_additions as $addition) {
				$addition->{$this->_subjectKeyField} = $this->_Subject->primaryKey();
				$addition->save();
			}
			
			$this->_allInclusive = false;
			$this->_resetLists();
		}
		
		protected function _resetLists() {
			$this->_additions = array();
			$this->_removals = array();
		}
	}