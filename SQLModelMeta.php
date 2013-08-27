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
			$uncamelized_model_name = acme_uncamelize($model, ' ');

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
					// echo acme_dump(SQLModel::$meta[$meta['model']]['primary_key'] .'_'. $name);
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
				}
			}
		}

		$class_list = array_diff_key(array_flip(acme_get_subclasses('SQLModel')), $this->model_list);
		while (count($class_list)) {
			$class = key($class_list);
			$this->reflect($class);
			$class_list = array_diff_key($class_list, $this->model_list);
		}
	}
}
