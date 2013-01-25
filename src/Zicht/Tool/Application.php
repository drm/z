<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Tool;

use \Symfony\Component\Console\Application as BaseApplication;
use \Symfony\Component\Yaml\Yaml;
use \Symfony\Component\Config\FileLocator;
use \Symfony\Component\Config\Definition\Processor;
use \Symfony\Component\Console\Input\InputArgument;
use \Symfony\Component\Console\Input\InputOption;
use \Symfony\Component\Console\Command\Command;
use \Symfony\Component\Console\Input\InputInterface;
use \Symfony\Component\Console\Output\OutputInterface;
use \Symfony\Component\Console\Input\ArgvInput;

use \Zicht\Tool\Command as Cmd;
use \Zicht\Tool\Command\TaskCommand;
use \Zicht\Tool\Container\Configuration;
use \Zicht\Tool\Container\Container;
use \Zicht\Tool\Container\Flattener;
use \Zicht\Tool\Version;
use \Zicht\Tool\Container\Task;

/**
 * Z CLI Application
 */
class Application extends BaseApplication
{
    protected $config;
    protected $container;
    protected $tasks;

    /**
     * Constructor, initializes the application, container and the commands
     */
    public function __construct()
    {
        parent::__construct('z - The Zicht Tool', Version::VERSION);
    }


    public function run(InputInterface $input = null, OutputInterface $output = null)
    {
        if (null === $input) {
            $input = new ArgvInput();
        }
        if (null === $output) {
            $output = new Output\ConsoleOutput();
        }

        $container = $this->initContainer(
            $input->hasParameterOption(array('--verbose', '-v')),
            $input->hasParameterOption(array('--force', '-f')),
            $input->hasParameterOption(array('--explain'))
        );

        $container->output = $output;

        $this->add(new Cmd\DumpCommand());
        $this->add(new Cmd\InitCommand());

        /** @var $task \Zicht\Tool\Container\Task */
        foreach ($this->tasks as $name => $task) {
            // if a tasks is prefixed with an underscore, it is considered an internal task
            if (substr($name, 0, 1) !== '_') {
                $cmd = new TaskCommand(str_replace('.', ':', $name));
                $cmd->setContainer($container);
                foreach ($task->getArguments() as $var => $isRequired) {
                    $cmd->addArgument($var, $isRequired ? InputArgument::REQUIRED : InputArgument::OPTIONAL);
                }
                $cmd->addOption('explain', '', InputOption::VALUE_NONE, 'Explains the commands that are executed without executing them.');
                $cmd->addOption('force', 'f', InputOption::VALUE_NONE, 'Force execution of otherwise skipped tasks.');
                $cmd->setHelp($task->getHelp());
                $cmd->setDescription(preg_replace('/^([^\n]*).*/s', '$1', $task->getHelp()));
                $this->add($cmd);
            }
        }
//        $container['console_dialog_helper']= $this->getHelperSet()->get('dialog');

        return parent::run($input, $output);
    }


    /**
     * Initializes the container.
     *
     * @return Container
     *
     * @throws \UnexpectedValueException
     */
    public function initContainer($verbose, $force, $explain)
    {
        list($plugins, $config) = $this->getConfig();

        $compiler = new Flattener();
        $flattened = $compiler->flatten($config);
        $flattened += array(
            'verbose'     => (bool)$verbose,
            'force'       => (bool)$force,
            'explain'     => (bool)$explain,
            'interactive' => false
        );
        $z = new Container($flattened, $config);

        $buffer = new \Zicht\Tool\Script\Buffer();
        $this->tasks = array();
        foreach ($config['tasks'] as $name => $taskDef) {
            $task = new Task($taskDef, $name);
            $this->tasks[$name]= $task;
            $buffer->writeln(sprintf('$z->decl(%s, ', var_export('tasks.' . $name, true)));
            $task->compile($buffer);
            $buffer->writeln(');');
        }
        unset($config['tasks']);

        eval($buffer->getResult());
        foreach ($plugins as $plugin) {
            $plugin->setContainer($z);
        }

        return $z;
    }

    public function getConfig()
    {
        $zFileLocator  = new FileLocator(array(getcwd(), getenv('HOME') . '/.config/z/'));
        $pluginLocator = new FileLocator(__DIR__ . '/Resources/plugins');

        $loader = new FileLoader($pluginLocator);

        try {
            $zfiles = $zFileLocator->locate('z.yml', null, false);
        } catch (\InvalidArgumentException $e) {
            $zfiles = array();
        }
        foreach ($zfiles as $file) {
            $loader->load($file);
        }

        $pluginFiles = $loader->getPlugins();
        $plugins     = array();
        foreach ($pluginFiles as $name => $file) {
            require_once $file;
            $className = sprintf('Zicht\Tool\Plugin\%s\Plugin', ucfirst($name));
            $class     = new \ReflectionClass($className);
            if (!$class->implementsInterface('Zicht\Tool\PluginInterface')) {
                throw new \UnexpectedValueException("The class $className is not a 'Zicht\\Tool\\PluginInterface'");
            }
            $plugins[$name] = $class->newInstance();
        }

        $processor = new Processor();
        $config    = $processor->processConfiguration(
            new Configuration($plugins),
            $loader->getConfigs()
        );

        return array($plugins, $config);
    }
}