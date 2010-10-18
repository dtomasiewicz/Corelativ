<?php
	namespace Corelativ\Factory;
	use \Corelativ\Model;
	
	class BelongsTo extends Singular {
		public function _uniqueProperty() {
			if ($this->_related === false) {
				return array($this->_Object->primaryKeyField() => $this->_Subject->{$this->_objectKeyField});
			} elseif (is_null($this->_related)) {
				// don't return anything if the related object has been unrelated
				return array('1 LIT' => 2);
			} else {
				return array($this->_Object->primaryKeyField() => $this->_related->primaryKey());
			}
		}
		
		public function save() {
			if ($this->_changed) {
				if ($this->_related instanceof Model) {
					$this->_Subject->{$this->_objectKeyField} = $this->_related->primaryKey();
				} else {
					$this->_Subject->{$this->_objectKeyField} = 0;
				}
				// false means don't save relationships-- otherwise we'd get an infinite loop
				$this->_Subject->save(false);
				$this->_changed = false;
			}
		}
	}