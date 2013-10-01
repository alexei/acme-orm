<?php

namespace AcmeOrm\Field;

class Bool extends Base
{
    public function to_sql()
    {
        return (int)$this->val;
    }

    public function to_php()
    {
        return (bool)$this->val;
    }
}
