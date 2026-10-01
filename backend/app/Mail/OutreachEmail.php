<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/**
 * One outreach email (DIQ-1105): a short personal-looking note, plain text
 * plus a minimal HTML copy, with one-click unsubscribe (RFC 8058) headers.
 */
class OutreachEmail extends Mailable
{
    public function __construct(
        public readonly string $subjectLine,
        public readonly string $body,
        public readonly ?string $unsubscribeUrl,
        public readonly ?string $unsubscribePageUrl,
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
        $paragraphs = collect(preg_split("/\n{2,}/", trim($this->body)))
            ->map(fn ($p) => '<p>'.nl2br(e($p)).'</p>')
            ->join('');
        // Links in the text become clickable; the text was escaped above.
        $paragraphs = preg_replace('~(https?://[^\s<]+)~', '<a href="$1">$1</a>', $paragraphs);

        return '<div style="font-family:Arial,sans-serif;font-size:15px;line-height:1.5;color:#222;max-width:560px">'
            .$paragraphs
            .'<p style="font-size:12px;color:#777;border-top:1px solid #eee;padding-top:8px">'.$this->footer(true).'</p></div>';
    }

    private function footer(bool $html): string
    {
        $address = config('outreach.postal_address');
        if (! $this->unsubscribePageUrl) {
            return $html ? e($address) : $address;
        }

        return $html
            ? e($address).'<br>Not interested? <a href="'.e($this->unsubscribePageUrl).'">Unsubscribe</a> and we will not write again.'
            : $address."\nNot interested? Unsubscribe and we will not write again: ".$this->unsubscribePageUrl;
    }
}
