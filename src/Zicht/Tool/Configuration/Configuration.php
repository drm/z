<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */
namespace Zicht\Tool\Configuration;

use \Symfony\Component\Config\Definition\ConfigurationInterface;
use \Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * Configuration implementation validation a Z file configuration
 */
class Configuration implements ConfigurationInterface
{
    /**
     * @var \Zicht\Tool\PluginInterface[]
     */
    protected $plugins = array();

    /**
     * Construct the configuration with a set of plugins
     *
     * @param \Zicht\Tool\PluginInterface[] $plugins
     */
    public function __construct($plugins)
    {
        $this->plugins = $plugins;
    }


    /**
     * Generates the configuration tree builder.
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();

        $zConfig = $treeBuilder->root('z');
        $toArray = function ($s) {
            return array($s);
        };

        $zConfig
            ->children()
                ->scalarNode('SHELL')->end()
                ->scalarNode('TIMEOUT')->end()
                ->arrayNode('vars')
                    ->prototype('variable')->end()
                ->end()
                ->arrayNode('tasks')
                    ->prototype('array')
                        ->beforeNormalization()
                            ->ifTrue(
                                function($in) {
                                    return
                                        is_string($in)

                                        // allow for 'lists' (skipping the 'do' key)
                                     || (is_array($in) && range(0, count($in) -1) === array_keys($in));
                                }
                            )
                            ->then(
                                function($v) {
                                    return array('do' => $v);
                                }
                            )
                        ->end()
                        ->children()
                            ->scalarNode('name')->end()
                            ->scalarNode('help')->defaultValue(null)->end()
                            ->arrayNode('flags')
                                ->prototype('boolean')->end()
                                ->defaultValue(array())
                            ->end()
                            ->arrayNode('args')
                                ->prototype('array')
                                    ->beforeNormalization()
                                        ->ifTrue(function($v) {
                                            return is_scalar($v);
                                        })
                                        ->then(function($v) {
                                            return array(
                                                'type' => 'argument',
                                                'default' => $v
                                            );
                                        })
                                    ->end()
                                    ->children()
                                        ->scalarNode('name')->end()
                                        ->scalarNode('type')->end()
                                        ->scalarNode('default')->end()
                                    ->end()
                                ->end()
                                ->useAttributeAsKey('name')
                                ->defaultValue(array())
                            ->end()
                            ->scalarNode('unless')->defaultValue(null)->end()
                            ->scalarNode('assert')->defaultValue(null)->end()
                            ->arrayNode('pre')
                                ->beforeNormalization()
                                    ->ifString()->then($toArray)
                                ->end()
                                ->prototype('scalar')->end()
                                ->defaultValue(array())
                            ->end()
                            ->arrayNode('post')
                                ->beforeNormalization()
                                    ->ifString()->then($toArray)
                                ->end()
                                ->prototype('scalar')->end()
                                ->defaultValue(array())
                            ->end()
                            ->arrayNode('do')
                                ->beforeNormalization()
                                    ->ifString()->then($toArray)
                                 ->end()
                                ->performNoDeepMerging()
                                ->prototype('scalar')->end()
                                ->defaultValue(array())
                            ->end()
                            ->scalarNode('yield')->defaultValue(null)->end()
                        ->end()
                    ->end()
                    ->useAttributeAsKey('name')
                ->end()
            ->end()
        ->end();

        foreach ($this->plugins as $plugin) {
            $plugin->appendConfiguration($zConfig);
        }

        return $treeBuilder;
    }
}