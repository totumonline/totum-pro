<?php

namespace totum\common\configs;

use PHPMailer\PHPMailer\PHPMailer;
use totum\common\Crypt;
use totum\common\sql\Sql;

trait ListUnsubscribeTrait
{

    protected function checkMailReceivers($to, &$hcopy)
    {
        $hcopy = (array)$hcopy;
        $emails = $hcopy;
        $emails[] = $to;

        /** @var Sql $Sql */
        $Sql = $this->getSql();

        $r = $Sql->getPrepared('select email->>\'v\' as email from ttm__list_unsubscribe where email->>\'v\' IN ('
            . str_repeat('?,', count($emails) - 1) . '?)');
        $r->execute($emails);
        foreach ($r->fetchAll(\PDO::FETCH_COLUMN) as $email) {
            if ($to === $email) {
                return false;
            } else {
                foreach ($hcopy as $i => $_e) {
                    if ($_e === $email) {
                        unset($hcopy[$i]);
                    }
                }
                $hcopy = array_values($hcopy);
            }
        }
        return true;
    }

    protected function addListUnsubscribeHeader(PHPMailer $mail, $to, $title, &$body)
    {

        $encriptedData = Crypt::getCrypted(
            json_encode([
                $to, substr($title, 0, 10)
            ], JSON_UNESCAPED_UNICODE),
            $this->getCryptSolt()
        );
        $mail->AddCustomHeader("List-Unsubscribe: <https://" . $this->getFullHostName() . "/unSubcribe.php?d=" . urlencode($encriptedData) . ">");

        $body .= '<div style="text-align: center; font-size: 10px; margin-top: 30px;"><a style="color: #ddd;" href="https://' . $this->getFullHostName() . '/unSubcribe.php?d=' . urlencode($encriptedData) . '">Unsubscribe</a></div>';
    }

    public function unsubscribe($encrypted): bool
    {
        if (!empty($encrypted)) {
            $decriptedData = Crypt::getDeCrypted(
                $encrypted,
                $this->getCryptSolt()
            );
            if ($decriptedData && $decriptedData = json_decode($decriptedData, true)) {
                list($email, $title) = $decriptedData;
                if (!$this->checkMailReceivers($email, [])) {
                    /** @var Sql $Sql */
                    $Sql = $this->getSql();

                    $r = $Sql->getPrepared('insert into ttm__list_unsubscribe (email, title) VALUES (?,?)');
                    $r->execute([$email, $title]);
                }
                return true;
            }

        }
        return false;
    }

}