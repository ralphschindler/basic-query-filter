<?php

namespace BasicQueryFilter;

class Identifier
{
    public $name = null, $field;

    public function toString()
    {
        return $this->__toString();
    }

    public function __toString()
    {
        return ($this->name) ? "{$this->name}.{$this->field}" : $this->field;
    }
}
