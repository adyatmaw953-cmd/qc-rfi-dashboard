<?php

namespace App\Console\Commands;

use App\Contracts\VsmClient;
use App\Models\Project;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;
use UnexpectedValueException;

#[Signature('vsm:sync-projects')]
#[Description('Sinkronkan data proyek dari VSM')]
class SyncVsmProjects extends Command
{
    public function handle(VsmClient $vsmClient): int
    {
        try {
            $projects = $this->normaliseProjects($vsmClient->projects());

            if ($projects === []) {
                $this->info('Tidak ada proyek dari VSM untuk disinkronkan.');

                return self::SUCCESS;
            }

            DB::transaction(function () use ($projects): void {
                Project::query()->upsert(
                    $projects,
                    ['external_id'],
                    ['project_code', 'project_name', 'customer', 'synced_at', 'updated_at'],
                );
            });

            $this->info('Berhasil menyinkronkan '.count($projects).' proyek dari VSM.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Gagal menyinkronkan proyek dari VSM: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @param  array<int, mixed>  $projects
     * @return array<int, array{external_id: int, project_code: string, project_name: string, customer: ?string, synced_at: Carbon, created_at: Carbon, updated_at: Carbon}>
     */
    private function normaliseProjects(array $projects): array
    {
        if (! array_is_list($projects)) {
            throw new UnexpectedValueException('VSM projects response must be a JSON list.');
        }

        $syncedAt = now();

        return array_map(function (mixed $project) use ($syncedAt): array {
            if (! is_array($project)) {
                throw new UnexpectedValueException('Each VSM project must be an object.');
            }

            $externalId = $project['external_id'] ?? $project['id'] ?? null;
            if ((! is_int($externalId) && ! (is_string($externalId) && ctype_digit($externalId)))
                || (int) $externalId < 0) {
                throw new UnexpectedValueException('Each VSM project must have a numeric external_id.');
            }

            if (! is_string($project['project_code'] ?? null) || $project['project_code'] === ''
                || ! is_string($project['project_name'] ?? null) || $project['project_name'] === '') {
                throw new UnexpectedValueException('Each VSM project must have a project_code and project_name.');
            }

            $customer = $project['customer'] ?? null;
            if ($customer !== null && ! is_string($customer)) {
                throw new UnexpectedValueException('A VSM project customer must be a string or null.');
            }

            return [
                'external_id' => (int) $externalId,
                'project_code' => $project['project_code'],
                'project_name' => $project['project_name'],
                'customer' => $customer,
                'synced_at' => $syncedAt,
                'created_at' => $syncedAt,
                'updated_at' => $syncedAt,
            ];
        }, $projects);
    }
}
