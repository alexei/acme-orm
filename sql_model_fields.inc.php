<?php

/**
 * Fields
 * @todo implement this
 */
function field_prepare(&$meta) {
	return $meta;
}

function field_emit_signal($signal, $model, $field, $meta) {
	$fn = 'field_'. $meta['type'] .'_'. $signal;
	if (is_callable($fn)) {
		call_user_func($fn, $model, $field, $meta);
	}
}


/**
 * Char
 */
function field_char_pre_init($model, $field, $meta) {
	$model->{$field} = isset($meta['default']) ? $meta['default'] : '';
}

/**
 * Text
 */
function field_text_pre_init($model, $field, $meta) {
	$model->{$field} = isset($meta['default']) ? $meta['default'] : '';
}



/**
 * Serial (Int)
 */
function field_serial_pre_init($model, $field, $meta) {
	$model->{$field} = isset($meta['default']) ? (int)$meta['default'] : 0;
}
function field_serial_post_init($model, $field, $meta) {
	$model->{$field} = (int)$model->{$field};
}
function field_serial_pre_save($model, $field, $meta) {
	$model->{$field} = (int)$model->{$field};
}
function field_serial_post_save($model, $field, $meta) {
	$model->{$field} = (int)$model->{$field};
}

/**
 * Int
 */
function field_int_pre_init($model, $field, $meta) {
	$model->{$field} = isset($meta['default']) ? (int)$meta['default'] : FALSE;
}
function field_int_post_init($model, $field, $meta) {
	$model->{$field} = (int)$model->{$field};
}
function field_int_pre_save($model, $field, $meta) {
	$model->{$field} = (int)$model->{$field};
}
function field_int_post_save($model, $field, $meta) {
	$model->{$field} = (int)$model->{$field};
}


/**
 * Unsigned Int
 */
function field_uint_pre_init($model, $field, $meta) {
	$model->{$field} = isset($meta['default']) ? (int)$meta['default'] : FALSE;
}
function field_uint_post_init($model, $field, $meta) {
	$model->{$field} = (int)$model->{$field};
}
function field_uint_pre_save($model, $field, $meta) {
	$model->{$field} = (int)$model->{$field};
}
function field_uint_post_save($model, $field, $meta) {
	$model->{$field} = (int)$model->{$field};
}


/**
 * Float
 */
function field_float_pre_init($model, $field, $meta) {
	$model->{$field} = isset($meta['default']) ? floatval($meta['default']) : FALSE;
}
function field_float_post_init($model, $field, $meta) {
	$model->{$field} = floatval($model->{$field});
}
function field_float_pre_save($model, $field, $meta) {
	$model->{$field} = floatval($model->{$field});
}
function field_float_post_save($model, $field, $meta) {
	$model->{$field} = floatval($model->{$field});
}


/**
 * Unsigned Float
 */
function field_ufloat_pre_init($model, $field, $meta) {
	$model->{$field} = isset($meta['default']) ? (float)$meta['default'] : FALSE;
}
function field_ufloat_post_init($model, $field, $meta) {
	$model->{$field} = (float)$model->{$field};
}
function field_ufloat_pre_save($model, $field, $meta) {
	$model->{$field} = (float)$model->{$field};
}
function field_ufloat_post_save($model, $field, $meta) {
	$model->{$field} = (float)$model->{$field};
}


/**
 * Bool
 */
function field_bool_pre_init($model, $field, $meta) {
	$model->{$field} = isset($meta['default']) ? (bool)$meta['default'] : FALSE;
}
function field_bool_post_init($model, $field, $meta) {
	$model->{$field} = (int)$model->{$field};
}
function field_bool_pre_save($model, $field, $meta) {
	$model->{$field} = (int)$model->{$field};
}
function field_bool_post_save($model, $field, $meta) {
	$model->{$field} = (bool)$model->{$field};
}


/**
 * Date
 */
function field_date_pre_init($model, $field, $meta) {
	$model->{$field} = isset($meta['default']) ? $meta['default'] : strftime('%Y-%m-%d', 0);
}

function field_date_pre_save($model, $field, $meta) {
	if (isset($meta['auto'])) {
		if ($meta['auto'] === SQLModel::AUTO_ON_CREATE && $model->is_bound === FALSE) {
			$model->{$field} = strftime('%Y-%m-%d');
		}
		else if ($meta['auto'] === SQLModel::AUTO_ON_UPDATE) {
			$model->{$field} = strftime('%Y-%m-%d');
		}
	}
}


/**
 * Time
 */
function field_time_pre_init($model, $field, $meta) {
	$model->{$field} = isset($meta['default']) ? $meta['default'] : strftime('%H:%M:%S', 0);
}

function field_time_pre_save($model, $field, $meta) {
	if (isset($meta['auto'])) {
		if ($meta['auto'] === SQLModel::AUTO_ON_CREATE && $model->is_bound === FALSE) {
			$model->{$field} = strftime('%H:%M:%S');
		}
		else if ($meta['auto'] === SQLModel::AUTO_ON_UPDATE) {
			$model->{$field} = strftime('%H:%M:%S');
		}
	}
}


/**
 * Datetime
 */
function field_datetime_pre_init($model, $field, $meta) {
	$model->{$field} = isset($meta['default']) ? $meta['default'] : strftime('%Y-%m-%d %H:%M:%S', 0);
}

function field_datetime_pre_save($model, $field, $meta) {
	if (isset($meta['auto'])) {
		if ($meta['auto'] === SQLModel::AUTO_ON_CREATE && $model->is_bound === FALSE) {
			$model->{$field} = strftime('%Y-%m-%d %H:%M:%S');
		}
		else if ($meta['auto'] === SQLModel::AUTO_ON_UPDATE) {
			$model->{$field} = strftime('%Y-%m-%d %H:%M:%S');
		}
	}
}


/**
 * supertypes
 */
/**
 * File
 */
function field_file_pre_init($model, $field, $meta) {
	$model->{$field} = isset($meta['default']) ? $meta['default'] : '';
}
function field_file_post_delete($model, $field, $meta) {
	$file_path = $model->{$field};
	return file_exists($file_path) && is_writable(dirname($file_path)) && unlink($file_path);
}


/**
 * IP
 */
function field_ip_pre_save($model, $field, $meta) {
	if (isset($meta['auto'])) {
		if ($meta['auto'] === SQLModel::AUTO_ON_CREATE && $model->is_bound === FALSE) {
			$model->{$field} = acme_ip_address();
		}
		else if ($meta['auto'] === SQLModel::AUTO_ON_UPDATE) {
			$model->{$field} = acme_ip_address();
		}
	}
}


/**
 * UUID
 */
function field_uuid_pre_save($model, $field, $meta) {
	if (isset($meta['auto'])) {
		if ($meta['auto'] === SQLModel::AUTO_ON_CREATE && $model->is_bound === FALSE) {
			$model->{$field} = UUID::generate(UUID::UUID_RANDOM, UUID::FMT_STRING);
		}
		else if ($meta['auto'] === SQLModel::AUTO_ON_UPDATE) {
			$model->{$field} = UUID::generate(UUID::UUID_RANDOM, UUID::FMT_STRING);
		}
	}
}


/**
 * Slug
 */
function field_slug_pre_save($model, $field, $meta) {
	if (isset($meta['auto'])) {
		if ($meta['auto'] === SQLModel::AUTO_ON_CREATE && $model->is_bound === FALSE) {
			$model->{$field} = substr(URLify::filter($model->{$meta['field']}), 0, $meta['max_length']);
			if (array_key_exists('preserve_case', $meta) && $meta['preserve_case'] == FALSE) {
				$model->{$field} = strtolower($model->{$field});
			}
		}
		else if ($meta['auto'] === SQLModel::AUTO_ON_UPDATE) {
			$model->{$field} = substr(URLify::filter($model->{$meta['field']}), 0, $meta['max_length']);
			if (array_key_exists('preserve_case', $meta) && $meta['preserve_case'] == FALSE) {
				$model->{$field} = strtolower($model->{$field});
			}
		}
	}
}


/**
 * Teaser
 * @xxx this is weird
 */
/*
function field_teaser_pre_save($model, $field, $meta) {
	if (isset($meta['auto'])) {
		if ($meta['auto'] === SQLModel::AUTO_ON_CREATE && $model->is_bound === FALSE) {
			$model->{$field} = acme_close_tags(acme_teaser($model->{$meta['field']}, $meta['max_length']));
		}
		else if ($meta['auto'] === SQLModel::AUTO_ON_UPDATE) {
			$model->{$field} = acme_close_tags(acme_teaser($model->{$meta['field']}, $meta['max_length']));
		}
	}
}
*/


/**
 * Objects
 */
function field_array_pre_init($model, $field, $meta) {
	$model->{$field} = isset($meta['default']) ? $meta['default'] : array();
}
function field_array_post_init($model, $field, $meta) {
	if ($model->{$field} && is_string($model->{$field})) {
		switch ($meta['format']) {
			case 'json':
				$model->{$field} = (array)json_decode($model->{$field});
				break;
			case 'php':
			default:
				$model->{$field} = unserialize($model->{$field});
				break;
		}
	}
}
function field_array_pre_save($model, $field, $meta) {
	switch ($meta['format']) {
		case 'json':
			$model->{$field} = json_encode($model->{$field});
			break;
		case 'php':
		default:
			$model->{$field} = serialize($model->{$field});
			break;
	}
}
function field_array_post_save($model, $field, $meta) {
	switch ($meta['format']) {
		case 'json':
			$model->{$field} = (array)json_decode($model->{$field});
			break;
		case 'php':
		default:
			$model->{$field} = unserialize($model->{$field});
			break;
	}
}

function field_object_pre_init($model, $field, $meta) {
	$model->{$field} = isset($meta['default']) ? $meta['default'] : new stdClass();
}
function field_object_post_init($model, $field, $meta) {
	if ($model->{$field} && is_string($model->{$field})) {
		switch ($meta['format']) {
			case 'json':
				$model->{$field} = json_decode($model->{$field});
				break;
			case 'php':
			default:
				$model->{$field} = unserialize($model->{$field});
				break;
		}
	}
}
function field_object_pre_save($model, $field, $meta) {
	switch ($meta['format']) {
		case 'json':
			$model->{$field} = json_encode($model->{$field});
			break;
		case 'php':
		default:
			$model->{$field} = serialize($model->{$field});
			break;
	}
}
function field_object_post_save($model, $field, $meta) {
	switch ($meta['format']) {
		case 'json':
			$model->{$field} = json_decode($model->{$field});
			break;
		case 'php':
		default:
			$model->{$field} = unserialize($model->{$field});
			break;
	}
}
