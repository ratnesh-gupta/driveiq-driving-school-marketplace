<?php

namespace App\Events;

use App\Models\Inquiry;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** The school's first response to a lead (BSEC-08: unlocks the enquirer's review link). */
class InquiryResponded
{
    use Dispatchable, SerializesModels;

    public function __construct(public Inquiry $inquiry) {}
}
