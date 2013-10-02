<?php

/**
 * @todo
 * - ArrayAccess
 * - rewrite field look-ups
 * - allow aliases in select values
 */
class SQLModelManager implements ArrayAccess, Countable, Iterator
{
	public $collection = array();
	public $is_executed = FALSE;

	private $CI;
	private $model;

	private $select_expr = array('*');
	private $where_cond = array();
	private $order_by = array();
	private $limit = array('row_count' => 0, 'offset' => 0);

	private $joins = array();

	public function __construct($model)
	{
		$this->CI =& get_instance();
		$this->model = $model;
	}

	public function values()
	{
		$that = clone $this;
		if (func_num_args()) {
			$func_list = array('date', 'day', 'dayofmonth', 'dayofweek', 'dayofyear', 'hour', 'minute', 'month', 'monthname', 'second', 'week', 'weekday', 'weekofyear', 'year');
			$select_expr = array_values(func_get_args());
			$that->select_expr = array();
			foreach ($select_expr as $alias => &$expr_bit) {
				$alias = $expr_bit;
				$lookup_type = explode('__', $expr_bit);
				$field = array_shift($lookup_type);
				$type = current($lookup_type);
				if (in_array($type, $func_list)) {
					$expr_bit = strtoupper($type) .'('. $field .')';
				}
				$that->select_expr[$alias] = $expr_bit;
			}
		}
		return $that;
	}

	private function escape($value)
	{
		if (is_bool($value)) {
			$value = $this->CI->db->escape_str((int)$value);
		}
		else if (is_scalar($value)) {
			$value = $this->CI->db->escape_str($value);
		}
		else if (is_array($value)) {
			$value = array_map(array($this->CI->db, 'escape_str'), $value);
		}
		else if (is_object($value) && $value instanceof SQLModelManager) {
			return $value;
		}
		else if (!is_null($value)) {
			trigger_error('Value must be of scalar, null or array types.', E_USER_ERROR);
		}
		return $value;
	}

	private function prepare_select_expr($select_expr = array())
	{
		if ($select_expr == array() || $select_expr == array('*')) {
			$select_expr = array(SQLModel::$meta[$this->model]['table_name'] .'.*');
			return $select_expr;
		}
		else {
			$__select_expr = array();
			foreach ($select_expr as $alias => $field) {
				if (is_numeric($field)) {
					$field = $alias;
				}
				$__select_expr[] = $field .' AS '. $alias;
			}
		}
		return $__select_expr;
	}

	public function exclude($conditions = array())
	{
		return $this->filter_or_exclude(TRUE, $conditions, 'AND');
	}

	public function filter($conditions = array())
	{
		return $this->filter_or_exclude(FALSE, $conditions, 'AND');
	}

	public function or_exclude($conditions = array())
	{
		return $this->filter_or_exclude(TRUE, $conditions, 'OR');
	}

	public function or_filter($conditions = array())
	{
		return $this->filter_or_exclude(FALSE, $conditions, 'OR');
	}

	protected function filter_or_exclude($negate, $conditions, $operator = 'AND')
	{
		$that = clone $this;
		if (count($conditions)) {
			foreach ($conditions as $key => $val) {
				$conditions[$key] = $that->field_lookup($key, $val);
			}
			if ($negate) { // exclude
				$that->where_cond[] = 'NOT ('. implode(' '. $operator .' ', $conditions) .')';
			}
			else { // filter
				$that->where_cond[] = '('. implode(' '. $operator .' ', $conditions) .')';
			}
		}
		return $that;
	}

	public function order()
	{
		$that = clone $this;

		if (func_num_args()) {
			foreach (func_get_args() as $col) {
				if ($col === '?') {
					$that->order_by[] = 'RAND()';
				}
				else {
					$order = '+';
					if (in_array($col{0}, array('+', '-'))) {
						$order = $col{0};
						$field_lookup = substr($col, 1);
					}
					else {
						$field_lookup = $col;
					}
					$field = $that->field_name($field_lookup);
					$that->order_by[] = $field .' '. ($order == '-' ? 'DESC' : 'ASC');
				}
			}
		}

		return $that;
	}

	protected function field_name($field_lookup)
	{
		$field_parts = explode('.', $field_lookup);
		if (count($field_parts) > 1) {
			$field_lookup = array_pop($field_parts);
			$join_left_model = $this->model;
			foreach ($field_parts as $field_part) {
				$join_right_model = SQLModel::$meta[$join_left_model]['relations'][$field_part]['model'];
				if (SQLModel::$meta[$join_left_model]['relations'][$field_part]['type'] == 'one_to_many') {
					if (SQLModel::$meta[$join_left_model]['relations'][$field_part]['implicit']) {
						$join = new StdClass();
						$join->table = SQLModel::$meta[$join_right_model]['table_name'];
						$join->on = array(
							SQLModel::$meta[$join_left_model]['table_name'] .'.'. SQLModel::$meta[$join_left_model]['primary_key'],
							SQLModel::$meta[$join_right_model]['table_name'] .'.'. SQLModel::$meta[$join_right_model]['relations'][SQLModel::$meta[$join_left_model]['relations'][$field_part]['other_field']]['relation_field']
						);
						$join->type = '';
						if (!in_array($join, $this->joins)) {
							$this->joins[] = $join;
						}
					}
					else {
						$join = new StdClass();
						$join->table = SQLModel::$meta[$join_right_model]['table_name'];
						$join->on = array(
							SQLModel::$meta[$join_left_model]['table_name'] .'.'. SQLModel::$meta[$join_left_model]['relations'][$field_part]['relation_field'],
							SQLModel::$meta[$join_right_model]['table_name'] .'.'. SQLModel::$meta[$join_right_model]['primary_key']
						);
						$join->type = '';
						if (!in_array($join, $this->joins)) {
							$this->joins[] = $join;
						}
					}
				}
				$join_left_model = $join_right_model;
			}
			$field_lookup = SQLModel::$meta[$join_right_model]['table_name'] .'.'. $field_lookup;
		}
		else {
			$field_lookup = SQLModel::$meta[$this->model]['table_name'] .'.'. $field_lookup;
		}

		return $field_lookup;
	}

	protected function field_lookup($field_lookup, $value)
	{
		$field_lookup = $this->field_name($field_lookup);

		/**
		 * There are basically three optional things you can do with the field:
		 * - pass it to a function
		 * - negate the operation
		 * - provide an operator
		 * Thus field[__function[__negate[__operator]]]
		 */
		$field_lookup = str_replace('__is__not', '__not__is', $field_lookup); // common mistake
		$lookup_type = explode('__', $field_lookup);
		$field = array_shift($lookup_type);
		if ($field === 'pk') {
			$field = SQLModel::$meta[$this->model]['primary_key'];
		}

		$function = NULL;
		$negate = FALSE;
		$operator = 'is';

		if (current($lookup_type)) {
			$func_list = array('date', 'day', 'dayofmonth', 'dayofweek', 'dayofyear', 'hour', 'minute', 'month', 'monthname', 'second', 'week', 'weekday', 'weekofyear', 'year');
			$type = current($lookup_type);
			if (in_array($type, $func_list)) {
				$function = $type;
				next($lookup_type);
			}
		}

		if (current($lookup_type)) {
			$negations = array('not', 'aint');
			$type = current($lookup_type);
			if (in_array($type, $negations)) {
				$negate = TRUE;
				next($lookup_type);
			}
		}

		if (current($lookup_type)) {
			$operators = array('gt', 'gte', 'lt', 'lte', 'is', 'like', 'has', 'haslike', 'startswith', 'startslike', 'endswith', 'endslike', 'in', 'between');
			$type = current($lookup_type);
			if (in_array($type, $operators)) {
				$operator = $type;
			}
			else {
				trigger_error('Invalid lookup type.', E_USER_ERROR);
			}
		}

		/**
		 * @xxx (alexei) 09.06.22
		 * - what about placeholders?
		 *
		 * @xxx (alexei) 11.01.22
		 * - should't we support $value of type SQLModelManager for "in"?
		 */
		$value = $this->escape($value);

		$out = array();
		if ($negate) {
			$out[] = 'NOT';
		}
		$out[] = $function ? strtoupper($function) .'('. $field .')' : $field;
		switch ($operator) {
			case 'gt':
				$out[] = "> '". $value ."'";
				break;
			case 'gte':
				$out[] = ">= '". $value ."'";
				break;
			case 'lt':
				$out[] = "< '". $value ."'";
				break;
			case 'lte':
				$out[] = "<= '". $value ."'";
				break;
			case 'is':
				$out[] = is_null($value) ? 'IS NULL' : ("= '". $value ."'");
				break;
			case 'like':
				$out[count($out) - 1] = 'LCASE('. $field .')';
				$out[] = "LIKE LCASE('". $value ."')";
				break;
			case 'has':
				$out[] = "LIKE '%". $value ."%'";
				break;
			case 'haslike':
				$out[count($out) - 1] = 'LCASE('. $field .')';
				$out[] = "LIKE LCASE('%". $value ."%')";
				break;
			case 'startswith':
				$out[] = "LIKE '". $value ."%'";
				break;
			case 'startslike':
				$out[count($out) - 1] = 'LCASE('. $field .')';
				$out[] = "LIKE LCASE('". $value ."%')";
				break;
			case 'endswith':
				$out[] = "LIKE '%". $value ."'";
				break;
			case 'endslike':
				$out[count($out) - 1] = 'LCASE('. $field .')';
				$out[] = "LIKE LCASE('%". $value ."')";
				break;
			case 'in':
				if (is_array($value)) {
					$out[] = "IN ('". implode("', '", $value) ."')";
					break;
				}
				else if (is_object($value) && $value instanceof SQLModelManager) {
					$out[] = "IN (". $value->select_sql() .")";
					break;
				}
			case 'between':
				$out[] = "BETWEEN '". $value[0] ."' AND '". $value[1] ."'";
				break;
		}

		return implode(' ', $out);
	}

	public function limit($row_count = 0, $offset = 0)
	{
		$that = clone $this;
		if ($row_count > 0) {
			$that->limit['row_count'] = $row_count;
			$that->limit['offset'] = $offset;
		}
		return $that;
	}

	public function get($conditions = array())
	{
		$objects = $this->filter($conditions)->limit(1);
		if (count($objects)) {
			return $objects[0];
		}
		else {
			return NULL;
		}
	}

	public function select_sql()
	{
		$sql = array();
		$sql[] = 'SELECT';
		$sql[] = implode(', ', $this->prepare_select_expr($this->select_expr));
		$sql[] = 'FROM';
		$sql[] = SQLModel::$meta[$this->model]['table_name'];
		if (count($this->joins)) {
			foreach ($this->joins as $join) {
				$sql[] = 'JOIN';
				$sql[] = $join->table;
				$sql[] = 'ON';
				$sql[] = $join->on[0];
				$sql[] = '=';
				$sql[] = $join->on[1];
			}
		}
		if (count($this->where_cond)) {
			$sql[] = 'WHERE '. implode(' AND ', $this->where_cond);
		}
		if (count($this->order_by)) {
			$sql[] = 'ORDER BY '. implode(', ', $this->order_by);
		}
		if ($this->limit['row_count'] > 0) {
			$sql[] = 'LIMIT '. $this->limit['offset'] .', '. $this->limit['row_count'];
		}

		return implode(' ', $sql);
	}

	public function select()
	{
		$list = array();
		$query = $this->CI->db->query($this->select_sql());
		if ($query) {
			$this->is_executed = TRUE;
			if ($query->num_rows()) {
				foreach ($query->result() as $row) {
					if ($this->select_expr == array('*')) {
						$object = new $this->model($row);
						$object->is_bound = TRUE;
					}
					else {
						$object = $row;
					}
					$this->collection[] = $object;
				}
			}
		}
		return $this;
	}

	public function create_sql($collection)
	{
		$model_field_set = array_keys(SQLModel::$meta[$this->model]['fields']);
		if (SQLModel::$meta[$this->model]['fields'][SQLModel::$meta[$this->model]['primary_key']]['type'] == 'serial') {
			unset($model_field_set[array_search(SQLModel::$meta[$this->model]['primary_key'], $model_field_set)]);
		}
		$sql = array();
		$sql[] = 'INSERT';
		$sql[] = 'INTO';
		$sql[] = SQLModel::$meta[$this->model]['table_name'];
		$sql[] = "(`". implode("`, `", $model_field_set) ."`)";
		$sql[] = 'VALUES';
		$values_sql = array();
		foreach ($collection as $entry) {
			$entry->emit_field_signal('pre_save');
			foreach ($model_field_set as $model_field) {
				$save_data[$model_field] = $entry->{$model_field};
			}
			$entry->emit_field_signal('post_save');
			$values_sql[] = "('". implode("', '", array_map(array($this, 'escape'), array_values($save_data))) ."')";
		}
		$sql[] = implode(', ', $values_sql);
		return implode(' ', $sql);
	}

	public function create($collection)
	{
		$query = $this->CI->db->query($this->create_sql($collection));
		$insert_id = $this->CI->db->insert_id();
		foreach ($collection as $entry) {
			$entry->pk = $insert_id;
			$entry->is_bound = true;
			$insert_id = $insert_id + 1;
		}
		return $this->CI->db->affected_rows();
	}

	public function update()
	{}

	public function delete_sql()
	{
		$sql = array();
		$sql[] = 'DELETE';
		$sql[] = 'FROM';
		$sql[] = SQLModel::$meta[$this->model]['table_name'];
		if (count($this->where_cond)) {
			$sql[] = 'WHERE '. implode(' AND ', $this->where_cond);
		}
		if (count($this->order_by)) {
			$sql[] = 'ORDER BY '. implode(', ', $this->order_by);
		}
		if ($this->limit['row_count'] > 0) {
			$sql[] = 'LIMIT '. $this->limit['offset'] .', '. $this->limit['row_count'];
		}

		return implode(' ', $sql);
	}

	public function delete()
	{
		$query = $this->CI->db->query($this->delete_sql());
		return $this->CI->db->affected_rows();
	}

	/**
	 * Supported aggregate functions:
	 * - avg
	 * - count_distinct
	 * - count
	 * - max
	 * - min
	 * - sum
	 */
	public function aggregate_sql($select_expr)
	{
		$sql = array();
		$sql[] = 'SELECT';
		$sql[] = implode(', ', $this->prepare_select_expr($select_expr));
		$sql[] = 'FROM';
		$sql[] = SQLModel::$meta[$this->model]['table_name'];
		if (count($this->where_cond)) {
			$sql[] = 'WHERE '. implode(' AND ', $this->where_cond);
		}
		if ($this->select_expr != array('*')) {
			$sql[] = 'GROUP BY '. implode(', ', array_map(array($this, 'escape'), array_values($this->select_expr)));
		}
		if (count($this->order_by)) {
			$sql[] = 'ORDER BY '. implode(', ', $this->order_by);
		}
		if ($this->limit['row_count'] > 0) {
			$sql[] = 'LIMIT '. $this->limit['offset'] .', '. $this->limit['row_count'];
		}

		return implode(' ', $sql);
	}

	/**
	 * @todo
	 * - should allow array params
	 *   i.e. array('min_price' => 'price__min', 'max_price' => 'price__max')
	 */
	public function aggregate()
	{
		if (($argc = func_num_args()) === 0) {
			trigger_error(__METHOD__ .'() expects at least one aggregate function', E_USER_ERROR);
		}
		$argv = func_get_args();
		$fn_list = array(
			'avg' => 'AVG(`%s`) AS `%s`',
			'count_distinct' => 'COUNT(DISTINCT `%s`)',
			'count' => 'COUNT(`%s`)',
			'max' => 'MAX(`%s`)',
			'min' => 'MIN(`%s`)',
			'sum' => 'SUM(`%s`)'
		);
		if ($this->select_expr == array('*')) {
			$select_expr = array();
		}
		else {
			$select_expr = $this->select_expr;
		}
		foreach ($argv as $field_lookup) {
			$lookup_type = explode('__', $field_lookup);
			$field = array_shift($lookup_type);
			if ($fn = current($lookup_type)) {
				if (isset($fn_list[$fn])) {
					$select_expr[$field_lookup] = sprintf($fn_list[$fn], $field);
				}
				else {
					trigger_error('Invalid aggregate function', E_USER_ERROR);
				}
			}
			else {
				trigger_error('Aggregate function require a field name', E_USER_ERROR);
			}
		}

		$result = array();
		$query = $this->CI->db->query($this->aggregate_sql($select_expr));
		if ($query->num_rows()) {
			if ($this->select_expr == array('*')) {
				$result = $query->row();
			}
			else {
				$result = array();
				foreach ($query->result() as $row) {
					$result[] = $row;
				}
			}
		}
		return $result;
	}

	/**
	 * map / reduce
	 */
	public function map($callback)
	{
		if ($this->is_executed === false) {
			$this->select();
		}
		$result = array();
		if (count($this->collection)) {
			foreach ($this->collection as $index => $document) {
				$result[] = call_user_func($callback, $document, $index, $this->collection);
			}
		}
		return $result;
	}

	public function reduce($callback, $initial = null)
	{
		if ($this->is_executed === false) {
			$this->select();
		}
		$is_value_set = false;
		if (count($this->collection)) {
			if (func_num_args() > 1) {
				$previous_value = $initial;
				$is_value_set = true;
			}
			foreach ($this->collection as $index => $current_value) {
				$previous_value = call_user_func($callback, $previous_value, $current_value, $index, $this->collection);
				$is_value_set = true;
			}
		}
		if (!$is_value_set) {
			throw new Exception('Reduce of empty array with no initial value');
		}
		return $previous_value;
	}

	/**
	 * ArrayAccess
	 */
	public function offsetExists($offset)
	{
		if ($this->is_executed === FALSE) {
			$this->select();
		}
		return array_key_exists($offset, $this->collection);
	}

	public function offsetGet($offset)
	{
		if ($this->is_executed === FALSE) {
			$this->select();
		}
		return $this->collection[$offset];
	}

	public function offsetSet($offset, $value)
	{
		throw new Exception('Modifying an immutable object is futile');
		/*
		if ($offset === NULL) {
			$this->collection[] = $value;
		}
		else {
			$this->collection[$offset] = $value;
		}
		*/
	}

	public function offsetUnset($offset)
	{
		throw new Exception('Modifying an immutable object is futile');
		/*
		unset($this->collection[$offset]);
		*/
	}

	/**
	 * Iterator interface
	 */
	public function current()
	{
		return current($this->collection);
	}

	public function key()
	{
		return key($this->collection);
	}

	public function next()
	{
		return next($this->collection);
	}

	public function rewind()
	{
		if ($this->is_executed === FALSE) {
			$this->select();
		}
		return reset($this->collection);
	}

	public function valid()
	{
		return !is_null(key($this->collection));
	}

	/**
	 * Count interface
	 */
	public function count()
	{
		if ($this->is_executed === FALSE) {
			$this->select();
		}
		return count($this->collection);
	}

	/**
	 * Magic stuff
	 */
	public function __clone()
	{
		$this->collection = array();
		$this->is_executed = FALSE;
	}
}
