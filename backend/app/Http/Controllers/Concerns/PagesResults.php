<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Paging for list endpoints (DIQ-1305, QA BUG-02). With ?page / ?perPage the
 * caller gets that page plus X-Total-Count, X-Page and X-Per-Page headers.
 * Without them, older callers (pickers, small widgets) still get a plain list,
 * capped so a big school can't produce a multi-megabyte response.
 * The body stays a plain array, like GET /schools.
 */
trait PagesResults
{
    public const DEFAULT_PER_PAGE = 25;

    public const MAX_PER_PAGE = 100;

    public const UNPAGED_CAP = 500;

    /** @return array{0: Collection, 1: array<string, string>} rows and response headers */
    protected function pageResults(Builder $query, Request $request): array
    {
        $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ]);

        if (! $request->filled('page') && ! $request->filled('perPage')) {
            return [$query->limit(self::UNPAGED_CAP)->get(), []];
        }

        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('perPage', self::DEFAULT_PER_PAGE);
        $total = (clone $query)->toBase()->getCountForPagination();
        $rows = $query->forPage($page, $perPage)->get();

        return [$rows, [
            'X-Total-Count' => (string) $total,
            'X-Page' => (string) $page,
            'X-Per-Page' => (string) $perPage,
        ]];
    }
}
