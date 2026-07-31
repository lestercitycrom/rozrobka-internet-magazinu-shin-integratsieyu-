<?php

namespace App\Http\Controllers\Admin;

use App\Modules\NdiImport\NdiImportReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ImportRunController
{
    public function index(Request $request)
    {
        $filters = $request->validate(['status' => ['nullable', 'string', 'max:64'], 'q' => ['nullable', 'string', 'max:64']]);
        $runs = DB::table('import_runs')->join('suppliers', 'suppliers.id', '=', 'import_runs.supplier_id')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('import_runs.status', $status))
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->where('suppliers.name', 'like', "%{$q}%"))
            ->orderByDesc('import_runs.started_at')->paginate(20, ['import_runs.*', 'suppliers.name as supplier_name'])->withQueryString();

        return view('admin.imports.index', compact('runs', 'filters'));
    }

    public function show(string $runId, NdiImportReport $report)
    {
        $data = $report->find($runId);
        abort_if($data === null, 404);
        $data['changes'] = DB::table('entity_changes')->where('import_run_id', $runId)->selectRaw('operation, count(*) as total')->groupBy('operation')->orderBy('operation')->get();
        $data['supplier'] = DB::table('suppliers')->where('id', $data['run']['supplier_id'])->value('name');

        return view('admin.imports.show', $data);
    }
}
