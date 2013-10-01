<?php

class Array extends Base
{
    const FMT_JSON = 1;
    const FMT_PHP = 2;

    public $format = self::FMT_JSON;

    public function to_sql()
    {
        if ($this->format == self::FMT_JSON) {
            return json_encode($this->val);
        }
        else if ($this->format == self::FMT_PHP) {
            return serialize($this->val);
        }
    }

    public function to_php()
    {
        if ($this->format == self::FMT_JSON) {
            return json_decode($this->val, true);
        }
        else if ($this->format == self::FMT_PHP) {
            return unserialize($this->val);
        }
    }
}
