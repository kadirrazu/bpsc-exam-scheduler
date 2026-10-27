<?php

namespace App\Http\Controllers;

use App\Models\ExamSchedule;
use App\Models\ExamType;
use App\Models\Unit;
use App\Models\User;
use App\Services\Scheduler\Catalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CatalogController extends Controller
{
    private function context(Request $r): array
    {
        Gate::authorize('manage-system');
        $isType = $r->routeIs('exam-types.*', 'api.exam-types.*');

        return ['isType' => $isType, 'model' => $isType ? ExamType::class : Unit::class, 'base' => $isType ? 'exam-types' : 'units', 'heading' => $isType ? 'Exam Types' : 'Units'];
    }

    public function index(Request $r)
    {
        $c = $this->context($r);
        $d = $r->validate(['search' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1']);
        $entries = $c['model']::when($d['search'] ?? null, fn ($q, $v) => $q->where('name', 'like', '%'.$v.'%')->orWhere('code', 'like', '%'.$v.'%'))->orderBy('sort_order')->orderBy('id')->paginate(25)->withQueryString();

        return $r->is('api/*') ? response()->json($entries) : view('catalog.index', $c + compact('entries'));
    }

    public function create(Request $r)
    {
        $c = $this->context($r);
        $entry = new $c['model'](['sort_order' => 100, 'is_viva' => false, 'for_exams' => true, 'for_users' => true]);

        return view('catalog.form', $c + compact('entry'));
    }

    public function store(Request $r)
    {
        $c = $this->context($r);
        $data = $this->data($r, $c);
        $data['code'] = ($c['isType'] ? 'exam_' : 'unit_').Str::lower(Str::random(20));
        $entry = DB::transaction(fn () => $c['model']::create($data)->refresh());
        app(Catalog::class)->clear();

        return $r->is('api/*') ? response()->json(['data' => $entry], 201) : redirect()->route($c['base'].'.index')->with('success', __('Entry created.'));
    }

    public function show(Request $r, int $entry)
    {
        $c = $this->context($r);

        return response()->json(['data' => $c['model']::findOrFail($entry)]);
    }

    public function edit(Request $r, int $entry)
    {
        $c = $this->context($r);
        $entry = $c['model']::findOrFail($entry);

        return view('catalog.form', $c + compact('entry'));
    }

    public function update(Request $r, int $entry)
    {
        $c = $this->context($r);
        $entry = $c['model']::findOrFail($entry);
        $data = $this->data($r, $c, $entry);
        $saved = DB::transaction(function () use ($c, $entry, $data) {
            $current = $c['model']::whereKey($entry->id)->lockForUpdate()->firstOrFail();
            abort_unless($current->version === (int) $data['version'], 409, __('This entry changed. Reload before saving.'));
            if ($c['isType'] && $current->is_viva !== (bool) $data['is_viva'] && ExamSchedule::withTrashed()->where('exam_type', $current->code)->exists()) {
                $this->used();
            }
            if (! $c['isType']) {
                if (! $data['for_exams'] && ExamSchedule::withTrashed()->where('unit', $current->code)->exists()) {
                    $this->used();
                }
                if (! $data['for_users'] && User::withTrashed()->where('unit', $current->code)->exists()) {
                    $this->used();
                }
            }
            unset($data['version']);
            $current->fill($data);
            $current->version++;
            $current->save();

            return $current;
        });
        app(Catalog::class)->clear();

        return $r->is('api/*') ? response()->json(['data' => $saved]) : redirect()->route($c['base'].'.index')->with('success', __('Entry updated.'));
    }

    public function destroy(Request $r, int $entry)
    {
        $c = $this->context($r);
        $data = $r->validate(['confirmation' => 'required|in:DELETE', 'version' => 'required|integer|min:1']);
        DB::transaction(function () use ($c, $entry, $data) {
            $current = $c['model']::whereKey($entry)->lockForUpdate()->firstOrFail();
            abort_unless($current->version === (int) $data['version'], 409, __('This entry changed. Reload before saving.'));
            if (ExamSchedule::withTrashed()->where($c['isType'] ? 'exam_type' : 'unit', $current->code)->exists()) {
                $this->used();
            }
            if (! $c['isType'] && User::withTrashed()->where('unit', $current->code)->exists()) {
                $this->used();
            }
            $current->delete();
        });
        app(Catalog::class)->clear();

        return $r->is('api/*') ? response()->noContent() : redirect()->route($c['base'].'.index')->with('success', __('Entry deleted.'));
    }

    private function used(): never
    {
        throw ValidationException::withMessages(['entry' => __('This entry is used by existing records. Its usage settings cannot be removed or changed.')]);
    }

    private function data(Request $r, array $c, $entry = null): array
    {
        $rules = ['name' => ['required', 'string', 'max:100', Rule::unique($c['isType'] ? 'exam_types' : 'units', 'name')->ignore($entry?->id)], 'sort_order' => 'required|integer|min:0|max:65535', 'code' => 'prohibited', 'id' => 'prohibited', 'version' => [$entry ? 'required' : 'prohibited', 'integer', 'min:1']];
        if ($c['isType']) {
            $rules += ['is_viva' => 'required|boolean', 'for_exams' => 'prohibited', 'for_users' => 'prohibited'];
        } else {
            $rules += ['for_exams' => 'required|boolean', 'for_users' => 'required|boolean', 'is_viva' => 'prohibited'];
        }
        $data = $r->validate($rules);
        if (! $c['isType'] && ! $data['for_exams'] && ! $data['for_users']) {
            throw ValidationException::withMessages(['for_exams' => __('Choose at least one usage.')]);
        }

        return $data;
    }
}
