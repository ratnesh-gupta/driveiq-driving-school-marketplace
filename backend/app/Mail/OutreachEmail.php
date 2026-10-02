<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/**
 * One outreach email (DIQ-1105, branded in DIQ-1203): a short note in the
 * campaign's language, a "your listing" card with one clear button, plain
 * text alongside, and one-click unsubscribe (RFC 8058) headers.
 */
class OutreachEmail extends Mailable
{
    /**
     * @param  array{name: string, place: string, type: string, link: string, claim: bool}|null  $card
     */
    public function __construct(
        public readonly string $subjectLine,
        public readonly string $body,
        public readonly ?string $unsubscribeUrl,
        public readonly ?string $unsubscribePageUrl,
        public readonly ?array $card = null,
        public readonly string $lang = 'en',
    ) {}

    public function envelope(): Envelope
    {
        $replyTo = config('outreach.reply_to');

        return new Envelope(
            from: new Address(config('outreach.from.address'), config('outreach.from.name')),
            replyTo: $replyTo ? [new Address($replyTo)] : [],
            subject: $this->subjectLine,
        );
    }

    public function headers(): Headers
    {
        if (! $this->unsubscribeUrl) {
            return new Headers;
        }

        return new Headers(text: [
            'List-Unsubscribe' => '<'.$this->unsubscribeUrl.'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->htmlBody(),
            text: 'emails.outreach-text',
            with: ['text' => $this->plainText()],
        );
    }

    public function plainText(): string
    {
        return $this->body."\n\n--\n".$this->footer(false);
    }

    public function htmlBody(): string
    {
        $copy = $this->copy();
        $paragraphs = collect(preg_split("/\n{2,}/", trim($this->body)))
            ->map(fn ($p) => '<p style="margin:0 0 14px">'.nl2br(e($p)).'</p>')
            // Links in the text become clickable; the text was escaped above.
            ->map(fn ($p) => preg_replace('~(https?://[^\s<]+)~', '<a href="$1" style="color:#2563eb">$1</a>', $p));

        $card = '';
        if ($this->card) {
            $c = $this->card;
            $status = $c['claim'] ? $copy['card_claim'] : $copy['card_signup'];
            $cta = $c['claim'] ? $copy['cta_claim'] : $copy['cta_signup'];
            $card = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:8px 0 20px;border:1px solid #e5e7eb;border-radius:12px">'
                .'<tr><td style="padding:16px 18px">'
                .'<div style="font-size:12px;color:#2563eb;font-weight:700;text-transform:uppercase;letter-spacing:.04em">DriveQ · '.e($c['type']).'</div>'
                .'<div style="font-size:18px;font-weight:700;color:#111827;margin-top:4px">'.e($c['name']).'</div>'
                .'<div style="font-size:14px;color:#6b7280;margin-top:2px">📍 '.e($c['place']).' · '.e($status).'</div>'
                .'<a href="'.e($c['link']).'" style="display:inline-block;margin-top:14px;background:#2563eb;color:#ffffff;text-decoration:none;font-weight:700;padding:12px 20px;border-radius:8px">'.e($cta).' →</a>'
                .'</td></tr></table>';
        }

        return '<!doctype html><html lang="'.e($this->lang).'"><body style="margin:0;padding:0;background:#f3f4f6">'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:24px 12px"><tr><td align="center">'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:580px;background:#ffffff;border-radius:14px;overflow:hidden">'
            .'<tr><td style="background:#2563eb;padding:16px 24px;font-family:Arial,sans-serif;font-size:22px;font-weight:800;color:#ffffff;letter-spacing:-.02em">Drive<span style="color:#facc15">Q</span></td></tr>'
            .'<tr><td style="padding:24px;font-family:Arial,\'Noto Sans Devanagari\',sans-serif;font-size:15px;line-height:1.6;color:#1f2937">'
            // The listing card sits just above the sign-off.
            .($paragraphs->count() > 1
                ? $paragraphs->slice(0, -1)->join('').$card.$paragraphs->last()
                : $paragraphs->join('').$card)
            .'</td></tr>'
            .'<tr><td style="padding:14px 24px;background:#f9fafb;font-family:Arial,sans-serif;font-size:12px;line-height:1.5;color:#6b7280">'.$this->footer(true).'</td></tr>'
            .'</table></td></tr></table></body></html>';
    }

    private function copy(): array
    {
        return config('outreach_copy.'.$this->lang) ?? config('outreach_copy.en');
    }

    private function footer(bool $html): string
    {
        $address = config('outreach.postal_address');
        $copy = $this->copy();
        if (! $this->unsubscribePageUrl) {
            return $html ? e($address) : $address;
        }

        return $html
            ? e($address).'<br>'.e($copy['unsubscribe']).' <a href="'.e($this->unsubscribePageUrl).'" style="color:#6b7280">'.e($copy['unsubscribe_link']).'</a>'
            : $address."\n".$copy['unsubscribe'].' '.$this->unsubscribePageUrl;
    }
}
