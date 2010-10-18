<?php
	namespace Corelativ;
	use \Corelativ\Model,
		\Frawst\Library\Collection;
	
	/**
	 * Model collection class
	 *
	 * Model::find() will return results for 'all' operations as an instance
	 * of ModelSet instead of an array. This object can still be iterated like an
	 * array, but also provides some additional information not available using an
	 * array.
	 */
	class ModelSet extends Collection {
		public $page;
		public $totalRecords;
		public $totalPages;
		protected $_modelType;
		
		public function __construct($type, $items = array()) {
			$this->_modelType = $type;
			parent::__construct('\\Corelativ\\Model\\'.$type, $items);
		}
		
		public function indexByPrimaryKey() {
			$class = $this->type();
			parent::indexBy($class::primaryKeyField());
		}
		
		public function modelType() {
			return $this->_modelType;
		}
	}