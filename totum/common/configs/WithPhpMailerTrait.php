<?php


namespace totum\common\configs;

use PHPMailer\PHPMailer\PHPMailer;

trait WithPhpMailerTrait
{
    use ListUnsubscribeTrait;

    abstract protected function getDefaultSender();

    public function sendMail(array|string $to, $title, $body, $attachments = [], $from = null, $replyTo = null, $hcopy = null)
    {

        $this->loadListUnsubscribeSettings();

        if ($this->listUnsubscribeSettings['enabled'] && is_array($to)) {
            foreach ($to as $_to) {
                $this->sendMail($_to, $title, $body, $attachments, $from, $replyTo, $hcopy);
                if ($hcopy) {
                    $hcopy = null;
                }
            }
            return;
        }

        if (!$this->checkMailReceivers($to, $hcopy)) {
            return;
        }

        list($body, $attachments) = $this->mailBodyAttachments($body, $attachments);

        try {
            $mail = new PHPMailer(true);

            $this->addListUnsubscribeHeader($mail, $to, $title, $body);

            $mail->SMTPDebug = $this->env !== static::ENV_LEVELS['production'];
            $mail->CharSet = 'utf-8';


            if (($smtpData = $this->getSettings('custom_smtp_setings_for_schema')) && is_array($smtpData)) {
                $mail->isSMTP();
                $mail->Host = $smtpData['host'] ?? '';
                $mail->Port = $smtpData['port'] ?? '';

                if ($mail->SMTPAuth = !empty($smtpData['login'])) {
                    $mail->Username = $smtpData['login'];
                    $mail->Password = $smtpData['password'] ?? '';
                }
            } else {
                $mail->isSendmail();
            }


            $from = $from ?? $this->getDefaultSender();
            //Recipients
            $mail->setFrom($from, $from);
            foreach ((array)$to as $_to) {
                $mail->addAddress($_to);     // Add a recipient
            }

            if ($replyTo) {
                $mail->addReplyTo($replyTo);
            }
            if ($hcopy) {
                foreach ((array) $hcopy as $_h){
                    $mail->addBCC($_h);
                }
            }

            foreach ($attachments as $innrName => $fileString) {
                if (preg_match('/jpg|gif|png$/', $innrName)) {
                    $mail->addStringEmbeddedImage($fileString, $innrName, $innrName);
                } else {
                    $mail->addStringAttachment($fileString, $innrName);
                }
            }
            //Content
            $mail->isHTML(true);                                  // Set email format to HTML
            $mail->Subject = $title;
            $mail->Body = $body;
            try {
                return $mail->send();
            } catch (\Exception) {
                return $mail->send();
            }
        } catch (\Exception $e) {
            throw new \ErrorException($mail->ErrorInfo);
        }
    }

}
