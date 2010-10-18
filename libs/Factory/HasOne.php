<?php
	namespace Corelativ\Factory;
	use \DataPane;
	
	class HasOne extends Singular {
		public function _uniqueProperty() {
			//@TODO make this behavior different if object is set
			return array($this->_subjectKeyField => $this->_Subject->primaryKey());
		}
		
		public function save() {
			if ($this->_changed) {
				$objectTable = $this->_Object->tableName();
			
				// unlink old relation if exists
				$delink = new DataPane\Query('update', $objectTable, array());
				$delink->values = array(
					$this->_subjectKeyField => 0
				);
				$delink->where = new DataPane\ConditionSet(array(
					$this->_subjectKeyField => $this->_Subject->primaryKey()
				));
				$delink->limit = 1;
				$delink->exec($this->_Object->dataSource());
			
				// link the new association
				if ($this->_related instanceof \Corelativ\Model) {
					$this->_related->{$this->_subjectKeyField} = $this->_Subject->primaryKey();
					if ($this->_related->save()) {
						$this->_changed = false;
					}
				} else {
					$this->_changed = false;
				}
			}
		}
	}