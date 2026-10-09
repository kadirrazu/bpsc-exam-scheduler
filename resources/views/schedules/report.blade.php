<!doctype html><html lang="{{ app()->getLocale() }}"><head><meta charset="utf-8">@include('shared.favicon')<title>{{ __('Exam Schedules') }}</title><meta name="csrf-token" content="{{ csrf_token() }}"><style>
@page { size:A4 landscape; margin:14mm 12mm 18mm; }
@font-face {font-family:Nikosh;src:url('{{ asset('fonts/Nikosh.ttf') }}') format('truetype');}
body { font-family:{{ app()->getLocale()==='bn' ? 'Nikosh':'DejaVu Sans' }},Nikosh,Arial,sans-serif; font-size:9px; color:#111; }
h1 { font-size:16px; text-align:center; margin:0 0 5px; } h2 { font-size:12px; text-align:center; margin:0 0 5px; }
p { margin:5px 0 10px; text-align:center; } table { width:100%; border-collapse:collapse; table-layout:fixed; }
th,td { border:1px solid #555; padding:5px 3px; word-wrap:break-word; } th { background:#edf3f0; } thead { display:table-header-group; } tr { page-break-inside:avoid; } .center { text-align:center; vertical-align:middle; }
.credit { position:fixed; bottom:-10mm; left:0; font-size:7pt; color:#000; opacity:0.65; } .tools { margin:15px; text-align:center; }
@media print { .tools { display:none; } }
</style></head><body data-print-error="{{ __('Print request could not be recorded. Please try again.') }}">
@if($printing)<div class="tools"><button type="button" data-print data-audit-url="{{ route('schedules.print-audit') }}" data-report-token="{{ $reportToken ?? '' }}">{{ __('Print Schedule') }}</button></div>@endif
<h1>{{ __('Bangladesh Public Service Commission (BPSC)') }}</h1><h2>{{ __('Examination Schedule') }}</h2><p>@if($filters['from']){{ \App\Support\Ui::date($filters['from']) }} — {{ \App\Support\Ui::date($filters['to']) }}@else{{ __('All dates') }}@endif · {{ __('Bangladesh time (UTC+6)') }} · {{ __('Exams') }}: {{ \App\Support\Ui::number($schedules->count()) }}<br>{{ __('Filters:') }} {{ __(config('scheduler.types.'.($filters['exam_type'] ?? '').'.label','All exam types')) }} / {{ $filters['unit'] ?? __('All units') }} / {{ __(config('scheduler.statuses.'.($filters['status'] ?? ''),'All statuses')) }}@if(!empty($filters['search'])) / {{ __('Search:') }} {{ $filters['search'] }}@endif</p>
@php($dateSpans=$schedules->groupBy(fn($s)=>$s->exam_date->format('Y-m-d'))->map->count())
@php($previousDate=null)
@php($dateSerial=0)
<table><thead><tr>@if(($filters['display'] ?? 'flat')==='grouped')<th style="width:10%">{{ __('Date') }}</th><th style="width:3%">#</th>@else<th style="width:3%">#</th><th style="width:10%">{{ __('Date') }}</th>@endif<th style="width:16%">{{ __('Unit / Exam Type') }}</th><th style="width:26%">{{ __('Post Name') }}</th><th style="width:13%">{{ __('Time') }}</th><th style="width:8%">{{ __('Candidates') }}</th><th style="width:6%">{{ __('Centers') }}</th><th style="width:8%">{{ __('Boards') }}</th><th style="width:10%">{{ __('Status') }}</th></tr></thead><tbody>
@forelse($schedules as $s)@php($dateKey=$s->exam_date->format('Y-m-d'))@php($dateSerial=$previousDate===$dateKey ? $dateSerial+1 : 1)<tr>@if(($filters['display'] ?? 'flat')==='flat')<td class="center">{{ \App\Support\Ui::digits($loop->iteration) }}</td>@endif
@if(($filters['display'] ?? 'flat')==='flat' || $previousDate!==$dateKey)<td class="center" rowspan="{{ ($filters['display'] ?? 'flat')==='grouped' ? $dateSpans[$dateKey] : 1 }}">{{ \App\Support\Ui::date($s->exam_date,'d M Y') }}<br>{{ \App\Support\Ui::date($s->exam_date,'l') }}</td>@endif
@if(($filters['display'] ?? 'flat')==='grouped')<td class="center">{{ \App\Support\Ui::digits($dateSerial) }}</td>@endif
@php($previousDate=$dateKey)
<td class="center">@include('schedules.unit-type-cell',['schedule'=>$s])</td><td><strong>{{ \App\Support\Ui::digits($s->post_name ?? $s->title) }}</strong>@if($s->post_grade !== null)<br>{{ __('Post Grade') }}: {{ \App\Support\Ui::digits($s->post_grade) }}@endif @if($s->vacant_posts !== null)<br>{{ __('Number of Vacant Posts') }}: {{ \App\Support\Ui::digits($s->vacant_posts) }}@endif @if($s->ministry)<br>{{ \App\Support\Ui::digits($s->ministry) }}@endif @if($s->reference)<br>{{ \App\Support\Ui::digits($s->reference) }}@endif @if($s->advertisement_number || $s->advertisement_year)<br>{{ __('Advertisement') }}: {{ \App\Support\Ui::digits($s->advertisement_number) }} @if($s->advertisement_year)({{ \App\Support\Ui::digits($s->advertisement_year) }})@endif @endif </td>
<td class="center">{{ $s->timeLabel() }}</td><td class="center">{{ \App\Support\Ui::number($s->candidate_count) }}</td><td class="center">{{ $s->isViva() ? '—' : \App\Support\Ui::number($s->center_count) }}</td><td class="center">@include('schedules.board-cell',['schedule'=>$s])</td><td class="center" style="background-color:#{{ config('scheduler.status_colors.'.$s->status,'F1F3F5') }};font-weight:bold">{{ __(config('scheduler.statuses.'.$s->status)) }}</td></tr>
@empty<tr><td colspan="9" class="center">{{ __('No exams match these filters.') }}</td></tr>@endforelse
</tbody></table>
<div class="credit">Software Developed By: <strong>IT Section, BPSC</strong> · {{ __('Printed:') }} {{ \App\Support\Ui::date(now(),'d M Y h:i A') }}</div>
@if($printing)@vite(['resources/js/app.js'])@endif
</body></html>
