<?php
	namespace Corelativ;
	use \Corelativ\Factory,
		\Frawst\Library\Validator,
		\Frawst\Library\JSONEncodable,
		\Frawst\Exception,
		\DataPane,
		\DataPane\Data;
	
	abstract class Model implements \Serializable, JSONEncodable {
		const INDEX_PRIMARY = 'PRIMARY';
		const INDEX_INDEX = 'INDEX';
		const INDEX_FULLTEXT = 'FULLTEXT';
		
		const FIELD_INT = 'INT';
		const FIELD_VARCHAR = 'VARCHAR';
		const FIELD_TEXT = 'TEXT';
		const FIELD_BOOL = 'BOOL';
		
		protected static $_nextUniqueId = 1;
		protected $_uniqueId;
		
		/**
		 * The datasource where this model is stored.
		 */
		protected $_dataSource = 'default';
		protected static $_dataSources = array();
		
		/**
		 * The name of the database table associated with this model. May be overridden
		 * in subclasses. Set in constructor.
		 * @var string
		 */
		protected $_tableName;
		protected static $_tableNames = array();
		
		/**
		 * The name of the model (the base part of the class name). Set
		 * in constructor.
		 * @var string
		 */
		protected $_modelName;
		protected static $_modelNames = array();
		
		/**
		 * The field to be used as the primary key. May be overridden in subclasses.
		 * @var string
		 */
		protected $_primaryKeyField;
		protected static $_primaryKeyFields = array();
		
		/**
		 * Array of properties and configurations
		 * @var array
		 */
		protected $_properties;
		
		/**
		 * An associative array describing the relations between this model and other models.
		 * @var array
		 */
		protected $_related = array();
		
		/**
		 * Associative array of validation instructions for this model.
		 * @var array
		 */
		protected $_validate = array();
		
		/**
		 * Stores the saved properties of this model. Set in constructor.
		 * @var array
		 */
		protected $_stored;
		
		/**
		 * Stores the unsaved properties of this model. Set in constructor.
		 * @var array
		 */
		protected $_changes;
		
		/**
		 * Whether or not this model has been saved to the datasource since the
		 * most recent change. Set in constructor.
		 * @var boolean
		 */
		protected $_saved;
		
		/**
		 * Relation models used for getting/setting associated models.
		 * @var array
		 */
		protected $_relations = array();
		
		/**
		 * An associative array of validation errors from the most recent validate()
		 * call on this model. Set in constructor.
		 * @var array
		 */
		protected $_errors = array();
		
		/**
		 * When this model is acting as a proxy for a relationship or factory, this stores
		 * the factory objec that defines how this relationship behaves. Set in constructor.
		 * @var Factory
		 */
		protected $_Factory;
		
		/**
		 * Constructor. This is the only place a primary key can be set from outside
		 * of the model.
		 */
		public function __construct($properties = array()) {
			$this->_modelName = static::modelName();
			$this->_dataSource = static::dataSource();
			$this->_tableName = static::tableName();
			$this->_primaryKeyField = static::primaryKeyField();
			
			if ($properties instanceof Factory) {
				// empty model for use by a factory
				$this->_Factory = $properties;
			} else {
				$this->_uniqueId = self::$_nextUniqueId++;
				$this->_stored = array();
				$this->_changes = array();
				
				$this->_saved = isset($properties[$this->_primaryKeyField]);
				
				foreach ($this->_properties as $prop => $cfg) {
					if ($this->_saved) {
						$this->_stored[$prop] = isset($properties[$prop])
							? $properties[$prop]
							: $this->defaultValue($prop);
					} else {
						$this->_changes[$prop] = $this->defaultValue($prop);
					}
				}
				
				if(!$this->_saved) {
					$this->create($properties);
				}
			}
		}
		
		public function create($data = array()) {
			$this->set($data);
		}
		
		public function __get($property) {
			switch($property) {
				case 'Factory':
					return $this->_Factory;
				default:
					return $this->get($property);
			}
		}
		
		public function __set($property, $value) {
			$this->set($property, $value);
		}
		
		public function serialize() {
			return serialize($this->get());
		}
		
		public function unserialize($properties) {
			$this->__construct(unserialize($properties));
		}
		
		public function toJSON() {
			return $this->get();
		}
		
		/**
		 * Getter override. Will first check to see if the requested member name is a
		 * model property. If it is, it'll return the (unsaved) value of that property.
		 * Otherwise will attempt to return a relationship object.
		 * 
		 * @param string $property The name of the property/relation
		 * @return mixed The value of the property, or a relation object, if exists.
		 */
		public function get($property = null) {
			if (is_null($property)) {
				return $this->_changes + $this->_stored;
			} elseif ($this->propertyExists($property)) {
				return isset($this->_changes[$property]) ? $this->_changes[$property] : $this->_stored[$property];
			} elseif ($rel = $this->relate($property)) {
				if ($rel instanceof Factory\Singular) {
					return $rel->find();
				} else {
					return $rel;
				}
			} else {
				throw new Exception\Model('Unrecognized property: '.$property);
			}
		}
		
		/**
		 * Sets the value of a model property or relation. You can flag autoRelate
		 * to false to avoid setting any relations, if your data is compromisable
		 * (for example, if you are simply set()ing form data, users may be able to
		 * cross-site-script to change the relations).
		 * 
		 * @return true if everything set correctly, false if any property failed to
		 *         set. since primary keys are ignored (they should be treated as
		 *         immutable) a primary key may fail to set but still return true.
		 * 
		 * @signature[1] (string $name, mixed $value[, boolean $autoRelate = true])
		 * @param string $name The name of the property or relation to set
		 * @param mixed $value The value to set the property or relation to
		 * @param boolean $autoRelate Whether or not to allow relation setting
		 * 
		 * @signature[2] (array $values[, boolean $autoRelate = true])
		 * @param array $values An array of property => value pairs to be set
		 * @param boolean $autoRelate Whether or not to allow relation setting
		 */
		public function set($property, $value = '', $autoRelate = true) {
			$success = true;
			if (is_array($property)) {
				// signature[2]
				$autoRelate = $value;
				if ($autoRelate !== false) {
					$autoRelate = true;
				}
				
				foreach ($property as $p => $v) {
					$this->set($p, $v, $autoRelate);
				}
			} else {
				if ($this->propertyExists($property)) {
					if ($property != $this->primaryKeyField()) {
						$this->_changes[$property] = $value;
					} elseif ($value != $this->primaryKey()) {
						throw new Exception\Model('Trying to set a primary key different from the saved primary key.');
					}
				} elseif ($autoRelate && ($rel = $this->relate($property))) {
					$rel->set($value);
				} else {
					throw new Exception\Model('trying to set invalid model property or relation: '.$property);
				}
			}
		}
		
		/**
		 * Revert changes to a model and its relations (or to specified properties)
		 * 
		 * @interface model
		 * 
		 * @signature[1] ([string $property = null])
		 * @param string $property The property/relation to be reverted. If null, all
		 *                         properties and relations will be reverted.
		 * 
		 * @signature[2] (array $properties)
		 * @param array $properties An array of properties/relations to be reverted
		 */
		public function revert($property = null) {
			if (is_array($property)) {
				// signature[2]
				foreach ($property as $p) {
					$this->revert($p);
				}
			} elseif (is_null($property)) {
				$this->_changes = array();
				$this->_relations = array();
			} else {
				if ($this->propertyExists($property) && isset($this->_changes[$property])) {
					unset($this->_changes[$property]);
				} elseif (isset($this->_relations[$property])) {
					unset($this->_relations[$property]);
				}
			}
		}
		
		/**
		 * Saves models.
		 */
		public function save($relationships = true) {
			if (!$this->beforeValidate()) {
				return false;
			}
			
			if ($this->validate($relationships) !== true) {
				return false;
			}
			
			if (!$this->beforeSave()) {
				return false;
			}
			
			$success = true;
			// only go through with the save if the model is unsaved or has changes
			if (count($this->_changes) || !$this->_saved) {
				if ($this->_saved) {
					$q = new DataPane\Query('update', $this->tableName(), array(
						'values' => $this->_changes,
						'where' => new DataPane\ConditionSet(array($this->primaryKeyField() => $this->primaryKey())),
						'limit' => 1
					));
				} else {
					$q = new DataPane\Query('insert', $this->tableName(), array('values' => $this->_changes));
				}
				
				if ($success = Data::source($this->_dataSource)->query($q)) {
					$this->_stored = $this->_changes + $this->_stored;
					$this->_changes = array();
							
					if ($q->type == 'insert') {
						$this->_stored[$this->primaryKeyField()] = Data::source($this->_dataSource)->insertId();
					}
					
					$this->_saved = true;
				}
			}
			
			// save model relations
			if ($success) {
				if ($relationships) {
					$this->__saveRelationships();
				}
				$this->afterSave();
				return $success;
			} else {
				throw new Exception\Model('Could not save model: '.Data::source($this->_dataSource)->error());
			}
		}
		
		/**
		 * Saves relationships.
		 */
		private function __saveRelationships() {
			foreach ($this->_relations as $relation) {
				$relation->save();
			}
		}
		
		/**
		 * Returns true if validation passes
		 */
		public function validate($relationships = true) {
			$validate = Validator::checkObject($this, $this->_validate);
			if (is_array($validate)) {
				$this->_errors = $validate;
			} else {
				$this->_errors = array();
			}
			
			if ($relationships) {
				// validate relations too
				foreach ($this->_relations as $alias => $relation) {
					$rValidate = $relation->validate();
					if (is_array($rValidate)) {
						$this->_errors[$alias] = $rValidate;
					}
				}
			}
			
			return (bool) !(count($this->_errors));
		}
				
		/**
		 * Deletes this model.
		 */
		public function delete() {
			$q = new DataPane\Query('delete', $this->tableName(), array(
				'limit' => 1,
				'where' => new DataPane\ConditionSet(array($this->primaryKeyField() => $this->primaryKey()))
			));
			
			if ($result = Data::source($this->_dataSource)->query($q)) {
				$pkField = $this->primaryKeyField();
				$this->_stored[$pkField] = $this->defaultValue($pkField);
				$this->_saved = false;
				return $result;
			} else {
				throw new Exception\Model('Could not delete model: '.Data::source($this->_dataSource)->error());
			}
		}
		
		/**
		 * Methods to return validation errors from the previous validation attempt.
		 */
		public function errors($field = null) {
			if (is_null($field)) {
				return $this->_errors;
			} else {
				return isset($this->_errors[$field])
					? $this->_errors[$field]
					: array();
			}
		}
		
		public function setErrors($field, $errors = array()) {
			$this->_errors[$field] = $errors;
		}
		
		public function addError($field, $error) {
			if ($old = $this->_errors[$field]) {
				$old[] = $error;
				$this->_errors[$field] = $old;
			} else {
				$this->_errors[$field] = array($error);
			}
		}
		
		public function valid($fields = null) {
			if (!is_array($fields)) {
				$fields = array($fields);
			}
			foreach ($fields as $field) {
				if (isset($this->_errors[$field]) && count($this->_errors[$field]) > 0) {
					return false;
				}
			}
			return true;
		}
		
		public static function propertyExists($property) {
			return array_key_exists($property, static::properties());
		}
		
		public function relate($alias) {
			if (isset($this->_relations[$alias])) {
				return $this->_relations[$alias];
			} elseif (isset($this->_related[$alias])) {
				// create the relation object
				$related = $this->_related[$alias];
				if (is_string($related)) {
					$related = array('type' => $related);
				}
				$related['objectAlias'] = $alias;
				if (!isset($related['model'])) {
					$related['model'] = $alias;
				}
				$related['subject'] = $this;
				
				$class = '\\Corelativ\\Factory\\'.$related['type'];
				return $this->_relations[$alias] = new $class($related);
			} else {
				return false;
			}
		}
		
		public function factory($model) {
			return Mapper::factory($model);
		}
		
		/**
		 * Hooks
		 */
		public function beforeFind($params) {
			return $params;
		}
		
		public function beforeSave() {
			return true;
		}
		
		public function beforeValidate() {
			return true;
		}
		
		public function afterSave() {
			
		}
		
		/**
		 * Determines whether or not the modle has been saved. If a field is specified,
		 * will instead return the saved value of that field.
		 */
		public function saved($field = null) {
			if (!is_null($field)) {
				if ($this->propertyExists($field)) {
					return $this->_stored[$field];
				} else {
					throw new Exception\Model('Attempting to access saved value of invalid property: '.$field);
				}
			}
			
			return $this->_saved;
		}
		
		public function uniqueId() {
			return $this->_uniqueId;
		}
		
		public function primaryKey() {
			return $this->_stored[$this->_primaryKeyField];
		}
		
		public static function dataSource() {
			$c = get_called_class();
			if (!isset(self::$_dataSources[$c])) {
				$v = get_class_vars($c);
				self::$_dataSources[$c] = $v['_dataSource'];
			}
			return self::$_dataSources[$c];
		}
		
		public static function modelName() {
			$c = get_called_class();
			if (!isset(self::$_modelNames[$c])) {
				$cParts = explode('\\', $c);
				self::$_modelNames[$c] = end($cParts);
			}
			return self::$_modelNames[$c];
		}
		
		public static function tableName() {
			$c = get_called_class();
			if (!isset(self::$_tableNames[$c])) {
				$v = get_class_vars($c);
				self::$_tableNames[$c] = isset($v['_tableName'])
					? $v['_tableName']
					: static::modelName();
			}
			return self::$_tableNames[$c];
		}
		
		public static function properties() {
			$v = get_class_vars(get_called_class());
			return $v['_properties'];
		}
		
		public static function primaryKeyField() {
			$c = get_called_class();
			if (!isset(self::$_primaryKeyFields[$c])) {
				foreach (static::properties() as $prop => $config) {
					if (isset($config['index']) && $config['index'] == Model::INDEX_PRIMARY) {
						return self::$_primaryKeyFields[$c] = $prop;
					}
				}
			} else {
				return self::$_primaryKeyFields[$c];
			}
			
			throw new Exception\Model('No primary key defined for model: '.$c);
		}
		
		public static function defaultValue($prop) {
			$props = static::properties();
			if(isset($props[$prop]['default'])) {
				return $props[$prop]['default'];
			} else {
				switch($props[$prop]['type']) {
					case self::FIELD_INT:
						return 0;
					case self::FIELD_BOOL:
						return 0;
					default:
						return '';
				}
			}
		}
	}