<?php

namespace BasicQueryFilter;

class ParseTree
{
    const COMBINED_BY_AND = 'AND';
    const COMBINED_BY_OR = 'OR';
    /** @var PredicateInterface[] */
    public $predicates = [];
    protected $nestParent = null;

    public function getPredicates()
    {
        return $this->predicates;
    }

    public function addPredicate(PredicateInterface $predicate, $combinedBy = self::COMBINED_BY_AND)
    {
        if (!in_array($combinedBy, [self::COMBINED_BY_AND, self::COMBINED_BY_OR])) {
            throw new \InvalidArgumentException('Must be combined by AND or OR');
        }
        $this->predicates[] = [$combinedBy, $predicate];
    }

    public function nest()
    {
        $parseTree = new static;
        $parseTree->nestParent = $this;
        return $parseTree;
    }

    public function unnest()
    {
        $parent = $this->nestParent;
        $this->nestParent = null;
        return $parent;
    }
}

