<?php
/**
 * For licensing information, please see the LICENSE file accompanied with this file.
 *
 * @author Gerard van Helden <drm@melp.nl>
 * @copyright 2012 Gerard van Helden <http://melp.nl>
 */

namespace Zicht\Tool\Plugin\Env;

use \Zicht\Tool\Plugin as BasePlugin;
use Zicht\Tool\Container\Container;
use \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

class Plugin extends BasePlugin
{
    public function setContainer(Container $container)
    {
        $container->method('env.versionat', function($container, $env, $verbose = false) {
            if ($container->resolve('explain')) {
                $container->notice('env.versionat() is not explain-safe. Following commands may not be accurate');
            }
            $tmp = tempnam(sys_get_temp_dir(), 'z');
            $container->cmd(sprintf(
                'scp %s:%s/%s %s',
                $container->resolve('env.' . $env . '.ssh'),
                $container->resolve('env.' . $env . '.root'),
                $container->resolve('vcs.export.revfile'),
                $tmp
            ));
            $vcsInfo = file_get_contents($tmp);
            unlink($tmp);
            if ($verbose) {
                return $vcsInfo;
            } else {
                return $container->call('vcs.versionid', $vcsInfo);
            }
        });
        $container->decl('env.ssh.connectable', function($container) {
            return shell_exec(sprintf('ssh -oBatchMode=yes %s "echo 1" 2>/dev/null;', $container->resolve('env.ssh')));
        });
    }
}