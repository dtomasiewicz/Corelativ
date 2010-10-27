<?php
	namespace Corelativ\Factory;
	use \Corelativ\Factory,
	    \Corelativ\Model;
	
	class OneToMany extends \Corelativ\Factory\Relation {
		
		public function __construct($config, $subject) {
			parent::__construct($config, $subject);
			$this->_foreignKeyField = isset($config['key'])
				? $config['key']
				: lcfirst($this->_Subject->tableName()).ucfirst($this->primaryKeyField());
		}
		
		public function getFrom() {
			if($this->_Subject instanceof Model) {
				return $this->tableName();
			} elseif($this->_Subject instanceof Relation) {
				return $this->_Subject->getFrom().' LEFT JOIN '.$this->tableName().' ON '.
					$this->_Subject->tableName().'.'.$this->_Subject->primaryKeyField().' = '.$this->tableName().'.'.$this->foreignKeyField();
			} elseif($this->_Subject instanceof Factory) {
				return $this->tableName();
			} else {
				//@todo exception
				exit('bad subject');
			}
		}
		
		public function getFilters() {
			if($this->_Subject instanceof Model) {
				return $this->tableName().'.'.$this->foreignKeyField().' = '.$this->_Subject->primaryKey();
			} elseif($this->_Subject instanceof Relation) {
				return $this->_Subject->getFilters();
			} else {
				return null;
			}
		}
	}