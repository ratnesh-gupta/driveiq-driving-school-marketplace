<?php

namespace App\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Validation rules for foreign IDs sent by a client. An ID is accepted only
 * when the row exists in the same school, so one school can never attach
 * another school's learner, package, trainer or vehicle to its records.
 */
class SchoolScopedIds
{
    public static function learner(int $schoolId): Exists
    {
        return self::in('learners', $schoolId);
    }

    public static function package(int $schoolId): Exists
    {
        return self::in('packages', $schoolId);
    }

    public static function instructor(int $schoolId): Exists
    {
        return self::in('instructors', $schoolId);
    }

    public static function vehicle(int $schoolId): Exists
    {
        return self::in('vehicles', $schoolId);
    }

    /** A session must also belong to the learner it is recorded against. */
    public static function learnerSession(int $schoolId, int $learnerId): Exists
    {
        return self::in('schedules', $schoolId)->where('learner_id', $learnerId);
    }

    private static function in(string $table, int $schoolId): Exists
    {
        return Rule::exists($table, 'id')->where('school_id', $schoolId);
    }
}
