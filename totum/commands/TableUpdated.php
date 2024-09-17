<?php

namespace totum\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use totum\common\Auth;
use totum\common\configs\MultiTrait;
use totum\common\Totum;
use totum\config\Conf;

class TableUpdated extends Command
{
    protected function configure()
    {
        $this->setName('table-updated')
            ->setDescription('Get table updated')
            ->addArgument(
                'table',
                InputArgument::REQUIRED,
                'table id, id/cycle, id/hash/userId '
            );
        $this->addOption('schema', 's', InputOption::VALUE_OPTIONAL, 'Enter schema name to execute code');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tableData = explode('/', $tableString = $input->getArgument('table'));

        $Conf = new Conf();

        if (is_callable([$Conf, 'setHostSchema'])) {
            if ($schema = $input->getArgument('schema')) {
                $Conf->setHostSchema(null, $schema);
            }
        }

        switch (count($tableData)) {
            case 3:
                $User = Auth::getUserById($Conf, $tableData[2]);
                $Totum = new Totum($Conf, $User);
                $Table = $Totum->getTable($tableData[0], $tableData[1]);
                break;
            case 2:
                $User = Auth::loadAuthUserByLogin($Conf, 'service', false);
                $Totum = new Totum($Conf, $User);
                $Table = $Totum->getTable($tableData[0], $tableData[1]);
                break;
            default:
                $User = Auth::loadAuthUserByLogin($Conf, 'service', false);
                $Totum = new Totum($Conf, $User);
                $Table = $Totum->getTable($tableData[0]);
        }

        $json = json_decode($Table->getUpdated(), true);

        if (ctype_digit($tableString)) {
            $tableString .= '/0';
        }
        $json["code"] = "{$json["code"]}";

        $data = [$tableString => $json];

        $output->write(json_encode($data));

        return 0;
    }
}