<?php

namespace totum\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use totum\common\Auth;
use totum\common\calculates\CalculateAction;
use totum\common\configs\MultiTrait;
use totum\common\Totum;
use totum\common\User;
use totum\config\Conf;

class SchemaUsers extends Command
{
    protected function configure()
    {
        $this->setName('schema-users')
            ->setDescription('Schema users processes');

        if (key_exists(MultiTrait::class, class_uses(Conf::class, false))) {
            $this->addOption('schema', 's', InputOption::VALUE_REQUIRED, 'Enter schema name');
        }

        $this->addOption('list', '', InputOption::VALUE_OPTIONAL, 'List of on/off users. Params: on|off|all. Use ON for license information. Example: --list=on');
        $this->addOption('on', '', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, 'Login/email of users to switch on');
        $this->addOption('off', '', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, 'Login/email of users to switch off');

    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $Conf = new Conf();

        if (is_callable([$Conf, 'setHostSchema'])) {
            if ($schema = $input->getOption('schema')) {
                $Conf->setHostSchema(null, $schema);
            }
        }


        $onUsers = $input->getOption('on');
        $offUsers = $input->getOption('off');


        $sql = $Conf->getSql();
        
        if ($onUsers || $offUsers) {
            $Totum = new Totum($Conf, Auth::serviceUserStart($Conf));

            $Table = $Totum->getTable('users');

            $sql->transactionStart();

            $exec=function ($field, $vars, $status) use ($Table) {
                $Cals = new CalculateAction("=: setListExtended(table: 'users'; field: 'on_off' = $status; where: '$field' = $#vars; log: true)");

                $Cals->execAction("CODE",
                    $Table->getTbl()['params'],
                    $Table->getTbl()['params'],
                    $Table->getTbl(),
                    $Table->getTbl(),
                    $Table,
                    'exec',
                    [
                        'vars' => $vars,
                    ]);
            };

            if ($onUsers) {
                $login=[];
                $email=[];
                $status='true';
                foreach ($onUsers as $on) {
                    if (str_contains($on, '@')) {
                       $email[] = $on;
                    } else {
                        $login[] = $on;
                    }
                }

                if($login){
                    $exec('login', $login, $status);
                }
                if($email){
                    $exec('email', $email, $status);
                }
            }
            if ($offUsers) {
                $login=[];
                $email=[];
                $status='false';
                foreach ($offUsers as $off) {
                    if (str_contains($off, '@')) {
                        $email[] = $off;
                    } else {
                        $login[] = $off;
                    }
                }

                if($login){
                    $exec('login', $login, $status);
                }
                if($email){
                    $exec('email', $email, $status);
                }
            }

            try{
                $sql->transactionCommit();
            }catch (\Exception){}

            try {
                $Conf->proGoModuleSocketSend([], true, true);
            }catch (\Exception){}


        }


        if (!empty($list = $input->getOption('list'))){
            $table = new Table($output);
            $rows = [];
            $lightUsersCount = 0;
            $usersCount = 0;
            $rolesMap=[];
            $rolesPrepared=$Conf->getSql(true)->getPrepared
            ('select tables->>\'v\' as tables, tables_read->>\'v\' as tables_read from roles where is_del = false AND id = ANY (string_to_array(?, \',\')::int[])');
            $isLightUser = function (string $rolesString, string $login, string|null $connections)
            use ($Conf, &$rolesMap, $rolesPrepared, &$lightUsersCount, &$usersCount):array{
                $roles = implode(',', json_decode($rolesString, true));
                if (!key_exists($rolesString, $rolesMap)){
                    $rolesMap[$rolesString] = [];
                    if($roles && $rolesPrepared->execute([$roles])){
                        foreach ($rolesPrepared->fetchAll() as $row){
                            foreach (json_decode($row['tables']??'[]', true) as $t){
                                $rolesMap[$rolesString][$t]=true;
                            }
                            foreach (json_decode($row['tables_read']??'[]', true) as $t){
                                $rolesMap[$rolesString][$t]=true;
                            }
                        }
                    }

                }
                $connections = abs($connections?:1);

                if(in_array($login, ['admin', 'cron', 'service'])){
                    $usersCount += $connections - 1;
                    return [count($rolesMap[$rolesString]),""];
                }
                elseif(count($rolesMap[$rolesString])<=5){
                    $lightUsersCount += $connections;
                    return [count($rolesMap[$rolesString]),"+"];
                }else{
                    $usersCount += $connections;
                    return [count($rolesMap[$rolesString]), ""];
                }
                return ["-",''];
            };

            $where='';
            switch ($input->getOption('list')) {
                case 'on':
                    $where='AND on_off->>\'v\'=\'true\'  ';
                    break;
                case 'off':
                    $where='AND on_off->>\'v\'=\'false\'  ';
                    break;
            }
            foreach ($sql->getAll('select email->>\'v\' as email,  login->>\'v\' as login, fio->>\'v\' as fio,
                                                on_off->>\'v\' as on_off, roles->>\'v\' as roles,
                                                ttm__concurrent_connections->>\'v\' as connections
                                                from users 
                                                where  is_del = false '.$where.' order by login->>\'v\'') as $login) {

                list($tablesCount, $isLightUserSign) = $isLightUser(
                    $login['roles'],
                    $login['login'] ?? '',
                    $login['connections']
                );
                $rows[] = [
                    $login["login"],
                    $login["fio"],
                    $login['email'],
                    $login["on_off"] == 'true' ? 'ON' : 'OFF',
                    $login["connections"],
                    $tablesCount,
                    $isLightUserSign,
                ];
            }
            $rows[] = ["", '', '', "ALL:". count($rows), "FULL:$usersCount", "LIMIT:".$lightUsersCount, 'TOTAL:'.($usersCount+$lightUsersCount)];
            $table
                ->setHeaders(['Login', 'FIO', 'Email', 'Status', 'Licenses', 'Tables', 'Limit User'])
                ->setRows($rows);
            $table->render();
        }

        return 0;
    }

}