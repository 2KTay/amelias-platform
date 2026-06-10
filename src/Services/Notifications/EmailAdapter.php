<?php

declare(strict_types=1);

namespace Amelias\Services\Notifications;

use Amelias\Services\Settings;

/**
 * Email channel. Uses PHPMailer over SMTP/SendGrid when available + configured;
 * otherwise falls back to PHP mail(). Always sends a hand-written plain-text
 * part alongside HTML (deliverability), with the CAN-SPAM footer added by the
 * Notifier for marketing sends.
 */
final class EmailAdapter implements NotifierInterface
{
    public function channel(): string
    {
        return 'email';
    }

    public function isEnabled(): bool
    {
        // Email is the always-on fallback channel; enabled if we have a from addr.
        return (Settings::get('mail.from') ?? env('MAIL_FROM')) !== null;
    }

    public function send(string $to, string $subject, string $textBody, ?string $htmlBody = null): bool
    {
        $from = (string) (Settings::get('mail.from') ?? env('MAIL_FROM', 'hello@ameliasaz.com'));
        $fromName = (string) env('MAIL_FROM_NAME', config('name', "Amelia's by EAT"));
        $apiKey = Settings::get('mail.sendgrid_key');

        // Preferred: PHPMailer (SMTP/SendGrid) when installed and configured.
        if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class) && $apiKey) {
            try {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = 'smtp.sendgrid.net';
                $mail->SMTPAuth = true;
                $mail->Username = 'apikey';
                $mail->Password = $apiKey;
                $mail->Port = 587;
                $mail->setFrom($from, $fromName);
                $mail->addAddress($to);
                $mail->Subject = $subject;
                if ($htmlBody !== null) {
                    $mail->isHTML(true);
                    $mail->Body = $htmlBody;
                    $mail->AltBody = $textBody;
                } else {
                    $mail->Body = $textBody;
                }
                $mail->send();
                return true;
            } catch (\Throwable $e) {
                error_log('[email] PHPMailer failed: ' . $e->getMessage());
                return false;
            }
        }

        // Fallback: PHP mail(). On local/staging without a mailer this no-ops
        // gracefully (logged), so the rest of the flow never blocks on email.
        $headers = "From: {$fromName} <{$from}>\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        if ((config('env') ?? 'production') === 'production') {
            return @mail($to, $subject, $textBody, $headers);
        }
        error_log("[email:dev] to={$to} subject={$subject}");
        return true;
    }
}
