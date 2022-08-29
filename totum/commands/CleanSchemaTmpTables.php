<?php


namespace totum\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use totum\common\configs\MultiTrait;
use totum\config\Conf;

class CleanSchemaTmpTables extends Command
{
    protected function configure()
    {
        $this->setName('clean-schema-tmp-tables')
            ->setDescription('Clean tmp_tables in schemas. For single install. Set in crontab one time in 10 minutes.');
        if (key_exists(MultiTrait::class, class_uses(Conf::class, false))) {
            $this->addArgument('schema', InputOption::VALUE_REQUIRED, 'Enter schema name');
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $Conf = new Conf();

        if (is_callable([$Conf, 'setHostSchema'])) {
            if ($schema = $input->getArgument('schema')) {
                $Conf->setHostSchema(null, $schema);
            }
        }
        $sql = $Conf->getSql();
        $plus24 = date_create();
        $plus24->modify('-24 hours');
        $sql->exec('delete from _tmp_tables where touched<\'' . $plus24->format('Y-m-d H:i') . '\'');

        $minus10 = date_create();
        $minus10->modify('-30 minutes');
        $sql->exec('delete from _tmp_tables where table_name SIMILAR TO \'\_%\' AND touched<\''
            . $minus10->format('Y-m-d H:i') . '\'');

        $sql->exec('VACUUM _tmp_tables');
    }
}
