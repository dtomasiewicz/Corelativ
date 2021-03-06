<?php
	namespace Corelativ;
	use \Corelativ\Model,
		\Frawst\Collection;
	
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
		
		public function mapByPrimaryKey() {
			$class = $this->type();
			return parent::mapBy($class::primaryKeyField());
		}
	}