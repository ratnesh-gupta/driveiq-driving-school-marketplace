<?php

namespace Database\Seeders\Concerns;

use RuntimeException;

/**
 * BSEC-16: demo seeders create accounts with the known password "password123".
 * They must never run against a production database, whatever the entry point
 * (db:seed, db:seed --class=..., driveiq:demo).
 */
trait RefusesProduction
{
    protected function refuseInProduction(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException(static::class.' creates demo data and known passwords; refusing to run in production.');
        }
    }
}
