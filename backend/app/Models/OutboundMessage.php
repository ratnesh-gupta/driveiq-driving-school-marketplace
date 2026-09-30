<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Log of WhatsApp / SMS attempts (DIQ-1001). Numbers are masked. */
class OutboundMessage extends Model
{
    protected $fillable = [
        'school_id', 'channel', 'template', 'to_masked', 'to_hash',
        'related_type', 'related_id', 'status', 'provider_message_id', 'error',
    ];
}
