<?php

namespace Zicht\Tool;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Zicht\Tool\Script\Node\Node;

class Parser
{
    public function __construct($file, $str)
    {
        $this->expr = new ExpressionLanguage();
        $this->file = 'STDIN';
        $this->str = $str;
    }


    public function parse()
    {
        $stack = [$this->newNode('root', -1)];

        $offset = 0;
        foreach (explode("\n", $this->str) as $line) {
            preg_match('/^( *)(.*)/s', $line, $indentMatch);
            $lineIndent = strlen($indentMatch[1]);
            $lineValue = $indentMatch[2];

            $lineValue = preg_replace('/#.*/', '', $lineValue);
            $previousNode = array_pop($stack);

            if (preg_match('/(?:-|(^\w+(?:\.\w+)*(?:\[\])?):)(\s*)(.*)/', $lineValue, $nodeMatch)) {
                $node = $this->newNode($nodeMatch[1], $lineIndent, strlen($nodeMatch[2]), $nodeMatch[3]);
                $node->attributes['offset']= $offset + $lineIndent;

                while ($previousNode->attributes['indent'] >= $node->attributes['indent']) {
                    $parent = array_pop($stack);
                    $parent->append($previousNode);
                    $previousNode = $parent;
                }

                array_push($stack, $previousNode);
                $previousNode = $node;
            } elseif (strlen(trim($lineValue))) {
                if ($lineIndent < $previousNode->attributes['data_indent']) {
                    $this->err("Unexpected decreasing indent. Expected indent is {$previousNode->attributes['data_indent']}, found {$lineIndent}", $offset + $lineIndent);
                } else {
                    if ($previousNode->attributes['data']) {
                        $previousNode->attributes['data'] .= "\n" . substr($line, $previousNode->attributes['data_indent']);
                    } else {
                        $previousNode->attributes['data_indent']= $lineIndent;
                        $previousNode->attributes['data']= $lineValue;
                    }
                }
            }
            array_push($stack, $previousNode);

            $offset += strlen($line) +1;
        }


        while(count($stack) >= 2) {
            $child = array_pop($stack);
            $parent = array_pop($stack);
            $parent->append($child);
            array_push($stack, $parent);
        }

        $root = array_pop($stack);

        return $this->fold($root);
    }


    private function fold($node, $path = [])
    {
        if ($node->attributes['data']) {
            // array or object literals are handled by the expression parser.
            if (preg_match('/^(\{|\[).*(\}|\])$/s', trim($node->attributes['data']))) {
                try {
                    $node->attributes['data']= $this->expr->evaluate($node->attributes['data']);
                } catch (SyntaxError $e) {
                    if (!preg_match('/position (\d+)/', $e->getMessage(), $m)) {
                        throw $e;
                    }

                    $this->err(
                        sprintf(
                            "\nExpression parse error:\n%s\n%s",
                            $e->getMessage(),
                            $this->formatPosition($m[1], $node->attributes['data'])
                        ),
                        $node->attributes['offset']
                    );
                }
            }
            return $node->attributes['data'];


        } elseif ($node->nodes) {
            $ret = [];
            foreach ($node->nodes as $child) {
                if ($child->attributes['name'] === '') {
                    $ret[]= $this->fold($child);
                } else {
                    $ret[$child->attributes['name']]= $this->fold($child);
                }
            }
            return $ret;
        } else {
            return null;
        }
    }


    public function formatPosition($offset, $str)
    {
        $lineNr = substr_count(substr($str, 0, $offset), "\n");
        $lines = explode("\n", $str);

        $tmp = $offset;
        $lineOffset = 0;

        while ($tmp > 0 && $str[$tmp-1] !== "\n") {
            $tmp --;
            $lineOffset ++;
        }

        return sprintf("\n%4d. %s\n      %s^-- here\n", $lineNr +1, $lines[$lineNr], str_repeat(" ", $lineOffset));
    }


    public function err($message, $offset)
    {
        $lineNr = substr_count(substr($this->str, 0, $offset), "\n");
        $msg = sprintf("Parse error in %s at line %d:\n", $this->file, $lineNr +1);
        $msg .= sprintf($this->formatPosition($offset, $this->str));
        $msg .= sprintf("%s\n", $message);

        throw new \UnexpectedValueException($msg);
    }

    private function newNode($name, $indent, $dataIndent = 0, $data = null)
    {
        $ret = new Node();
        
        $ret->attributes = [
            'name' => $name,
            'indent' => $indent,
            'data_indent' =>
                $data
                    ? (
                        $data === '|'
                        ? null
                        : ($indent + strlen($name) + 1 + $dataIndent) // the 1 is the colon
                    )
                    : null,
            'data' => $data === '|' ? '' : $data,
        ];
        return $ret;
    }
}

