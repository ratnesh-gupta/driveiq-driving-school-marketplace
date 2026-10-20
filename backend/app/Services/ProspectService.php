<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Locality;
use App\Models\Prospect;
use App\Models\School;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** DIQ-1103: prospects, their CSV import and their unclaimed listings. */
class ProspectService
{
    /** CSV header (lower-case, trimmed) => prospect field. */
    private const HEADER_ALIASES = [
        'name' => 'name', 'school name' => 'name', 'business name' => 'name', 'school' => 'name', 'trainer name' => 'name',
        'type' => 'type', 'listing type' => 'type',
        'contact person' => 'contact_person', 'contact' => 'contact_person', 'owner' => 'contact_person', 'owner name' => 'contact_person',
        'phone' => 'phone', 'mobile' => 'phone', 'phone number' => 'phone', 'mobile number' => 'phone', 'whatsapp' => 'phone',
        'email' => 'email', 'email address' => 'email', 'e-mail' => 'email',
        'website' => 'website', 'url' => 'website', 'site' => 'website',
        'locality' => 'locality', 'area' => 'locality', 'neighbourhood' => 'locality', 'neighborhood' => 'locality',
        'address' => 'address', 'full address' => 'address',
        'latitude' => 'latitude', 'lat' => 'latitude',
        'longitude' => 'longitude', 'lng' => 'longitude', 'lon' => 'longitude', 'long' => 'longitude',
        'google place id' => 'google_place_id', 'place id' => 'google_place_id', 'place_id' => 'google_place_id', 'google_place_id' => 'google_place_id',
        'notes' => 'notes', 'note' => 'notes', 'comments' => 'notes',
    ];

    public const MAX_IMPORT_ROWS = 2000;

    /** @return array<string, mixed> validation rules shared by the form and the import */
    public static function rules(bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return [
            'type' => [$req, Rule::in(Prospect::TYPES)],
            'name' => [$req, 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'locality_id' => ['nullable', 'integer', 'exists:localities,id'],
            'address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'google_place_id' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Reads a CSV into rows ready to save, flagging invalid rows and
     * duplicates (of existing prospects or of earlier rows in the file).
     *
     * @return array{mapping: array<string, string>, rows: list<array>, error?: string}
     */
    public function parseCsv(string $path, string $defaultType): array
    {
        $handle = fopen($path, 'r');
        $header = $handle ? fgetcsv($handle, escape: '\\') : false;
        if (! $header) {
            return ['mapping' => [], 'rows' => [], 'error' => 'The file is empty.'];
        }

        $mapping = [];
        foreach ($header as $i => $label) {
            // Strip a UTF-8 byte order mark from the first header (Excel adds one).
            $key = mb_strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $label)));
            if (isset(self::HEADER_ALIASES[$key]) && ! in_array(self::HEADER_ALIASES[$key], $mapping, true)) {
                $mapping[$i] = self::HEADER_ALIASES[$key];
            }
        }
        if (! in_array('name', $mapping, true)) {
            fclose($handle);

            return ['mapping' => [], 'rows' => [], 'error' => 'No name column found. Add a column called "name" (or "school name").'];
        }

        $localities = Locality::all(['id', 'name', 'slug'])
            ->flatMap(fn ($l) => [mb_strtolower($l->name) => $l->id, mb_strtolower($l->slug) => $l->id]);

        $rows = [];
        $seen = ['phone' => [], 'email' => [], 'place' => []];
        $line = 1;
        while (($cells = fgetcsv($handle, escape: '\\')) !== false) {
            $line++;
            if ($cells === [null] || count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            if (count($rows) >= self::MAX_IMPORT_ROWS) {
                fclose($handle);

                return ['mapping' => $this->labelMapping($header, $mapping), 'rows' => $rows, 'error' => 'Import at most '.self::MAX_IMPORT_ROWS.' rows at a time.'];
            }

            $data = [];
            foreach ($mapping as $i => $field) {
                $value = trim((string) ($cells[$i] ?? ''));
                $data[$field] = $value === '' ? null : $value;
            }

            $warnings = [];
            $data['type'] = in_array(mb_strtolower((string) ($data['type'] ?? '')), Prospect::TYPES, true)
                ? mb_strtolower($data['type']) : $defaultType;
            if (! empty($data['locality'])) {
                $data['locality_id'] = $localities[mb_strtolower($data['locality'])] ?? null;
                if (! $data['locality_id']) {
                    $warnings[] = "Unknown locality \"{$data['locality']}\"";
                }
            }
            unset($data['locality']);

            $errors = Validator::make($data, self::rules())->errors()->all();
            $duplicate = $errors ? null : Prospect::findDuplicate($data['phone'] ?? null, $data['email'] ?? null, $data['google_place_id'] ?? null);

            $phone = Prospect::normalPhone($data['phone'] ?? null);
            $email = Prospect::normalEmail($data['email'] ?? null);
            $place = $data['google_place_id'] ?? null;
            $repeat = ($phone && isset($seen['phone'][$phone])) || ($email && isset($seen['email'][$email])) || ($place && isset($seen['place'][$place]));
            if (! $errors) {
                $phone && $seen['phone'][$phone] = true;
                $email && $seen['email'][$email] = true;
                $place && $seen['place'][$place] = true;
            }

            $rows[] = [
                'line' => $line,
                'data' => $data,
                'status' => $errors ? 'invalid' : ($duplicate ? 'duplicate' : ($repeat ? 'repeated' : 'new')),
                'duplicateOf' => $duplicate ? ['id' => $duplicate->id, 'name' => $duplicate->name] : null,
                'errors' => $errors,
                'warnings' => $warnings,
            ];
        }
        fclose($handle);

        return ['mapping' => $this->labelMapping($header, $mapping), 'rows' => $rows];
    }

    /** @return array{created: int, skipped: int} */
    public function import(array $rows, int $adminId): array
    {
        $created = 0;
        DB::transaction(function () use ($rows, $adminId, &$created) {
            foreach ($rows as $row) {
                if ($row['status'] !== 'new') {
                    continue;
                }
                Prospect::create([...$row['data'], 'source' => 'csv', 'owner_admin_id' => $adminId]);
                $created++;
            }
        });

        AuditLog::log('import', 'Prospect', null, [], ['created' => $created, 'rows' => count($rows)]);

        return ['created' => $created, 'skipped' => count($rows) - $created];
    }

    /** An unclaimed, hidden listing built from the prospect, ready to be claimed. */
    public function createListing(Prospect $prospect): School
    {
        return DB::transaction(function () use ($prospect) {
            $school = new School([
                'name' => $prospect->name,
                'slug' => app(ListingOnboarding::class)->slug($prospect->name),
                'phone' => $prospect->phone,
                'email' => $prospect->email,
                'address' => $prospect->address,
                'locality_id' => $prospect->locality_id,
                'latitude' => $prospect->latitude,
                'longitude' => $prospect->longitude,
                'google_place_id' => $prospect->google_place_id,
                'source' => in_array($prospect->source, ['ads', 'gbp'], true) ? $prospect->source : 'outreach',
            ]);
            $school->listing_type = $prospect->type;
            $school->listing_status = 'unclaimed';
            $school->save();

            $prospect->school()->associate($school)->save();
            AuditLog::log('create_unclaimed', 'School', $school->id, [], ['prospect_id' => $prospect->id], $school->id);

            return $school;
        });
    }

    /**
     * The person asked not to be contacted: stop all outreach and remove the
     * listing we built for them if they never claimed it.
     */
    public function markDoNotContact(Prospect $prospect, string $reason): void
    {
        DB::transaction(function () use ($prospect, $reason) {
            $school = $prospect->school;
            if ($school && $school->listing_status === 'unclaimed') {
                $prospect->school()->dissociate();
                $school->delete();
            }
            $prospect->stage = 'do_not_contact';
            $prospect->save();

            app(OutreachSuppression::class)->suppress($prospect->email, $prospect->phone, $reason);
            AuditLog::log('do_not_contact', 'Prospect', $prospect->id, [], ['reason' => $reason]);
        });
    }

    /** @return array<string, string> CSV header label => field */
    private function labelMapping(array $header, array $mapping): array
    {
        $out = [];
        foreach ($mapping as $i => $field) {
            $out[trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[$i]))] = $field;
        }

        return $out;
    }
}
