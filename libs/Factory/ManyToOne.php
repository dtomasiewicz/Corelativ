<?php
	namespace Corelativ\Factory;
	use \Corelativ\Factory,
	    \Corelativ\Model;
	
	class ManyToOne extends \Corelativ\Factory\Relation {
		
		public function __construct($config, $subject) {
			parent::__construct($config, $subject);
			$this->_foreignKeyField = isset($config['key'])
				? $config['key']
				: lcfirst($this->alias()).ucfirst($this->primaryKeyField());
		}
		
		public function getFrom() {
			if($this->_Subject instanceof Relation) {
				return $this->_Subject->getFrom().' LEFT JOIN '.$this->_from().' ON '.
					$this->_Subject->tableAlias().'.'.$this->foreignKeyField().' = '.$this->tableAlias().'.'.$this->primaryKeyField();
			} elseif($this->_Subject instanceof Factory || $this->_Subject instanceof Model) {
				return $this->_from();
			} else {
				//@todo exception
				exit('bad subject');
			}
		}
		
		public function getFilters() {
			if($this->_Subject instanceof Model) {
				return $this->tableAlias().'.'.$this->primaryKeyField().' = '.$this->_Subject->{$this->foreignKeyField()};
			} elseif($this->_Subject instanceof Relation) {
				return $this->_Subject->getFilters();
			} else {
				return null;
			}
		}
	}