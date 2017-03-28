<?php

namespace Test;

use PHPUnit\Framework\TestCase;
use BasicQueryFilter\Identifier;
use BasicQueryFilter\Parser;
use BasicQueryFilter\ParserException;
use BasicQueryFilter\PredicateInterface;

class QueryFilterTest extends TestCase
{
    /**
     * @dataProvider parserSuccessData
     */
    public function testParserSuccess($filter, $predicateSpecs)
    {
        $parser = new Parser();
        $tree = $parser->parse($filter);
        $predicates = $tree->getPredicates();

        $this->assertCount(count($predicateSpecs), $predicates);

        foreach ($predicateSpecs as $i => $predicateSpec) {
            $predicateInfo = $predicates[$i];
            $this->assertEquals($predicateSpec[0], $predicateInfo[0]);

            $predicate = $predicateInfo[1];

            // left
            if ($predicateSpec[1][0] === PredicateInterface::TYPE_IDENTIFIER) {
                $this->assertInstanceOf(Identifier::class, $predicate->left);
                $this->assertEquals(PredicateInterface::TYPE_IDENTIFIER, $predicate->leftType);
                $this->assertEquals($predicateSpec[1][1], $predicate->left->toString());
            } else {
                $this->assertEquals(PredicateInterface::TYPE_VALUE, $predicate->leftType);
                $this->assertEquals($predicateSpec[1][1], $predicate->left);
            }

            $this->assertEquals($predicateSpec[2], $predicate->op);

            // right
            if ($predicateSpec[3][0] === PredicateInterface::TYPE_IDENTIFIER) {
                $this->assertInstanceOf(Identifier::class, $predicate->right);
                $this->assertEquals(PredicateInterface::TYPE_IDENTIFIER, $predicate->rightType);
                $this->assertEquals($predicateSpec[3][1], $predicate->right->toString());
            } else {
                $this->assertEquals(PredicateInterface::TYPE_VALUE, $predicate->rightType);
                $this->assertEquals($predicateSpec[3][1], $predicate->right);
            }
        }
    }

    public function parserSuccessData()
    {
        return [
            [
                'foo = "Bar"',
                [
                    ['AND', [PredicateInterface::TYPE_IDENTIFIER, 'foo'], '=', [PredicateInterface::TYPE_VALUE, 'Bar']]
                ]
            ],
            [
                'foo = bar',
                [
                    ['AND', [PredicateInterface::TYPE_IDENTIFIER, 'foo'], '=', [PredicateInterface::TYPE_IDENTIFIER, 'bar']]
                ]
            ],
            [
                'foo > 5',
                [
                    ['AND', [PredicateInterface::TYPE_IDENTIFIER, 'foo'], '>', [PredicateInterface::TYPE_VALUE, '5']]
                ]
            ],
            [
                'foo != "200" AND created_at > "2015-01-01"',
                [
                    ['AND', [PredicateInterface::TYPE_IDENTIFIER, 'foo'], '!=', [PredicateInterface::TYPE_VALUE, '200']],
                    ['AND', [PredicateInterface::TYPE_IDENTIFIER, 'created_at'], '>', [PredicateInterface::TYPE_VALUE, '2015-01-01']]
                ]
            ],
            [
                'foo.bar = boom.baz',
                [
                    ['AND', [PredicateInterface::TYPE_IDENTIFIER, 'foo.bar'], '=', [PredicateInterface::TYPE_IDENTIFIER, 'boom.baz']]
                ]
            ]
        ];
    }

    /**
     * @dataProvider parserExceptionData
     */
    public function testParserException($filter, $message)
    {
        $parser = new Parser();
        $this->expectExceptionMessage($message);
        $this->expectException(ParserException::class);
        $parser->parse($filter);
    }

    public function parserExceptionData()
    {
        $expectingMessage = function ($type, $current, $next) {
            return "Expected the *$type* $current to be followed by whitespace or a ), was followed by $next";
        };


        return [
            ['foo = 2005-01', $expectingMessage('value', '2005', '-01')],
            ['foo=2005-01', $expectingMessage('value', '2005', '-01')],
            ['foo = "2005-01"x', $expectingMessage('value', '2005-01', 'x')],
            ['foo = boo/x', $expectingMessage('identifier', 'boo', '/')],
        ];
    }

}