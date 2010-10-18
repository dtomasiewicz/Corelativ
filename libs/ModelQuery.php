<?php
	namespace Corelativ;
	use \DataPane;
	
	class ModelQuery extends DataPane\Query {
		protected $_page;
		
		public function __construct($type, $tables, $params = array()) {
			parent::__construct($type, $tables, $params);
			
			$this->page = isset($params['page']) ? $params['page'] : null;
		}
		
		public function paginated() {
			return (bool) (!is_null($this->_page) && !is_null($this->limit));
		}
		
		/**
		 * If a page number is defined, increase the offset by the appropriate amount.
		 * Default offset is 0.
		 * @param string $name Name of the property
		 * @return string If name is offset, will return the adjusted offset. Otherwise, see parent.
		 */
		public function __get($name) {
			if ($name == 'offset' && $this->paginated()) {
				return $this->_offset + ($this->_page-1)*$this->limit;
			} elseif ($name == 'page' && $this->paginated()) {
				return $this->_page;
			} else {
				return parent::__get($name);
			}
		}
	}