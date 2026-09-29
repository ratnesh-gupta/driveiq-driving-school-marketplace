<?php

namespace Tests;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    /**
     * A school user belonging to a separate, real school — for isolation tests.
     * (A made-up school_id violates the users.school_id foreign key on Postgres.)
     */
    protected function otherSchoolUser(array $attributes = []): User
    {
        $school = School::create([
            'name' => 'Other School',
            'slug' => 'other-school-'.Str::lower(Str::random(6)),
        ]);

        return User::factory()->create(array_merge([
            'role' => 'school',
            'school_id' => $school->id,
        ], $attributes));
    }
}
