<?php

namespace totum\common\configs;

use PHPMailer\PHPMailer\PHPMailer;
use totum\common\Auth;
use totum\common\Crypt;
use totum\common\sql\Sql;
use totum\common\Totum;

trait ListUnsubscribeTrait
{
    protected $checkEmailPrepared;
    protected array $listUnsubscribeSettings = [];

    protected function loadListUnsubscribeSettings()
    {
        if (!key_exists('enabled', $this->listUnsubscribeSettings)) {
            if (property_exists($this, 'SmtpData')) {
                if (key_exists('enablelistunsubscribe', $this->SmtpData)) {
                    if ($this->listUnsubscribeSettings['enabled'] = $this->SmtpData['enablelistunsubscribe']) {
                        $this->listUnsubscribeSettings['header'] = true;
                        $this->listUnsubscribeSettings['link'] = true;
                    }else{
                        $this->listUnsubscribeSettings['header'] = false;
                        $this->listUnsubscribeSettings['link'] = false;
                    }
                }
                $this->listUnsubscribeSettings['blockhiddencopy'] = $this->SmtpData['blockhiddencopy'] ?? false;
            }

            if (!key_exists('enabled', $this->listUnsubscribeSettings)) {
                /** @var Sql $Sql */
                $Sql = $this->getSql();
                $settings = json_decode($Sql->get('select header from tables where name->>\'v\' = \'ttm__list_unsubscribe\'')['header'], true);
               $this->listUnsubscribeSettings['enabled'] = $settings['h_enable']['v'] ?? false;
                $this->listUnsubscribeSettings['link'] = $settings['h_link']['v'] ?? false;
                $this->listUnsubscribeSettings['header'] = $settings['h_header']['v'] ?? false;
                $this->listUnsubscribeSettings['blockhiddencopy'] = false;
            }
        }
    }

    protected function checkMailReceivers($to, &$hcopy, $force = false)
    {
        if (!$force) {
            $this->loadListUnsubscribeSettings();

            if ($this->listUnsubscribeSettings['blockhiddencopy']) {
                $hcopy = [];
            }

            if (!$this->listUnsubscribeSettings['enabled']) {
                return true;
            }
        }


        $hcopy = (array)$hcopy;
        $emails = $hcopy;
        $emails[] = $to;

        /** @var Sql $Sql */
        $Sql = $this->getSql();

        $this->checkEmailPrepared = $this->checkEmailPrepared ?? $Sql->getPrepared('select email->>\'v\' as email from ttm__list_unsubscribe where email->>\'v\' = ? limit 1');

        foreach ($emails as $email) {
            $this->checkEmailPrepared->execute([$email]);
            if ($this->checkEmailPrepared->fetch(\PDO::FETCH_ASSOC)) {
                if ($to === $email) {
                    return false;
                } else {
                    foreach ($hcopy as $i => $_e) {
                        if ($_e === $email) {
                            unset($hcopy[$i]);
                            break;
                        }
                    }
                    $hcopy = array_values($hcopy);
                }
            }
        }
        return true;
    }

    protected function addListUnsubscribeHeader(PHPMailer $mail, $to, $title, &$body)
    {

        if ($this->listUnsubscribeSettings['link'] || $this->listUnsubscribeSettings['header']) {
            $encriptedData = Crypt::getCrypted(
                json_encode([
                    $to, substr($title, 0, 100)
                ], JSON_UNESCAPED_UNICODE),
                $this->getCryptSolt()
            );
            if ($this->listUnsubscribeSettings['header']) {
                $mail->AddCustomHeader("List-Unsubscribe: <https://" . $this->getFullHostName() . "/unSubcribe.php?d=" . urlencode($encriptedData) . "&ok=1>");
            }
            if ($this->listUnsubscribeSettings['link']) {
                if (!str_contains($body, '</body>')) {
                    $body = '<html><body>' . $body . '</body></html>';
                }
                $body = str_replace('</body>',
                    '<div style="text-align: center; font-size: 10px; margin-top: 30px;">' .
                    '<a style="color: #ddd;" href="https://' . $this->getFullHostName() . '/unSubcribe.php?d=' . urlencode($encriptedData) . '">' . $this->getLangObj()->translate('list-ubsubscribe-link-text') . '</a></div>',
                    $body);
            }
        }
    }

    public function unsubscribe($encrypted, $check): string|bool
    {
        if (!empty($encrypted)) {
            $decriptedData = Crypt::getDeCrypted(
                $encrypted,
                $this->getCryptSolt()
            );
            if ($decriptedData && $decriptedData = json_decode($decriptedData, true)) {

                list($email, $title) = $decriptedData;
                $ar = [];
                if ($this->checkMailReceivers($email, $ar, true)) {
                    if ($check) {
                        return true;
                    }
                    $Totum = new Totum($this, Auth::loadAuthUserByLogin($this, 'service', false));
                    $Totum->getTable('ttm__list_unsubscribe')->reCalculateFromOvers(['add' => [
                        ['email' => $email, 'title' => $title]
                    ]]);
                }
                if ($check) {
                    return "not in base";
                }
                return true;
            }

        }
        return false;
    }

}