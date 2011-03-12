<?php
	namespace Corelativ\Factory;
	use \Corelativ\Factory,
	    \Corelativ\Model;
	
	class OneToMany extends \Corelativ\Factory\Relation {
		
		public function __construct($config, $subject) {
			parent::__construct($config, $subject);
			$this->foreignKeyField(isset($config['key'])
				? $config['key']
				: lcfirst($this->subject()->tableName()).ucfirst($this->primaryKeyField())
			);
		}
		
		public function getFrom() {
			if($this->subject() instanceof Relation) {
				return $this->subject()->getFrom().' LEFT JOIN '.$this->from().' ON '.
					$this->subject()->tableAlias().'.'.$this->subject()->primaryKeyField().' = '.$this->tableAlias().'.'.$this->foreignKeyField();
			} elseif($this->subject() instanceof Factory || $this->subject() instanceof Model) {
				return $this->from();
			} else {
				//@todo exception
				exit('bad subject');
			}
		}
		
		public function getFilters() {
			if($this->subject() instanceof Model) {
				return $this->tableAlias().'.'.$this->foreignKeyField().' = '.$this->subject()->primaryKey();
			} elseif($this->subject() instanceof Relation) {
				return $this->subject()->getFilters();
			} else {
				return null;
			}
		}
	}