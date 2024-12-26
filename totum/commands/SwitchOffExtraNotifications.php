<?php


namespace totum\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use totum\common\configs\ConfParent;
use totum\common\configs\MultiTrait;
use totum\common\Services\Services;
use totum\common\sql\Sql;
use totum\config\Conf;

class SwitchOffExtraNotifications extends Command
{
    protected function configure()
    {
        $this->setName('switch-off-extra-notifications')
            ->setDescription('Set off status for older notifications more than max argument')
        ->addArgument('max', InputOption::VALUE_REQUIRED, 'Max limit for notifications');
        if (key_exists(MultiTrait::class, class_uses(Conf::class, false))) {
            $this->addArgument('schema', InputOption::VALUE_REQUIRED, 'Enter schema name');
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $Conf = new Conf();

        $max = $input->getArgument('max') ?? '';
        if (!ctype_digit($max)) {
            throw new \Exception('Argument max must be integer');
        }
        $max = (int)$max;
        if ($max < 1) {
            throw new \Exception('Argument max must be > 0');
        }
        $sql = $Conf->getSql(true, false);

        if (is_callable([$Conf, 'setHostSchema'])) {
            if ($schema = $input->getArgument('schema')) {
                $this->doSqlWorks($max, $schema, $sql);
            } else {
                foreach (array_unique(array_values(Conf::getSchemas())) as $schema) {
                    $this->doSqlWorks($max, $schema, $sql);
                }
            }
        }
       else{
            $schema = $Conf->getSchema(true);

        }


        return 0;
    }
    protected function doSqlWorks(int $max, string $schema, Sql $sql)
    {
        $sql->exec('WITH ranked_entries AS (
    SELECT
        id,
        ROW_NUMBER() OVER (PARTITION BY user_id->>\'v\' ORDER BY active_dt_from->>\'v\' DESC) AS rnk
    FROM
        "'.$schema.'".notifications
    WHERE active->>\'v\' = \'true\'
)
UPDATE "'.$schema.'".notifications set active = \'{"v":false}\'
WHERE id IN (
    SELECT id FROM ranked_entries WHERE rnk > '.$max.'
)');
    }
}
