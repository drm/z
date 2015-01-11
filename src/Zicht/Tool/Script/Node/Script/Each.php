<?php
/**
 * For licensing information, please see the LICENSE file accompanied with this file.
 *
 * @author Gerard van Helden <drm@melp.nl>
 * @copyright 2012 Gerard van Helden <http://melp.nl>
 */

namespace Zicht\Tool\Script\Node\Script;

use \Zicht\Tool\Script\Buffer;
use \Zicht\Tool\Script\Node\Branch;
use \Zicht\Tool\Script\Node\Node;

class Each extends Branch implements Annotation
{
    /**
     * Construct the decorator with the specified expression as the first and only child node.
     *
     * @param \Zicht\Tool\Script\Node\Node $expr
     */
    public function __construct($expr)
    {
        parent::__construct(array($expr));
    }
    /**
     * Allows the annotation to modify the buffer before the script is compiled.
     *
     * @param Buffer $buffer
     * @return void
     */
    public function beforeScript(Buffer $buffer)
    {
        $buffer->writeln('foreach ((array)');
        $this->nodes[0]->compile($buffer);
        $buffer
            ->write(' as $_key => $_value) {')
            ->write('$z->push(\'_key\', $_key);')
            ->write('$z->push(\'_value\', $_value);')
        ;
    }

    /**
     * Allows the annotation to modify the buffer after the script is compiled.
     *
     * @param Buffer $buffer
     * @return void
     */
    public function afterScript(Buffer $buffer)
    {
        $buffer->write('$z->pop(\'_key\');');
        $buffer->write('$z->pop(\'_value\');');
        $buffer->writeln('}');
    }

    /**
     * Compiles the node into the buffer.
     *
     * @param \Zicht\Tool\Script\Buffer $buffer
     * @return void
     */
    public function compile(Buffer $buffer)
    {
    }
}