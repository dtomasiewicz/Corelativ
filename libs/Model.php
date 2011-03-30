<?php
	namespace Corelativ;
	use \Corelativ\Factory,
		\Frawst\Validator,
		\Frawst\JSONEncodable,
		\Frawst\Exception,
		\DataPane\Data,
		\DataPane\Query;
	
	abstract class Model implements \Serializable, JSONEncodable {
		const INDEX_PRIMARY = 'PRIMARY';
		const INDEX_UNIQUE = 'UNIQUE';
		const INDEX_INDEX = 'INDEX';
		const INDEX_FULLTEXT = 'FULLTEXT';
		
		const FIELD_INT = 'INT';
		const FIELD_VARCHAR = 'VARCHAR';
		const FIELD_TEXT = 'TEXT';
		const FIELD_BOOL = 'BOOL';
		const FIELD_ENUM = 'ENUM';
		
		const REL_ONE_TO_MANY = 'OneToMany';
		const REL_MANY_TO_ONE = 'ManyToOne';
		
		private static $nextUniqueId = 1;
		private $uniqueId;
		
		/**
		 * Stores the saved properties of this model. Set in constructor.
		 * @var array
		 */
		private $stored;
		
		/**
		 * Stores the unsaved properties of this model. Set in constructor.
		 * @var array
		 */
		private $changes;
		
		/**
		 * Stores offsets for fields that are incremented/decremented as opposed
		 * to being explicitely set.
		 * @var array
		 */
		private $offsets;
		
		/**
		 * Whether or not this model has been saved to the data source.
		 * @var boolean
		 */
		private $saved;
		
		/**
		 * Relation models used for getting/setting associated models.
		 * @var array
		 */
		private $relations = array();
		
		/**
		 * An associative array of validation errors from the most recent validate()
		 * call on this model. Set in constructor.
		 * @var array
		 */
		private $errors = array();
		
		/**
		 * When this model is acting as a proxy for a relationship or factory, this stores
		 * the factory objec that defines how this relationship behaves. Set in constructor.
		 * @var Factory
		 */
		private $Factory;
		
		/**
		 * Constructor. This is the only place a primary key can be set from outside
		 * of the model.
		 */
		public function __construct($properties = array()) {
			if ($properties instanceof Factory) {
				// empty model for use by a factory
				$this->Factory = $properties;
			} else {
				$this->uniqueId = self::$nextUniqueId++;
				$this->stored = array();
				$this->changes = array();
				$this->offsets = array();
				
				$this->saved = isset($properties[$this->primaryKeyField()]);
				
				foreach (static::properties() as $prop => $cfg) {
					if ($this->saved) {
						$this->stored[$prop] = isset($properties[$prop])
							? $properties[$prop]
							: $this->defaultValue($prop);
					} else {
						$this->changes[$prop] = isset($properties[$prop])
							? $properties[$prop]
							: $this->defaultValue($prop);
						$this->stored[$prop] = null;
					}
				}
			}
		}
		
		/**
		 * Reloads from the data source.
		 */
		public function reload() {
			//@todo this
		}
		
		public function __get($property) {
			if($this->propertyExists($property)) {
				return $this->get($property);
			} elseif($rel = $this->relate($property)) {
				return $rel;
			} elseif($property == 'Factory') {
				return $this->Factory;
			} else {
				//@todo exception
				exit('invalid model property: '.$property);
			}
		}
		
		public function __set($property, $value) {
			$this->set($property, $value);
		}
		
		public function serialize() {
			return serialize($this->get());
		}
		
		public function unserialize($properties) {
			$this->construct(unserialize($properties));
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
		public function get($property = null, $storedValue = false) {
			if (is_null($property)) {
				$vals = array();
				foreach($this->properties() as $prop => $cfg) {
					$vals[$prop] = $this->get($prop);
				}
				return $vals;
			} elseif ($this->propertyExists($property)) {
				if($storedValue && $this->saved()) {
					return $this->stored[$property];
				} else {
					$val = isset($this->changes[$property])
						? $this->changes[$property]
						: $this->stored[$property];
					if(isset($this->offsets[$property])) {
						$val += $this->offsets[$property];
					}
					return $val;
				}
			} elseif ($rel = $this->relate($property)) {
				return $rel;
			} else {
				throw new Exception\Model('Unrecognized property/relation: '.$property);
			}
		}
		
		/**
		 * Sets the value of a model property.
		 * 
		 * @signature[1] (string $name, mixed $value)
		 * @param string $name The name of the property to set
		 * @param mixed $value The value to set the property to
		 * 
		 * @signature[2] (array $values)
		 * @param array $values An array of property => value pairs to be set
		 */
		public function set($property, $value = '') {
			if (is_array($property)) {
				// signature[2]
				foreach ($property as $p => $v) {
					$this->set($p, $v);
				}
			} else {
				if ($this->propertyExists($property)) {
					if ($property != $this->primaryKeyField()) {
						$this->changes[$property] = $value;
						if(isset($this->offsets[$property])) {
							unset($this->offsets[$property]);
						}
					}
				}
			}
		}
		
		public function offset($property, $offset) {
			if($this->propertyExists($property)) {
				$this->offsets[$property] = isset($this->offsets[$property])
					? $this->offsets[$property] + $offset
					: $offset;
				
				return $this->get($property);
			}
		}
		
		/**
		 * Increments a given field.
		 * @param  string $property The name of the property to be incremented
		 * @param  bool   $post Whether to evaluate the return as post-increment or pre-increment.
		 * @return int    The value before being incremented if $post is true, else the value
		 *                after being incremented.
		 */
		public function increment($property, $post = true) {
			return $post ? $this->offset($property, 1)-1 : $this->offset($property, 1);
		}
		
		/**
		 * Decrements a given field.
		 * @param  string $property The name of the property to be decremented
		 * @param  bool   $post Whether to evaluate the return as post-decrement or pre-decrement.
		 * @return int    The value before being decremented if $post is true, else the value
		 *                after being decremented.
		 */
		public function decrement($property, $post = true) {
			return $post ? $this->offset($property, -1)+1 : $this->offset($property, -1);
		}
		
		/**
		 * Revert changes to a model.
		 * 
		 * @interface model
		 * 
		 * @signature[1] ([string $property = null])
		 * @param string $property The property to be reverted. If null, all
		 *                         properties and will be reverted.
		 * 
		 * @signature[2] (array $properties)
		 * @param array $properties An array of properties to be reverted
		 */
		public function revert($property = null) {
			if (is_array($property)) {
				// signature[2]
				foreach ($property as $p) {
					$this->revert($p);
				}
			} elseif (is_null($property)) {
				$this->revert(array_keys($this->properties()));
			} else {
				if ($this->propertyExists($property)) {
					if($this->saved() && isset($this->changes[$property])) {
						unset($this->changes[$property]);
					} elseif(!$this->saved()) {
						$this->changes[$property] = $this->defaultValue($property);
					}
				}
			}
		}
		
		/**
		 * Saves models.
		 */
		public function save($validate = true) {
			if($validate && !$this->validate()) {
				return false;
			}
			
			$set = array();
			$params = array();
			foreach($this->changes as $prop => $val) {
				$set[] = $prop.'=:'.$prop;
				$params[$prop] = $val;
			}
			foreach($this->offsets as $prop => $offset) {
				if($offset > 0) {
					$set[] = $prop.'='.$prop.'+'.$offset;
				} elseif($offset < 0) {
					$set[] = $prop.'='.$prop.$offset;
				}
			}
			
			$success = true;
			if ($this->saved) {
				$query = new ModelQuery(Query::UPDATE, get_class($this));
				$query->table($this->tableName())
					->set($set)
					->where($this->primaryKeyField().'='.$this->primaryKey())
					->limit(1);
			} else {
				$query = new Query(Query::INSERT, get_class($this));
				$query->table($this->tableName())
					->set($set);
			}
			
			if ($success = $query->execute($params)) {
				$this->stored = $this->get();
				$this->changes = array();
				$this->offsets = array();
						
				if ($query->type == Query::INSERT) {
					$this->stored[$this->primaryKeyField()] = Data::connection($this->connectionName())->lastInsertId();
				}
				
				$this->saved = true;
			}
			
			return $success;
		}
		
		/**
		 * Returns true if validation passes
		 */
		public function validate() {
			
			$validate = Validator::checkObject($this, static::validation());
			if (is_array($validate)) {
				$this->errors = $validate;
			} else {
				$this->errors = array();
			}
			
			return (bool) !(count($this->errors));
		}
				
		/**
		 * Deletes this model.
		 */
		public function delete() {
			$q = new ModelQuery(Query::DELETE, get_class($this));
			$q->from($this->tableName())->where('id = ?');
			
			if($result = $q->execute($this->id)) {
				$pkField = $this->primaryKeyField();
				$this->stored[$pkField] = $this->defaultValue($pkField);
				$this->saved = false;
			}
			
			return $result;
		}
		
		/**
		 * Methods to return validation errors from the previous validation attempt.
		 */
		public function errors($field = null) {
			if (is_null($field)) {
				return $this->errors;
			} else {
				return isset($this->errors[$field])
					? $this->errors[$field]
					: array();
			}
		}
		
		public function setErrors($field, $errors = array()) {
			$this->errors[$field] = $errors;
		}
		
		public function addError($field, $error) {
			if (isset($this->errors[$field])) {
				$this->errors[$field][] = $error;
			} else {
				$this->errors[$field] = array($error);
			}
		}
		
		public function valid($fields = null) {
			if($fields === null) {
				return count($this->errors) === 0;
			}
			
			if (!is_array($fields)) {
				$fields = array($fields);
			}
			
			foreach ($fields as $field) {
				if (isset($this->errors[$field]) && count($this->errors[$field]) > 0) {
					return false;
				}
			}
			
			return true;
		}
		
		public static function propertyExists($property) {
			return array_key_exists($property, static::properties());
		}
		
		public function relate($alias, $as = null) {
			$rel = static::relations();
			if ($as === null && isset($this->relations[$alias])) {
				return $this->relations[$alias];
			} elseif ( isset($rel[$alias])) {
				// create the relation object
				$related = $rel[$alias];
				if (is_string($related)) {
					$related = array('type' => $related);
				}
				if($as !== null) {
					$related['tableAlias'] = $as;
				}
				$related['alias'] = $alias;
				if (!isset($related['model'])) {
					$related['model'] = $alias;
				}
				$class = '\Corelativ\Factory\\'.$related['type'];
				$relation = new $class($related, $this);
				
				// if not using a table alias, store this for future use
				if($as === null) {
					$this->relations[$alias] = $relation;
				}
				
				return $relation;
			} else {
				return false;
			}
		}
		
		public static function factory($model) {
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
					return $this->stored[$field];
				} else {
					throw new Exception\Model('Attempting to access saved value of invalid property: '.$field);
				}
			}
			
			return $this->saved;
		}
		
		public function uniqueId() {
			return $this->uniqueId;
		}
		
		public function primaryKey() {
			return $this->stored[static::primaryKeyField()];
		}
		
		public static function connectionName() {
			return 'default';
		}
		
		public static function modelName() {
			$c = explode('\\', get_called_class());
			return array_pop($c);
		}
		
		public static function tableName() {
			return static::modelName();
		}
		
		public static function properties() {
			return array(
				'id' => array('type' => self::FIELD_INT)
			);
		}
		
		public static function validation() {
			return array();
		}
		
		public static function relations() {
			return array();
		}
		
		public static function primaryKeyField() {
			return 'id';
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