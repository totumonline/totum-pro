<?php


namespace totum\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use totum\common\configs\MultiTrait;
use totum\common\errorException;
use totum\common\TotumInstall;
use totum\common\User;
use totum\config\Conf;
use totum\config\Conf2;

class HostSchema extends Command
{
    protected function configure()
    {
        $this->setName('host-schema')
            ->setDescription('Get schema by host/main host for schema')
            ->addArgument('host', InputArgument::REQUIRED, 'Enter host')
            ->addArgument('schema', InputArgument::OPTIONAL, 'Enter schema');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $Conf = new Conf();

        if (is_callable([$Conf, 'setHostSchema'])) {
            if ($host = $input->getArgument('host')) {
                $Conf->setHostSchema($host);
            }elseif ($schema = $input->getArgument('schema')){
                $Conf->setHostSchema(null, $schema);
            }
        }
        if(empty($input->getArgument('host'))){
            $output->write($Conf->getMainHostName());
        }else{
            $output->write($Conf->getSchema());
        }
        return 0;
    }
}
