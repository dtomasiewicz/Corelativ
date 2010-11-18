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
			if($this->_Subject instanceof Relation) {
				return $this->_Subject->getFrom().' LEFT JOIN '.$this->_from().' ON '.
					$this->_Subject->tableAlias().'.'.$this->_Subject->primaryKeyField().' = '.$this->tableAlias().'.'.$this->foreignKeyField();
			} elseif($this->_Subject instanceof Factory || $this->_Subject instanceof Model) {
				return $this->_from();
			} else {
				//@todo exception
				exit('bad subject');
			}
		}
		
		public function getFilters() {
			if($this->_Subject instanceof Model) {
				return $this->tableAlias().'.'.$this->foreignKeyField().' = '.$this->_Subject->primaryKey();
			} elseif($this->_Subject instanceof Relation) {
				return $this->_Subject->getFilters();
			} else {
				return null;
			}
		}
	}