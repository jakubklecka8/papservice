<?php

namespace App\Services\Mail;

use App\Models\InboundEmail;
use App\Models\TicketMessageId;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Odpověď zákazníkovi s číslem požadavku – ve stejném vlákně (In-Reply-To/References),
 * přes SMTP schránky, ze které e-mail přišel. Naše Message-ID se uloží k zakázce,
 * takže odpověď na potvrzení se připojí ke správné zakázce.
 */
class ConfirmationSender
{
    public function send(InboundEmail $email): void
    {
        $email->loadMissing(['mailbox', 'ticket']);
        $mb = $email->mailbox;
        $ticket = $email->ticket;
        if (! $ticket || ! $email->from_email || ! $mb->canSend()) {
            return;
        }

        $lang = $email->ai_result['language'] ?? 'cs';
        $number = $ticket->number;
        $subject = (string) ($email->subject ?: ($lang === 'pl' ? 'Zgłoszenie serwisowe' : 'Servisní požadavek'));
        if (! preg_match('/^(re|odp|aw)\s*:/i', $subject)) {
            $subject = 'Re: '.$subject;
        }
        if (! str_contains($subject, $number)) {
            $subject .= ' ['.$number.']';
        }

        $body = $lang === 'pl'
            ? "Dzień dobry,\n\npotwierdzamy przyjęcie zgłoszenia. Numer zgłoszenia: {$number}.\n\nJeśli chcą Państwo coś uzupełnić (np. zdjęcia), wystarczy odpowiedzieć na tę wiadomość.\n\nPozdrawiamy\nPAP Service"
            : "Dobrý den,\n\npotvrzujeme přijetí požadavku. Číslo požadavku: {$number}.\n\nPokud chcete něco doplnit (např. fotky), stačí odpovědět na tento e-mail.\n\nS pozdravem\nPAP Service";

        $domain = Str::after((string) ($mb->from_address ?: $mb->smtp_username), '@') ?: 'servicepap.com';
        $ourId = 'pap-'.$ticket->id.'-'.Str::lower(Str::random(16)).'@'.$domain;
        $refs = array_values(array_unique(array_filter([...$email->referencedIds(), $email->message_id])));
        $strip = fn ($id) => trim($id, '<> ');

        $mailer = Mail::build([
            'transport' => config('pap.confirmation_transport', 'smtp'), // v testech 'array'
            'scheme' => $mb->smtp_encryption === 'ssl' ? 'smtps' : 'smtp',
            'host' => $mb->smtp_host,
            'port' => $mb->smtp_port ?: ($mb->smtp_encryption === 'ssl' ? 465 : 587),
            'username' => $mb->smtp_username,
            'password' => $mb->smtp_password,
            'timeout' => 30,
        ]);

        $mailer->raw($body, function (Message $m) use ($email, $mb, $subject, $ourId, $refs, $strip) {
            $m->to($email->from_email, $email->from_name)
                ->from($mb->from_address ?: $mb->smtp_username, $mb->from_name ?: 'PAP Service')
                ->subject($subject);
            $headers = $m->getSymfonyMessage()->getHeaders();
            $headers->remove('Message-ID');
            $headers->addIdHeader('Message-ID', $ourId);
            $headers->addIdHeader('In-Reply-To', $strip($email->message_id));
            $headers->addIdHeader('References', array_map($strip, $refs));
        });

        TicketMessageId::remember($email->ticket_id, '<'.$ourId.'>');
        $email->update(['confirmation_sent_at' => now(), 'confirmation_message_id' => '<'.$ourId.'>']);
    }
}
