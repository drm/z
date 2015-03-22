<?php

namespace Zicht\Tool;

/**
 * Debug helper class
 */
final class Debug
{
    /**
     * @var array
     */
    public static $scope = array();

    public static $scopeChange = array();

    /**
     * @return mixed
     */
    public static function getScope()
    {
        return self::$scope[count(self::$scope) -1];
    }

    /**
     * Keeps track of scope
     *
     * @param string $scope
     * @return void
     */
    public static function enterScope($scope)
    {
        array_push(self::$scope, $scope);
        list($call)= debug_backtrace(0, 2);
        array_push(self::$scopeChange, $call);
    }


    /**
     * @param string $scope
     * @throws ScopeException
     * @return void
     */
    public static function exitScope($scope)
    {
        $current = array_pop(self::$scope);
        $call = array_pop(self::$scopeChange);
        if ($scope !== $current) {
            throw new \LogicException("The current scope '$current' was not closed properly, while trying to close '$scope', which was opened at {$call['file']}@{$call['line']}");
        }
    }
}