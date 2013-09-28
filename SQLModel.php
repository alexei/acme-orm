<?php

include 'sql_model_fields.inc.php';
include 'SQLModelMeta.php';

/**
 * There are 3 types of models:
 * 1. Concrete models
 * Concrete models have a table of their own.
 *
 * 2. Abstract models
 * Abstract models serve as generic models. They do not have a table of their own. Instead, they
 * define and implement common fields and methods that can be shared by other, concrete, models.
 *
 * 3. Proxy models
 * Proxy models serve as aliases. They inherit from other, concrete, models, but they do not have
 * tables of their own. They, instead, share the same database table under different names.
 */
abstract class SQLModel extends Mutator
{
	const FORCE_INSERT = 1;
	const FORCE_UPDATE = 2;

	const AUTO_ON_CREATE = 1;
	const AUTO_ON_UPDATE = 2;


	static $meta = NULL;

	static $table_name = '';
	static $fields = array();
	static $verbose_name = '';
	static $is_proxy = FALSE;


	public $is_bound = FALSE;
	public $__class__ = '';


	protected $CI;

	/**
	 * @xxx maybe add some magic powder?
	 */
	static $cache = array();


	public function __construct($data = NULL)
	{
		$this->CI =& get_instance();
		$this->__class__ = get_class($this);

		$this->emit_field_signal('pre_init');

		$row = NULL;
		if (is_int($data) || is_string($data)) {
			$query = $this->CI->db->from(SQLModel::$meta[$this->__class__]['table_name'])->where(SQLModel::$meta[$this->__class__]['primary_key'], $data)->limit(1)->get();
			if ($query->num_rows()) {
				$row = $query->row();
				$this->is_bound = TRUE;
			}
			else {
				throw new Exception('Object does not exist');
			}
		}
		else if (is_array($data)) {
			$row = (object)$data;
		}
		else if (is_object($data)) {
			$row = $data;
		}

		if ($row) {
			foreach (array_keys(SQLModel::$meta[$this->__class__]['fields']) as $field_name) {
				if (property_exists($row, $field_name)) {
					$this->{$field_name} = $row->{$field_name};
				}
			}

			// @xxx isset won't work
			if ($this->pk) {
				if (!isset(SQLModel::$cache[$this->__class__])) {
					SQLModel::$cache[$this->__class__] = array();
				}
				SQLModel::$cache[$this->__class__][$this->pk] = $this;
			}
		}

		$this->emit_field_signal('post_init');

		if (count(SQLModel::$meta[$this->__class__]['relations'])) {
			foreach (SQLModel::$meta[$this->__class__]['relations'] as $key => $relation) {
				if ($relation['type'] == 'one_to_many') {
					if ($relation['implicit'] == TRUE) { // implicit - i.e. auto
						$this->__defineGetter__($key, array($this, 'relation_one_to_many_get_many'));
						$this->__defineSetter__($key, array($this, 'relation_one_to_many_set_many'));
						$this->__defineIsSetter__($key, array($this, 'relation_one_to_many_isset_many'));
					}
					else { // explicit
						$this->__defineGetter__($key, array($this, 'relation_one_to_many_get_one'));
						$this->__defineSetter__($key, array($this, 'relation_one_to_many_set_one'));
						$this->__defineIsSetter__($key, array($this, 'relation_one_to_many_isset_one'));
					}
				}
			}
			//exit;
		}
	}

	public function save($save_flag = 0)
	{
		$this->emit_field_signal('pre_save');

		foreach (array_keys(SQLModel::$meta[$this->__class__]['fields']) as $field_name) {
			$save_data[$field_name] = $this->{$field_name};
		}

		if ($this->is_bound === FALSE || $save_flag === SQLModel::FORCE_INSERT) { // insert
			if (SQLModel::$meta[$this->__class__]['fields'][SQLModel::$meta[$this->__class__]['primary_key']]['type'] == 'serial') {
				// @xxx what about other types of PKs?
				if ($save_flag !== SQLModel::FORCE_INSERT) {
					unset($save_data[SQLModel::$meta[$this->__class__]['primary_key']]);
				}
			}
			$this->CI->db->insert(SQLModel::$meta[$this->__class__]['table_name'], $save_data);
			$affected_rows = $this->CI->db->affected_rows();
			$this->pk = $this->CI->db->insert_id();
			if ($affected_rows >= 0) {
				$this->is_bound = TRUE;
			}
		}
		else if ($this->is_bound === TRUE || $save_flag === SQLModel::FORCE_UPDATE) { // update
			$this->CI->db->where(SQLModel::$meta[$this->__class__]['primary_key'], $this->pk)->limit(1)->update(SQLModel::$meta[$this->__class__]['table_name'], $save_data);
			$affected_rows = $this->CI->db->affected_rows();
		}
		else {
			throw new Exception("I don't know what to do");
		}

		$this->emit_field_signal('post_save');

		return $affected_rows;
	}

	public function delete()
	{
		$this->emit_field_signal('pre_delete');

		$this->CI->db->limit(1)->delete(SQLModel::$meta[$this->__class__]['table_name'], array(SQLModel::$meta[$this->__class__]['primary_key'] => $this->pk));
		$affected_rows = $this->CI->db->affected_rows();

		$this->emit_field_signal('post_delete');

		return $affected_rows;
	}

	public function __get_pk__()
	{
		return $this->{SQLModel::$meta[$this->__class__]['primary_key']};
	}

	public function __set_pk__($val)
	{
		return $this->{SQLModel::$meta[$this->__class__]['primary_key']} = $val;
	}

	public function __isset_pk__()
	{
		return isset($this->{SQLModel::$meta[$this->__class__]['primary_key']});
	}

	/**
	 * Relations
	 */
	/*
	 * one to many
	 */
	public function relation_one_to_many_get_one($key)
	{
		if (!array_key_exists($key, $this->__attrs_cache__)) {
			$this_meta = SQLModel::$meta[$this->__class__]['relations'][$key];
			$model = $this_meta['model'];
			$relation_field = $this_meta['relation_field'];
			if (isset(SQLModel::$cache[$this_meta['model']]) && isset(SQLModel::$cache[$model][$this->{$relation_field}])) {
				$this->__attrs_cache__[$key] = SQLModel::$cache[$model][$this->{$relation_field}];
			}
			else {
				$this->__attrs_cache__[$key] = new $model($this->{$relation_field});
			}
		}
		return $this->__attrs_cache__[$key];
	}

	public function relation_one_to_many_set_one($key, $val)
	{
		$pk = NULL;
		$this_meta = SQLModel::$meta[$this->__class__]['relations'][$key];
		if ($val instanceof $this_meta['model']) {
			$pk = $val->pk;
		}
		else if (is_int($val)) { // should look for type of pk
			$pk = $val;
		}

		if (isset($pk)) {
			$this->{$this_meta['relation_field']} = $pk;
		}
		else {
			trigger_error('Bad method call', E_USER_ERROR);
		}
	}

	public function relation_one_to_many_isset_one($key)
	{
		return true;
	}

	/**
	 * one to many - reverse
	 */
	public function relation_one_to_many_get_many($key)
	{
		if (!array_key_exists($key, $this->__attrs_cache__)) {
			$this_meta = SQLModel::$meta[$this->__class__]['relations'][$key];
			$that_meta = SQLModel::$meta[$this_meta['model']]['relations'][$this_meta['other_field']];
			$objects = acme_class_get($this_meta['model'], 'objects');
			$this->__attrs_cache__[$key] = $objects->filter(array($that_meta['relation_field'] => $this->pk));
		}
		return $this->__attrs_cache__[$key];
	}

	public function relation_one_to_many_set_many($key, $val)
	{
		//throw new Exception('Modifying an immutable object is futile');
		$this->__attrs_cache__[$key] = $val;
	}

	public function relation_one_to_many_isset_many($key)
	{
		return true;
	}

	/**
	 * misc
	 */
	public function emit_field_signal($signal)
	{
		foreach (SQLModel::$meta[$this->__class__]['fields'] as $field_name => $field_meta) {
			field_emit_signal($signal, $this, $field_name, $field_meta);
		}
	}

	public function __sleep()
	{
		return array_keys(SQLModel::$meta[$this->__class__]['fields']);
	}
}
SQLModel::$meta = new SQLModelMeta();
