<?php

namespace totum\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use totum\common\configs\MultiTrait;
use totum\config\Conf;
use totum\moduls\MCP\MCPAuth;
use totum\moduls\MCP\MCPServer;

/**
 * MCP server over stdio: one JSON-RPC message per line in STDIN, answers per line in STDOUT.
 * Shell access = root access, so only login/email of the agent user is required (no password).
 */
class MCPServe extends Command
{
    protected function configure()
    {
        $this->setName('mcp')
            ->setDescription('MCP server (stdio) for agents: tables interface with rights of user with interface "agent"')
            ->addOption('user', 'u', InputOption::VALUE_REQUIRED, 'Login or email of the user with interface "agent"')
            ->addOption('creator', null, InputOption::VALUE_OPTIONAL,
                'Creator view (--creator=true): tools and instructions of the admin layer (tree, tables, fields); the user must have the role Creator', false);
        if (key_exists(MultiTrait::class, class_uses(Conf::class, false))) {
            $this->addOption('schema', 's', InputOption::VALUE_REQUIRED, 'Schema name');
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /* STDOUT is the protocol channel: everything else goes to STDERR */
        ini_set('display_errors', 'stderr');
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }

        $Conf = new Conf();
        if (is_callable([$Conf, 'setHostSchema']) && $input->hasOption('schema')) {
            $schema = $input->getOption('schema');
            if (!$schema && is_callable([$Conf, 'getSchemas']) && count($schemas = array_unique(array_values($Conf->getSchemas()))) === 1) {
                $schema = reset($schemas);
            }
            if (!$schema) {
                fwrite(STDERR, 'ERROR: set --schema' . PHP_EOL);
                return 1;
            }
            $Conf->setHostSchema(null, $schema);
        }

        $login = (string)$input->getOption('user');
        $creator = $input->getOption('creator') !== false && MCPAuth::isFlagOn($input->getOption('creator'));
        try {
            $User = MCPAuth::byLogin($Conf, $login);
            if ($creator) {
                MCPAuth::checkCreator($User);
            }
        } catch (\Exception $e) {
            fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
            return 1;
        }

        $Server = new MCPServer($Conf, fn(Conf $Config) => MCPAuth::byLogin($Config, $login), true, null, $creator);

        while (($line = fgets(STDIN)) !== false) {
            if (trim($line) === '') {
                continue;
            }
            ob_start();
            $answer = $Server->handlePayload($line);
            $garbage = ob_get_clean();
            if ($garbage !== '' && $garbage !== false) {
                fwrite(STDERR, $garbage . PHP_EOL);
            }
            if (!is_null($answer)) {
                fwrite(STDOUT, $answer . "\n");
                fflush(STDOUT);
            }
        }
        return 0;
    }
}
