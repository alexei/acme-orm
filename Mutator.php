<?php

/**
 * @todo
 * - add support for arguments
 */
class Mutator
{
	protected $__attrs_cache__ = array();
	protected $__declared_attrs__ = array();
	protected $__defined_getters__ = array();
	protected $__defined_setters__ = array();
	protected $__defined_issetters__ = array();
	protected $__defined_unsetters__ = array();

	public function __lookupGetter__($key)
	{
		if (array_key_exists($key, $this->__defined_getters__)) {
			return $this->__defined_getters__[$key];
		}
		else {
			return NULL;
		}
	}

	public function __defineGetter__($key, $getter) {
		if (isset($this->{$key})) {
			$this->__attrs_cache__[$key] = $this->__declared_attrs__[$key] = $this->{$key};
			unset($this->{$key});
		}
		else {
			$this->__defined_getters__[$key] = $getter;
		}
	}

	public function __get($key)
	{
		if (($getter = $this->__lookupGetter__($key)) !== NULL) {
			return call_user_func($getter, $key);
		}
		else if (is_callable(array($this, $fn = '__get_'. $key .'__'))) {
			return call_user_func(array($this, $fn));
		}
		else {
			return array_key_exists($key, $this->__attrs_cache__) ? $this->__attrs_cache__[$key] : NULL;
		}
	}

	public function __lookupSetter__($key)
	{
		if (array_key_exists($key, $this->__defined_setters__)) {
			return $this->__defined_setters__[$key];
		}
		else {
			return NULL;
		}
	}

	public function __defineSetter__($key, $setter) {
		if (isset($this->{$key})) {
			$this->__attrs_cache__[$key] = $this->__declared_attrs__[$key] = $this->{$key};
			unset($this->{$key});
		}
		else {
			$this->__defined_setters__[$key] = $setter;
		}
	}

	public function __set($key, $val)
	{
		if (($setter = $this->__lookupSetter__($key)) !== NULL) {
			return call_user_func($setter, $key, $val);
		}
		else if (is_callable(array($this, $fn = '__set_'. $key .'__'))) {
			return call_user_func(array($this, $fn), $val);
		}
		else {
			return $this->__attrs_cache__[$key] = $val;
		}
	}

	public function __lookupIsSetter__($key)
	{
		if (array_key_exists($key, $this->__defined_issetters__)) {
			return $this->__defined_issetters__[$key];
		}
		else {
			return NULL;
		}
	}

	public function __defineIsSetter__($key, $issetter) {
		if (isset($this->{$key})) {
			$this->__attrs_cache__[$key] = $this->__declared_attrs__[$key] = $this->{$key};
			unset($this->{$key});
		}
		else {
			$this->__defined_issetters__[$key] = $issetter;
		}
	}

	public function __isset($key)
	{
		if (($issetter = $this->__lookupIsSetter__($key)) !== NULL) {
			return call_user_func($issetter, $key, $val);
		}
		else if (is_callable(array($this, $fn = '__isset_'. $key .'__'))) {
			return call_user_func(array($this, $fn));
		}
		else {
			return isset($this->__attrs_cache__[$key]);
		}
	}

	public function __lookupUnSetter__($key)
	{
		if (array_key_exists($key, $this->__defined_unsetters__)) {
			return $this->__defined_unsetters__[$key];
		}
		else {
			return NULL;
		}
	}

	public function __defineUnSetter__($key, $unsetter) {
		if (isset($this->{$key})) {
			$this->__attrs_cache__[$key] = $this->__declared_attrs__[$key] = $this->{$key};
			unset($this->{$key});
		}
		else {
			$this->__defined_unsetters__[$key] = $unsetter;
		}
	}

	public function __unset($key)
	{
		if (($unsetter = $this->__lookupUnSetter__($key)) !== NULL) {
			return call_user_func($unsetter, $key, $val);
		}
		else if (is_callable(array($this, $fn = '__unset_'. $key .'__'))) {
			return call_user_func(array($this, $fn));
		}
		else {
			unset($this->__attrs_cache__[$key]);
		}
	}
}
