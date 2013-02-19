<?php
/**
 * For licensing information, please see the LICENSE file accompanied with this file.
 *
 * @author Gerard van Helden <drm@melp.nl>
 * @copyright 2012 Gerard van Helden <http://melp.nl>
 */

namespace Zicht\Tool\Plugin\Svn;

use \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

use \Zicht\Tool\Container\Container;
use \Zicht\Tool\Plugin as BasePlugin;

/**
 * SVN plugin configuration
 */
class Plugin extends BasePlugin
{
    /**
     * Appends SVN configuration options
     *
     * @param \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $rootNode
     * @return mixed|void
     */
    public function appendConfiguration(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('vcs')
                    ->children()
                        ->scalarNode('url')->isRequired()->end()
                        ->arrayNode('export')
                            ->children()
                                ->scalarNode('revfile')->isRequired()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function setContainer(Container $container)
    {
        $container->method('vcs.versionid', function($container, $info) {
            if (
                trim($info)
                && preg_match('/^URL: (.*)/m', $info, $urlMatch)
                && preg_match('/^Revision: (.*)/m', $info, $revMatch)
            ) {
                $url = $urlMatch[1];
                $rev = $revMatch[1];
                $projectUrl = $container->resolve('vcs.url');

                if (substr($url, 0, strlen($projectUrl)) != $projectUrl) {
                    $err = "The project url {$projectUrl} does not match the VCS url {$url}\n";
                    $err .= "Maybe you need to relocate your working copy?";
                    throw new \UnexpectedValueException($err);
                }

                return ltrim(substr($url, strlen($projectUrl)), '/') . '@' . $rev;
            }
            return null;
        });
        $container->method('versionof', function($container, $dir) {
            if (is_file($revFile = ($dir . '/' . $container->resolve('vcs.export.revfile')))) {
                $info = file_get_contents($revFile);
            } elseif (is_dir($dir)) {
                $info = @shell_exec('svn info ' . $dir . ' 2>&1');
            } else {
                return null;
            }
            return $container->call('vcs.versionid', $info);
        });
        $container->method('vcs.diff', function($container, $left, $right, $verbose = false) {
            $left = $container->resolve('vcs.url') . '/' . $left;
            $right = $container->resolve('vcs.url') . '/' . $right;
            return sprintf('svn diff %s %s %s', $left, $right, ($verbose ? '' : '--summarize'));
        });
        $container->decl('vcs.current', function($container) {
            return $container->call('versionof', $container->resolve('cwd'));
        });
    }
}