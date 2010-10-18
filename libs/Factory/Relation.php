<?php
	namespace Corelativ\Factory;
	use Corelativ\Factory;
	
	/**
	 * implementation notes:
	 *  through needs to be specified outside of constructor
	 *  type needs to be determined outside of constructor
	 */
	abstract class Relation extends Factory {
		protected $_subjectAlias;
		protected $_subjectKeyField;
		protected $_objectAlias;
		protected $_objectKeyField;
		protected $_Subject;
		
		public function __construct($config) {
			parent::__construct($config);
			$this->_Subject = $config['subject'];
			
			$this->_subjectAlias = isset($config['subjectAlias'])
				? $config['subjectAlias']
				: $this->_Subject->modelName();
			
			$this->_objectAlias = isset($config['objectAlias'])
				? $config['objectAlias']
				: $this->_Object->modelName();
				
			$this->_subjectKeyField = isset($config['subjectKeyField'])
				? $config['subjectKeyField']
				: lcfirst($this->_subjectAlias).ucfirst($this->_Subject->primaryKeyField());
			
			$this->_objectKeyField = isset($config['objectKeyField'])
				? $config['objectKeyField']
				: lcfirst($this->_objectAlias).ucfirst($this->_Object->primaryKeyField());
		}
		
		public function findAll($params = array()) {
			$params = $this->_normalizeParams($params);
			$condition = $this->_uniqueCondition();
			$condition->add($params->where);
			$params->where = $condition;
			return parent::findAll($params);
		}
		
		abstract protected function _uniqueCondition();
		abstract public function set($related);
		abstract public function save();
	}