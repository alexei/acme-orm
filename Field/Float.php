<?php

namespace AcmeOrm\Field;

class Float extends Base
{
    public $is_unsigned = false;

    public function to_sql()
    {
        return (float)$this->val;
    }

    public function to_php()
    {
        return (float)$this->val;
    }
}
