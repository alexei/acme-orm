<?php

class SQLModelMeta implements ArrayAccess
{
	public $model_list = array();

	public function offsetExists($model)
	{
		return isset($this->model_list[$model]);
	}

	public function offsetGet($model)
	{
		if (!isset($this->model_list[$model])) {
			$this->reflect($model);
		}
		return $this->model_list[$model];
	}

	public function offsetSet($model, $meta)
	{
		$this->model_list[$model] = $meta;
	}

	public function offsetUnset($model)
	{
		unset($this->model_list[$model]);
	}

	public function reflect($model)
	{
		if (!isset($this->model_list[$model])) {
			$uncamelized_model_name = _uncamelize($model, ' ');

			$class_info = new ReflectionClass($model);
			$super_model = is_subclass_of($super_class = get_parent_class($model), 'SQLModel') ? $super_class : NULL;
			$super_model_list = array();
			$concrete_super_model_list = array();
			if ($super_model) {
				$super_model_list = $this[$super_model]['super_model_list'];
				array_unshift($super_model_list, $super_model);

				$concrete_super_model_list = $this[$super_model]['concrete_super_model_list'];
				if ($this[$super_model]['is_abstract'] === FALSE && $this[$super_model]['is_proxy'] === FALSE) {
					array_unshift($concrete_super_model_list, $super_model);
				}
			}

			$model_is_abstract = $class_info->isAbstract();

			$prop_is_proxy = $class_info->getProperty('is_proxy');
			$model_is_proxy = $prop_is_proxy->class === $model ? $prop_is_proxy->getValue() : FALSE;

			$prop_table = $class_info->getProperty('table_name');
			$model_table = $prop_table->getValue();
			if ($prop_table->class !== $model) {
				if ($model_is_proxy) {
					$model_table = $this[$super_model]['table_name'];
				}
				else {
					$model_table = strtolower(str_replace(' ', '_', $uncamelized_model_name));
				}
			}

			$prop_fields = $class_info->getProperty('fields');
			$model_fields = $prop_fields->getValue();
			if ($model_is_proxy) {
				$model_fields = $this[$super_model]['fields'];
			}
			else {
				if ($super_model && $this[$super_model]['is_abstract']) {
					$model_fields = array_merge($this[$super_model]['fields'], $model_fields);
				}
			}
			$primary_key = NULL;
			if (count($model_fields)) {
				foreach ($model_fields as $name => $field) {
					if (isset($field['primary_key'])) {
						$primary_key = $name;
						break;
					}
				}
			}
			if ($model_is_abstract === FALSE && $primary_key === NULL) {
				$primary_key = 'id';
				$model_fields[$primary_key] = array('type' => 'serial', 'primary_key' => TRUE, 'editable' => FALSE);
			}

			$prop_verbose_name = $class_info->getProperty('verbose_name');
			$model_verbose_name = $prop_verbose_name->getValue();
			if ($prop_verbose_name->class !== $model) {
				$model_verbose_name = ucwords($uncamelized_model_name);
			}

			$this->model_list[$model] = array(
				'super_model_list' => $super_model_list,
				'concrete_super_model_list' => $concrete_super_model_list,
				'is_abstract' => $model_is_abstract,
				'is_proxy' => $model_is_proxy,
				'table_name' => $model_table,
				'fields' => $model_fields,
				'primary_key' => $primary_key,
				'verbose_name' => $model_verbose_name,
				'relations' => array(),
			);

			/**
			 * PHP's answer to the ultimate question of life, the universe and everything: fuck you!
			 * http://bugs.php.net/bug.php?id=39449
			 * http://bugs.php.net/bug.php?id=41116
			 * http://bugs.php.net/bug.php?id=41641
			 * http://bugs.php.net/bug.php?id=42030
			 */
			// relations
			foreach ($model_fields as $name => $meta) {
				// echo $model, '->', $name, '<br />';
				if ($meta['type'] === 'one_to_one') {
				}
				else if ($meta['type'] === 'one_to_many') {
					$_name = isset($meta['db_field']) ? $meta['db_field'] : SQLModel::$meta[$meta['model']]['primary_key'] .'_'. $name;
					$this->model_list[$model]['fields'][$_name] = array('type' => 'uint', 'index' => TRUE);
					unset($this->model_list[$model]['fields'][$name]);

					$this->model_list[$model]['relations'][$name] = array(
						'type' => $meta['type'],
						'model' => $meta['model'],
						'relation_field' => $_name,
						'other_field' => '',
						'implicit' => FALSE,
						'self' => FALSE,
					);
					/*if ($model === $meta['model']) {
						$relations[$name]['self'] = TRUE;
					}
					else {*/
						$related_model_meta = SQLModel::$meta[$meta['model']];
						$related_name = isset($meta['related_name']) ? $meta['related_name'] : strtolower($model) .'_set';
						$relations[$name]['other_field'] = $related_name;
						$related_model_meta['relations'][$related_name] = array(
							'type' => $meta['type'],
							'model' => $model,
							'relation_field' => NULL,
							'other_field' => $name,
							'implicit' => TRUE,
							'self' => FALSE,
						);
						SQLModel::$meta[$meta['model']] = $related_model_meta;
					/*}*/
				}
				else if ($meta['type'] === 'many_to_many') {
					$related_model = $meta['model'];

					$model_meta = SQLModel::$meta[$model];
					$related_model_meta = SQLModel::$meta[$related_model];

					$relation_table = isset($meta['db_table']) ? $meta['db_table'] : $model_meta['table_name'] .'_'. $related_model_meta['table_name'];

					// model
					unset($model_meta['fields'][$name]);
					$model_meta['relations'][$name] = array(
						'type' => 'many_to_many',
						'model' => $related_model,
						'relation_field' => $related_model_meta['table_name'] .'_'. $related_model_meta['primary_key'],
						'other_field' => $meta['related_name'],
						'relation_table' => $relation_table,
						'implicit' => false,
						'self' => false,
					);

					// related model
					$related_model_meta['relations'][$meta['related_name']] = array(
						'type' => 'many_to_many',
						'model' => $model,
						'relation_field' => $model_meta['table_name'] .'_'. $model_meta['primary_key'],
						'other_field' => $name,
						'relation_table' => $relation_table,
						'implicit' => true,
						'self' => false,
					);

					SQLModel::$meta[$model] = $model_meta;
					SQLModel::$meta[$related_model] = $related_model_meta;

					/*$fixture = $model .'_'. $related_model;

					$model_field_name = $model_meta['table_name'] .'_'. $model_meta['primary_key'];
					$model_field = $model_meta['fields'][$model_meta['primary_key']];
					unset($model_field['primary_key']);

					$related_model_field_name = $related_model_meta['table_name'] .'_'. $related_model_meta['primary_key'];
					$related_model_field = $related_model_meta['fields'][$related_model_meta['primary_key']];
					unset($related_model_field['primary_key']);

					$model_field_relation = array(
						'type' => 'one_to_many',
						'model' => $fixture,
						'relation_field' => $model_field_name,
						'other_field' => '',
						'implicit' => true,
						'self' => false,
					);
					$this->model_list[$model]['relations'][$name] = $model_field_relation;
					$fixture_model_field_relation = array(
						'type' => 'one_to_many',
						'model' => $related_model,
						'relation_field' => $related_model_field_name,
						'other_field' => '',
						'implicit' => false,
						'self' => false,
					);

					$related_model_field_relation = array(
						'type' => 'one_to_many',
						'model' => $fixture,
						'relation_field' => '',
						'other_field' => $related_model_field_name,
						'implicit' => false,
						'self' => false,
					);
					// $related_model_meta = SQLModel::$meta[$meta['model']];
					$related_model_meta['relations'][$related_model] = $related_model_field_relation;
					SQLModel::$meta[$meta['model']] = $related_model_meta;
					$fixture_related_model_field_relation = array(
						'type' => 'one_to_many',
						'model' => $model,
						'relation_field' => $model_field_name,
						'other_field' => '',
						'implicit' => false,
						'self' => false,
					);

					$fixture_table_name = $model_meta['table_name'] .'_'. $related_model_meta['table_name'];
					$fixture_fields = array();
					$fixture_fields[$model_field_name] = $model_field;
					$fixture_fields[$related_model_field_name] = $related_model_field;
					$fixture_relations = array();
					$fixture_relations[$name] = $fixture_model_field_relation;
					$fixture_relations[$meta['related_name']] = $fixture_related_model_field_relation;
					$fixture_meta = array(
						'is_fixture' => true,
						'table_name' => $fixture_table_name,
						'fields' => $fixture_fields,
						'relations' => $fixture_relations,
					);
					SQLModel::$meta[$fixture] = $fixture_meta;*/
				}
			}
		}

		$class_list = array_diff_key(array_flip(_get_subclasses('SQLModel')), $this->model_list);
		while (count($class_list)) {
			$class = key($class_list);
			$this->reflect($class);
			$class_list = array_diff_key($class_list, $this->model_list);
		}
	}

	public function many_to_many_fixture($model, $name, $meta) {

	}
}
