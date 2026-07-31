<?php

namespace App\Modules\NdiImport;

use Illuminate\Support\Facades\DB;

final class NdiImportReport
{
    /** @return array<string, mixed>|null */
    public function find(string $runId): ?array
    {
        $run = DB::table('import_runs')->where('id', $runId)->first();

        if ($run === null) {
            return null;
        }

        return [
            'run' => (array) $run,
            'artifacts' => DB::table('import_run_artifacts')
                ->join('source_artifacts', 'source_artifacts.id', '=', 'import_run_artifacts.source_artifact_id')
                ->where('import_run_id', $runId)
                ->orderBy('dataset_role')
                ->get([
                    'dataset_role',
                    'parser_version',
                    'source_artifacts.id',
                    'original_filename',
                    'storage_key',
                    'byte_size',
                    'sha256',
                    'received_at',
                ])
                ->map(fn (object $artifact): array => (array) $artifact)
                ->all(),
            'rejections' => DB::table('import_rejections')
                ->where('import_run_id', $runId)
                ->orderBy('id')
                ->get()
                ->map(fn (object $rejection): array => (array) $rejection)
                ->all(),
        ];
    }
}
