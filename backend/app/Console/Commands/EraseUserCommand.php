<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ErasureService;
use Illuminate\Console\Command;

/**
 * BSEC-13: operator tool to anonymise one user (DPDP erasure). Dry run unless
 * --execute. Normal path is the admin action on a verified data-subject request.
 */
class EraseUserCommand extends Command
{
    protected $signature = 'driveiq:erase-user {email : account e-mail} {--execute : Apply instead of only reporting}';

    protected $description = 'Anonymise a user and their personal data (billing and audit records stay); dry run by default';

    public function handle(ErasureService $erasure): int
    {
        $user = User::query()->withoutGlobalScopes()->whereRaw('lower(email) = ?', [strtolower($this->argument('email'))])->first();
        if (! $user) {
            $this->error('No such user.');

            return self::FAILURE;
        }

        $execute = (bool) $this->option('execute');
        $result = $erasure->erase($user, $execute);

        $this->info(match ($result['status']) {
            'blocked' => 'Blocked: '.implode(', ', $result['blockers']),
            'already_erased' => 'Already erased; nothing to do.',
            'dry_run' => 'Dry run (nothing changed; pass --execute).',
            default => 'Erased.',
        });
        if ($result['counts']) {
            $this->table(['Data', 'Rows'], collect($result['counts'])->map(fn ($n, $k) => [$k, $n])->values()->all());
        }

        return $result['status'] === 'blocked' ? self::FAILURE : self::SUCCESS;
    }
}
