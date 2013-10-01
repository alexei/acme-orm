<?php

namespace AcmeOrm\Field;

class Int extends Base
{
    public $is_unsigned = false;

    public function to_sql()
    {
        return (int)$this->val;
    }

    public function to_php()
    {
        return (int)$this->val;
    }
}
