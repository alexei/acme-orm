<?php

namespace AcmeOrm\Field;

abstract class Base
{
    public $document;

    public $name;
    public $is_required = false;

    public $val;

    public function __toString()
    {
        return $this->val;
    }

    public function is_valid() {
    }
}
