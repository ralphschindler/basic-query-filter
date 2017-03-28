<?php

namespace BasicQueryFilter;

class Parser
{
    const T_WHITESPACE = 0;
    const T_GENERIC_SYMBOL = 1;
    const T_IDENTIFIER = 2;
    const T_IDENTIFIER_SEPARATOR = 3;
    const T_VALUE = 4;
    const T_COMPARISON_OPERATOR = 5;
    const T_PRECEDENCE_OPERATOR = 6;
    const T_LOGIC_OPERATOR = 7;

    protected $tokens = [];
    protected $tokenIndex = 0;

    /**
     * @param $input
     * @return ParseTree
     */
    public function parse($input)
    {
        $segments = preg_split(
            '#([a-z-_\\\][a-z0-9-_\\\:]*[a-z0-9_]{1})|((?:[0-9]+(?:[\.][0-9]+)*)(?:e[+-]?[0-9]+)?)|(\'(?:[^\']|\'\')*\')|("(?:[^"]|"")*")|([!><=~]{1,2})|(\s+)|(.)#i',
            $input,
            -1,
            PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_OFFSET_CAPTURE
        );
        $this->tokens = [];
        foreach ($segments as $segment) {
            $type = $this->getTokenType($segment[0]);
            $this->tokens[] = [
                'value' => $segment[0],
                'type'  => $type,
                'position' => $segment[1],
            ];
        }

        $parseTree = new ParseTree;
        $token = $this->currentToken();
        $logic = ParseTree::COMBINED_BY_AND;
        while ($token) {
            switch (true) {
                case ($token['type'] == self::T_LOGIC_OPERATOR):
                    $logic = (in_array(strtolower($token['value']), ['or', '||'])) ? ParseTree::COMBINED_BY_OR : ParseTree::COMBINED_BY_AND;
                    break;
                case ($token['type'] == self::T_PRECEDENCE_OPERATOR && $token['value'] == '('):
                    $parseTree = $parseTree->nest();
                    break;
                case ($token['type'] == self::T_PRECEDENCE_OPERATOR && $token['value'] == ')'):
                    $parseTree = $parseTree->unnest();
                    break;
                case ($token['type'] == self::T_IDENTIFIER):
                    $parseTree->addPredicate($this->parsePredicate(), $logic);
                    break;
                default:
                    throw new ParserException("Was expecting a Logic Operator (and, or, &&, ||), Precendence Operator ) or (, or an Identifier (' after {$token['value']}");
            }
            $token = $this->nextToken();
        }

        return $parseTree;
    }

    protected function getTokenType(&$value)
    {
        $type = self::T_GENERIC_SYMBOL;

        switch (true) {
            case (trim($value) === ''):
                return self::T_WHITESPACE;

            case ($value == '.'):
                return self::T_IDENTIFIER_SEPARATOR;

            case (is_numeric($value) || is_numeric($value[0])):
                return self::T_VALUE;

            case ($value[0] === "'"):
                $value = str_replace("''", "'", substr($value, 1, strlen($value) - 2));
                return self::T_VALUE;

            case ($value[0] === '"'):
                $value = str_replace('""', '"', substr($value, 1, strlen($value) - 2));
                return self::T_VALUE;

            case ($value == '(' || $value == ')'):
                return self::T_PRECEDENCE_OPERATOR;

            case (in_array($value{0}, ['=', '>', '<', '!'])):
                return self::T_COMPARISON_OPERATOR;

            case (in_array(strtolower($value), ['and', 'or', '&&', '||'])):
                return self::T_LOGIC_OPERATOR;

            case (ctype_alpha($value[0])):
                return self::T_IDENTIFIER;

        }
        return $type;
    }

    protected function currentToken()
    {
        if (!isset($this->tokens[$this->tokenIndex])) {
            return false;
        }
        return $this->tokens[$this->tokenIndex];
    }

    protected function nextToken()
    {
        INCREMENT_TOKEN:
        ++$this->tokenIndex;

        if (!isset($this->tokens[$this->tokenIndex])) {
            return false;
        }

        if ($this->tokens[$this->tokenIndex]['type'] === self::T_WHITESPACE) {
            goto INCREMENT_TOKEN;
        }

        return $this->tokens[$this->tokenIndex];
    }

    protected function peekToken($increment = 1)
    {
        if (!isset($this->tokens[$this->tokenIndex + $increment])) {
            return false;
        }
        return $this->tokens[($this->tokenIndex + $increment)];
    }

    protected function parsePredicate()
    {
        $identifier = new Identifier;
        $identifier->field = $this->currentToken()['value'];
        $token = $this->nextToken();
        if ($token['type'] == self::T_IDENTIFIER_SEPARATOR) {
            $identifier->name = $identifier->field;
            $token = $this->nextToken();
            if ($token['type'] !== self::T_IDENTIFIER) {
                throw new ParserException('Parser error: predicate expects an identifier (unquoted string) after an identifier separator (dot)');
            }
            $identifier->field = $token['value'];
            $token = $this->nextToken();
        }

        if ($token['type'] !== self::T_COMPARISON_OPERATOR && $token['type'] !== self::T_COMPARISON_OPERATOR) {
            throw new ParserException('A function name or comparison operator must follow an identifer');
        }

        if ($token['type'] == self::T_COMPARISON_OPERATOR) {
            $operator = $token['value'];
            $token = $this->nextToken();
            $peekToken = $this->peekToken();

            if ($token['type'] !== self::T_IDENTIFIER && $token['type'] !== self::T_VALUE) {
                throw new ParserException('Comparisons must have an identifier or value on the right side');
            }

            $predicate = new Comparison();
            $predicate->leftType = Comparison::TYPE_IDENTIFIER;
            $predicate->left = $identifier;
            $predicate->op = $operator;

            if ($token['type'] == self::T_IDENTIFIER) {
                $predicate->rightType = Comparison::TYPE_IDENTIFIER;
                $predicate->right = new Identifier();

                if ($peekToken && $peekToken['type'] === self::T_IDENTIFIER_SEPARATOR) {
                    $predicate->right->name = $token['value'];

                    $this->nextToken(); // separator token
                    $token = $this->nextToken();
                    $peekToken = $this->peekToken();

                    $predicate->right->field = $token['value'];
                } else {
                    $predicate->right->field = $token['value'];
                }
            } else {
                $predicate->rightType = Comparison::TYPE_VALUE;
                $predicate->right = $token['value'];
            }

            if ($peekToken && !in_array($peekToken['type'], [self::T_WHITESPACE, self::T_PRECEDENCE_OPERATOR])) {
                $type = ($token['type'] === self::T_IDENTIFIER) ? 'identifier' : 'value';
                throw new ParserException("Expected the *$type* {$token['value']} to be followed by whitespace or a ), was followed by {$peekToken['value']}");
            }
        } else {
            throw new ParserException('Non-comparison not supported');
        }



        return $predicate;
    }
}
