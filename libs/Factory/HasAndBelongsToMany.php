<?php
	namespace Corelativ\Factory;
	use \Corelativ\Model,
		\Corelativ\ModelQuery,
		\Corelativ\Join,
		\DataPane;
	
	class HasAndBelongsToMany extends Multiple {
		protected $_joins;
		protected $_tableName;
		protected $_dataSource;
		protected $_Through;
		
		public function __construct($config) {
			parent::__construct($config);
			
			if (isset($config['through'])) {
				$class = '\\Corelativ\\Model\\'.$config['through'];
				$hasMany = new HasMany(array('model' => $config['through'], 'subject' => $this->_Subject));
				$this->_Through = new $class($hasMany);
				$this->_tableName = $this->_Through->tableName();
				$this->_dataSource = $this->_Through->dataSource();
			} else {
				$this->_Through = null;
				$this->_tableName = min(
					$this->_subjectAlias.'_'.$this->_objectAlias,
					$this->_objectAlias.'_'.$this->_subjectAlias
				);
				
				$this->_dataSource = isset($config['dataSource'])
					? $config['dataSource']
					: 'default';
			}
		}
		
		protected function _uniqueCondition() {
			$objectPK = $this->_Object->primaryKeyField();
			
			if ($this->_allInclusive) {
				// if it's all-inclusive, obviously every related object will be in joins or additions.
				// so no need for a subquery
				if (is_null($this->_Through)) {
					$objIds = array_keys($this->_joins)+array_keys($this->_additions);
				} else {
					$objIds = array();
					foreach ($this->_joins as $join) {
						$objIds[] = $join->{$this->_objectKeyField};
					}
					foreach ($this->_additions as $addition) {
						$objIds[] = $addition->{$this->_objectKeyField};
					}
				}
				return new DataPane\ConditionSet(array($objectPK.' IN' => $objIds));
			} else {
				if (is_null($this->_Through)) {
					// object ID could be in the table or in additions
					$condition = new DataPane\ConditionSet(array(
						$objectPK.' IN' => $this->__selectObjectKeys(),
						$objectPK.' IN ' => array_keys($this->_additions)
					), 'OR');
					
					// but object ID shouldn't be flagged for removal
					if (count($this->_removals)) {
						$condition = new DataPane\ConditionSet(array(
							$condition,
							$objectPK.' NOT IN' => array_keys($this->_removals)
						));
					}
					return $condition;
				} else {
					// object ID could be in the table or in additions. removals were
					// accounted for in selectObjectIds()
					$objectPKs = array();
					foreach ($this->_additions as $addition) {
						$objectPKs[] = $addition->{$this->_objectKeyField};
					}
					return new DataPane\ConditionSet(array(
						$objectPK.' IN' => $this->__selectObjectKeys(),
						$objectPK.' IN ' => $objectPKs
					), 'OR');
				}
			}
		}
		
		private function __selectObjectKeys() {
			// this doesn't need a datacontroller since it is always
			// parsed inside another query
			$q = new ModelQuery('select', $this->_tableName);
			$q->fields = array($this->_objectKeyField);
			$q->where = new DataPane\ConditionSet(array(
				$this->_subjectKeyField => $this->_Subject->primaryKey()
			));
			// don't include joins that are flagged for removal
			if (!is_null($this->_Through)) {
				$q->where->add($this->_Through->primaryKeyField().' NOT IN', array_keys($this->_removals));
			}
			return $q;
		}
		
		public function add($object, $supplemental = array()) {
			if ($object instanceof \Iterator || is_array($object)) {
				// adding multiple objects-- add each individually
				foreach ($object as $key => $obj) {
					if (is_array($obj)) {
						$this->add($key, $obj);
					} else {
						$this->add($obj);
					}
				}
			} elseif ($object instanceof Model) {
				// add by primaryKey, not the object!
				$this->add($object->primaryKey(), $supplemental);
			} else {
				// the actual adding
				$joinData = array(
					$this->_subjectKeyField => $this->_Subject->primaryKey(),
					$this->_objectKeyField => $object
				) + $supplemental;
				
				if (is_null($this->_Through)) {
					// since unmodelized joins contain a unique key pair, they can be removed and added
					// without being saved
					$this->_additions[$object] = new Join($joinData);
					if (isset($this->_removals[$object])) {
						unset($this->_removals[$object]);
					}
				} else {
					$this->_additions[] = $this->_Through->create($joinData);
				}
			}
		}
		
		public function remove($object) {
			if ($object instanceof \Iterator || is_array($object)) {
				// adding multiple objects-- add each individually
				foreach ($object as $obj) {
					$this->remove($obj);
				}
			} elseif ($object instanceof Model) {
				// add by primaryKey, not the object!
				$this->remove($object->primaryKey());
			} else {
				// remember, in a modelized hABTM, $object represents the join ID. otherwise it represents the object ID.
				$this->_removals[$object] = $object;
				if (is_null($this->_Through) && isset($this->_additions[$object])) {
					unset($this->_additions[$object]);
				}
				if (isset($this->_joins[$object])) {
					unset($this->_joins[$object]);
				}
			}
		}
		
		public function validate() {
			//@TODO this if you dare
			return true;
		}
		
		public function save() {
			if (is_null($this->_Through)) {
				// delete joins flagged for removal
				if ($this->_allInclusive || count($this->_removals)) {
					$delete = new Query('delete', $this->_tableName, array());
					$delete->where->add($this->_objectKeyField.' IN', array_keys($this->_removals));
					
					if ($this->_allInclusive) {
						// if all-inclusive, delete joins not found in the set
						$delete->where = new DataPane\ConditionSet(array(
							$delete->where,
							$this->_objectKeyField.' NOT IN' => array_keys($this->_joins)
						), 'OR');
					}
					
					$delete->where = new DataPane\ConditionSet(array(
						$delete->where,
						$this->_subjectKeyField => $this->_Subject->primaryKey(),
					));
					
					$delete->exec($this->_dataSource);
				}
				
				// now insert the additions
				//@TODO make this use a multiple-insert query instead of multiple queries
				foreach ($this->_additions as $objectKey => $join) {
					$add = new Query('insert', $this->_tableName, array());
					$add->options = array('IGNORE');
					$add->values = $join->data;
					$add->exec($this->_dataSource);
					$this->_joins[$objectKey] = $join;
				}
				
				//NOTE: if set was all-inclusive, it should still be
				$this->_additions = array();
				$this->_removals = array();
			} else {
				$this->__saveThrough();
			}
		}
		
		private function __saveThrough() {
			foreach ($this->_additions as $addition) {
				$addition->save();
				$this->_joins[$addition->primaryKey()] = $addition;
			}
			
			$params = array();
			$params['where'] = new DataPane\ConditionSet(array(
				$this->_objectKeyField.' IN' => array_keys($this->_removals)
			));
			
			if ($this->_allInclusive) {
				// if all-inclusive, delete joins not found in the set
				$delete->where = new DataPane\ConditionSet(array(
					$delete->where,
					$this->_Through->primaryKeyField().' NOT IN' => array_keys($this->_joins)
				), 'OR');
			}
			
			$this->_Through->delete($params);
			
			//NOTE: if set was all-inclusive, it should still be
			$this->_additions = array();
			$this->_removals = array();
		}
		
		protected function _resetLists() {
			$this->_joins = array();
			$this->_additions = array();
			$this->_removals = array();
		}
	}