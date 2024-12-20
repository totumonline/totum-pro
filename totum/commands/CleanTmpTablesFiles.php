<?php


namespace totum\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use totum\common\configs\MultiTrait;
use totum\config\Conf;

class CleanTmpTablesFiles extends Command
{
    protected function configure()
    {
        $this->setName('clean-tmp-tables-files')
            ->setDescription('Clean tmp tables files. Set in crontab one time in hour.');
        if (key_exists(MultiTrait::class, class_uses(Conf::class, false))) {
            $this->addOption('schema', 's', InputOption::VALUE_REQUIRED, 'Enter schema name', '');
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!class_exists(Conf::class)) {
            $output->writeln('ERROR: config class not found');
        }
        $Conf = new Conf();
        if (is_callable([$Conf, 'setHostSchema'])) {
            if ($schema = $input->getOption('schema')) {
                $Conf->setHostSchema(null, $schema);
            }
        }
        $dirs[] = $Conf->getFilesDir();
        $dirs[] = $Conf->getSecureFilesDir();

        $cleanTmpFilesinDir = function ($dir) use (&$cleanTmpFilesinDir) {
            if ($dh = opendir($dir)) {
                while (($file = readdir($dh)) !== false) {
                    if (is_file($fName = $dir . '/' . $file)) {
                        if (str_contains($file,
                            '!tmp!') && fileatime($fName) < time() - 3600) {
                            unlink($fName);
                        }
                    } elseif ($file !='.' && $file !='..' && is_dir($fName)) {
                        $cleanTmpFilesinDir($fName);
                    }
                }
                closedir($dh);
            }
        };


        foreach ($dirs as $dir) {
            if (is_dir($dir)) {
                $cleanTmpFilesinDir($dir);
            }
        }

        return 0;
    }
}
