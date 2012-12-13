<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Tool\Task\Release;

use Zicht\Tool\Task\Task;

class Deploy extends Task
{
    static function uses()
    {
        return array(
            'environment',
            'sync.src'
        );
    }


    function execute()
    {
        foreach ($this->options['post'] as $command) {
            $this->context->execScript($command);
        }
        echo "Released to environment " . $this->context->getEnvironment() . "\n";
    }
}