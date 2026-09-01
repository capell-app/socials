<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const string GENERATION_TABLE = 'social_site_preferences_table_generation';

    private const string MARKER_TABLE = 'social_site_preferences_share_semantics';

    public function up(): void
    {
        if (! Schema::hasTable('social_site_preferences')) {
            return;
        }

        $hadCustomisedColumn = Schema::hasColumn('social_site_preferences', 'share_networks_customised');
        $tableGeneration = $this->ensureGenerationTable();
        $this->ensureMarkerTable();

        if (! $hadCustomisedColumn) {
            $hasShareNetworkKeys = Schema::hasColumn('social_site_preferences', 'share_network_keys');

            Schema::table('social_site_preferences', function (Blueprint $table) use ($hasShareNetworkKeys): void {
                $column = $table->boolean('share_networks_customised')->default(false);

                if ($hasShareNetworkKeys) {
                    $column->after('share_network_keys');
                }
            });
        }

        $this->synchroniseSemantics($hadCustomisedColumn, $tableGeneration);
    }

    public function down(): void
    {
        if (! Schema::hasTable('social_site_preferences')) {
            return;
        }

        if (! Schema::hasColumn('social_site_preferences', 'share_networks_customised')) {
            return;
        }

        $tableGeneration = $this->ensureGenerationTable();
        $this->ensureMarkerTable();
        $this->snapshotCurrentSemantics($tableGeneration);

        Schema::table('social_site_preferences', function (Blueprint $table): void {
            $table->dropColumn('share_networks_customised');
        });
    }

    private function ensureMarkerTable(): void
    {
        $requiredColumns = ['preference_id', 'site_id', 'table_generation', 'row_fingerprint', 'customised'];

        if (Schema::hasTable(self::MARKER_TABLE)) {
            foreach ($requiredColumns as $column) {
                if (! Schema::hasColumn(self::MARKER_TABLE, $column)) {
                    Schema::drop(self::MARKER_TABLE);

                    break;
                }
            }
        }

        if (Schema::hasTable(self::MARKER_TABLE)) {
            return;
        }

        Schema::create(self::MARKER_TABLE, function (Blueprint $table): void {
            $table->unsignedBigInteger('preference_id')->primary();
            $table->unsignedBigInteger('site_id')->index();
            $table->uuid('table_generation')->index();
            $table->string('row_fingerprint', 64);
            $table->boolean('customised');
        });
    }

    private function ensureGenerationTable(): string
    {
        if (Schema::hasTable(self::GENERATION_TABLE)
            && (! Schema::hasColumn(self::GENERATION_TABLE, 'id') || ! Schema::hasColumn(self::GENERATION_TABLE, 'generation'))) {
            Schema::drop(self::GENERATION_TABLE);
        }

        if (! Schema::hasTable(self::GENERATION_TABLE)) {
            Schema::create(self::GENERATION_TABLE, function (Blueprint $table): void {
                $table->unsignedTinyInteger('id')->primary();
                $table->uuid('generation');
            });
        }

        $generation = DB::table(self::GENERATION_TABLE)->where('id', 1)->value('generation');

        if (is_string($generation) && $generation !== '') {
            return $generation;
        }

        $generation = (string) Str::uuid();
        DB::table(self::GENERATION_TABLE)->updateOrInsert(['id' => 1], ['generation' => $generation]);

        return $generation;
    }

    private function synchroniseSemantics(bool $hadCustomisedColumn, string $tableGeneration): void
    {
        if (! $this->hasPreferenceIdentityColumns()) {
            DB::table(self::MARKER_TABLE)->delete();

            return;
        }

        $currentPreferenceIds = [];

        foreach ($this->preferenceRows($hadCustomisedColumn) as $row) {
            $preferenceId = $this->integerRowValue($row, 'id');
            $siteId = $this->integerRowValue($row, 'site_id');
            $rowFingerprint = $this->rowFingerprint($row);
            $marker = DB::table(self::MARKER_TABLE)
                ->where('preference_id', $preferenceId)
                ->where('site_id', $siteId)
                ->where('table_generation', $tableGeneration)
                ->where('row_fingerprint', $rowFingerprint)
                ->first();
            $customised = $hadCustomisedColumn
                ? $this->booleanRowValue($row, 'share_networks_customised')
                : ($marker !== null ? $this->normaliseBoolean($this->rowValue($marker, 'customised')) : $this->hasShareNetworkKeys($row));

            DB::table('social_site_preferences')
                ->where('id', $preferenceId)
                ->update(['share_networks_customised' => $customised]);
            DB::table(self::MARKER_TABLE)->updateOrInsert(
                ['preference_id' => $preferenceId],
                [
                    'site_id' => $siteId,
                    'table_generation' => $tableGeneration,
                    'row_fingerprint' => $rowFingerprint,
                    'customised' => $customised,
                ],
            );

            $currentPreferenceIds[] = $preferenceId;
        }

        if ($currentPreferenceIds === []) {
            DB::table(self::MARKER_TABLE)->delete();

            return;
        }

        DB::table(self::MARKER_TABLE)->whereNotIn('preference_id', $currentPreferenceIds)->delete();
    }

    private function snapshotCurrentSemantics(string $tableGeneration): void
    {
        if (! $this->hasPreferenceIdentityColumns()) {
            DB::table(self::MARKER_TABLE)->delete();

            return;
        }

        foreach ($this->preferenceRows(withCustomisedColumn: true) as $row) {
            DB::table(self::MARKER_TABLE)->updateOrInsert(
                ['preference_id' => $this->integerRowValue($row, 'id')],
                [
                    'site_id' => $this->integerRowValue($row, 'site_id'),
                    'table_generation' => $tableGeneration,
                    'row_fingerprint' => $this->rowFingerprint($row),
                    'customised' => $this->booleanRowValue($row, 'share_networks_customised'),
                ],
            );
        }
    }

    /** @return iterable<int, object> */
    private function preferenceRows(bool $withCustomisedColumn): iterable
    {
        $columns = ['id', 'site_id', 'share_network_keys'];

        foreach (['created_at', 'updated_at'] as $timestampColumn) {
            if (Schema::hasColumn('social_site_preferences', $timestampColumn)) {
                $columns[] = $timestampColumn;
            }
        }

        if ($withCustomisedColumn) {
            $columns[] = 'share_networks_customised';
        }

        return DB::table('social_site_preferences')->select($columns)->orderBy('id')->get();
    }

    private function hasPreferenceIdentityColumns(): bool
    {
        return array_all(
            ['id', 'site_id', 'share_network_keys'],
            fn (string $column): bool => Schema::hasColumn('social_site_preferences', $column),
        );
    }

    private function rowFingerprint(object $row): string
    {
        return hash('sha256', json_encode([
            'site_id' => $this->integerRowValue($row, 'site_id'),
            'share_network_keys' => $this->decodedShareNetworkKeys($row),
            'created_at' => $this->rowValue($row, 'created_at'),
            'updated_at' => $this->rowValue($row, 'updated_at'),
        ], JSON_THROW_ON_ERROR));
    }

    private function hasShareNetworkKeys(object $row): bool
    {
        return $this->decodedShareNetworkKeys($row) !== [];
    }

    /** @return array<mixed> */
    private function decodedShareNetworkKeys(object $row): array
    {
        $rawKeys = $this->rowValue($row, 'share_network_keys');

        if (is_array($rawKeys)) {
            return $rawKeys;
        }

        if (! is_string($rawKeys)) {
            return [];
        }

        $keys = json_decode($rawKeys, true);

        return is_array($keys) ? $keys : [];
    }

    private function integerRowValue(object $row, string $column): int
    {
        $value = $this->rowValue($row, $column);

        return is_int($value) || is_string($value) ? (int) $value : 0;
    }

    private function booleanRowValue(object $row, string $column): bool
    {
        return $this->normaliseBoolean($this->rowValue($row, $column));
    }

    private function normaliseBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        if (is_string($value)) {
            return match (strtolower(trim($value))) {
                '1', 'true', 'yes', 'on' => true,
                '0', 'false', 'no', 'off', '' => false,
                default => throw new UnexpectedValueException(sprintf('Invalid persisted boolean value [%s].', $value)),
            };
        }

        throw new UnexpectedValueException(sprintf('Invalid persisted boolean type [%s].', get_debug_type($value)));
    }

    private function rowValue(object $row, string $column): mixed
    {
        return get_object_vars($row)[$column] ?? null;
    }
};
