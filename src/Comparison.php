<?php

namespace BasicQueryFilter;

class Comparison implements PredicateInterface
{
    public $left, $op, $right;
    public $leftType = self::TYPE_IDENTIFIER, $rightType = self::TYPE_VALUE;
}

