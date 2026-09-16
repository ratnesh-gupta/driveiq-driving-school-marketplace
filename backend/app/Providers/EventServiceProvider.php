<?php

namespace App\Providers;

use App\Events\InquiryCreated;
use App\Listeners\SendInquiryCreatedNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        InquiryCreated::class => [
            SendInquiryCreatedNotification::class,
        ],
    ];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
