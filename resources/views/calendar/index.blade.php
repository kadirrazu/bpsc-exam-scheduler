@extends('layouts.app')
@section('title',__('Calendar'))
@section('content')
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3"><h1 class="page-title d-flex align-items-center gap-2"><i class="bi bi-calendar-month" aria-hidden="true"></i> {{ __('Exam Calendar') }}</h1><a class="btn btn-outline-secondary" href="{{ route('schedules.index') }}">{{ __('Schedules') }}</a></div>
<form class="card card-body mb-3" method="get"><div class="row g-3"><div class="col-12 col-md-3"><label class="form-label" for="month"><i class="bi bi-calendar3" aria-hidden="true"></i> {{ __('Month') }}</label><input class="form-control" type="month" min="1900-01" max="9998-12" id="month" name="month" required value="{{ $month->format('Y-m') }}"></div><div class="col-12 col-md-3"><label class="form-label" for="exam_type">{{ __('Exam Type') }}</label><select class="form-select" id="exam_type" name="exam_type"><option value="">{{ __('All exam types') }}</option>@foreach(app(\App\Services\Scheduler\Catalog::class)->types() as $code=>$type)<option value="{{ $code }}" @selected(($filters['exam_type'] ?? '')===$code)>{{ $type['label'] }}</option>@endforeach</select></div><div class="col-12 col-md-3"><label class="form-label" for="unit">{{ __('Unit') }}</label><select class="form-select" id="unit" name="unit"><option value="">{{ __('All units') }}</option>@foreach(app(\App\Services\Scheduler\Catalog::class)->unitLabels() as $code=>$label)<option value="{{ $code }}" @selected(($filters['unit'] ?? '')===$code)>{{ $label }}</option>@endforeach</select></div><div class="col-12 col-md-3 align-self-end d-flex flex-wrap gap-2"><button class="btn btn-primary"><i class="bi bi-funnel" aria-hidden="true"></i> {{ __('Apply Filters') }}</button><a class="btn btn-outline-secondary" href="{{ route('calendar.index') }}">{{ __('This Month') }}</a></div></div></form>
<div class="card mb-4 bpsc-calendar-card">
    <div class="bpsc-calendar-toolbar">
        <div class="bpsc-calendar-month-nav">
            @if($month->year>1900 || $month->month>1)<a class="btn btn-outline-secondary btn-icon" aria-label="{{ __('Previous month') }}" href="{{ route('calendar.index',array_merge($navigation,['month'=>$month->subMonth()->format('Y-m')])) }}"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>@endif
            <h2 class="bpsc-calendar-month-title">{{ \App\Support\Ui::date($month,'F Y') }}</h2>
            @if($month->year<9998 || $month->month<12)<a class="btn btn-outline-secondary btn-icon" aria-label="{{ __('Next month') }}" href="{{ route('calendar.index',array_merge($navigation,['month'=>$month->addMonth()->format('Y-m')])) }}"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>@endif
        </div>
        <div class="bpsc-calendar-month-total">{{ __('Exam count') }}: <strong>{{ \App\Support\Ui::number($monthTotal) }}</strong></div>
    </div>
    <div class="card-body">
        <div class="bpsc-calendar-intro"><p class="text-secondary small mb-0">{{ __('Hover over a date for counts by exam type. Select a date to see its schedules below.') }}</p><div class="bpsc-calendar-legend"><span><i class="bpsc-legend-exam" aria-hidden="true"></i>{{ __('Exam day') }}</span><span><i class="bpsc-legend-empty" aria-hidden="true"></i>{{ __('No exam day') }}</span><span><i class="bpsc-legend-weekend" aria-hidden="true"></i>{{ __('Weekly holiday') }}</span></div></div>
        <p class="bpsc-calendar-scroll-hint"><i class="bi bi-arrow-left-right" aria-hidden="true"></i> {{ __('Scroll sideways to view the full calendar.') }}</p>
        <div class="bpsc-calendar-scroll" tabindex="0" role="region" aria-label="{{ __('Exam Calendar') }}">
            <div class="bpsc-calendar-grid">
                @for($i=0;$i<7;$i++)<div class="bpsc-calendar-weekday {{ $i>=5 ? 'bpsc-calendar-weekend' : '' }}">{{ \App\Support\Ui::date($month->startOfWeek(0)->addDays($i),'D') }}</div>@endfor
                @foreach($cells as $cell)
                @php($day=$cell['date'])
                <div data-calendar-cell class="bpsc-calendar-day {{ !$cell['in_month'] ? 'bpsc-calendar-outside' : '' }} {{ $cell['in_month'] && $cell['stats']['total']>0 ? 'bpsc-calendar-has-exams' : '' }} {{ in_array($day->dayOfWeek,[5,6],true) ? 'bpsc-calendar-weekend' : '' }} {{ $day->isToday() ? 'bpsc-calendar-today' : '' }} {{ $selectedDate===$day->toDateString() ? 'bpsc-calendar-selected' : '' }}">
                    @if($cell['in_month'])
                    <a class="bpsc-calendar-date" href="{{ route('calendar.index',array_merge($navigation,['month'=>$month->format('Y-m'),'date'=>$day->toDateString()])) }}#day-schedules" aria-describedby="stats-{{ $day->format('Ymd') }}" @if($selectedDate===$day->toDateString())aria-current="date"@endif>
                        <span class="bpsc-calendar-number">{{ \App\Support\Ui::digits($day->day) }}</span>
                        @if($cell['stats']['total']>0)
                        <span class="bpsc-calendar-count"><span>{{ __('Exam count') }}:</span><strong>{{ \App\Support\Ui::digits($cell['stats']['total']) }}</strong></span>
                        <span class="bpsc-calendar-types">
                            @foreach($cell['stats']['types'] as $type)<span class="bpsc-calendar-type"><span class="bpsc-calendar-type-name">{{ $type['label'] }}</span><strong>{{ \App\Support\Ui::digits($type['count']) }}</strong></span>@endforeach
                        </span>
                        @else<span class="bpsc-calendar-empty-label">{{ __('No exams') }}</span>@endif
                    </a>
                    <div class="bpsc-calendar-stats" id="stats-{{ $day->format('Ymd') }}" role="tooltip"><strong class="bpsc-calendar-tip-date">{{ \App\Support\Ui::date($day,'d M Y') }}</strong><div class="bpsc-calendar-tip-total">{{ __('Exam count') }}: <strong>{{ \App\Support\Ui::digits($cell['stats']['total']) }}</strong></div>@foreach($cell['stats']['types'] as $type)<div class="bpsc-calendar-tip-row"><span>{{ $type['label'] }}</span><strong>{{ \App\Support\Ui::digits($type['count']) }}</strong></div>@endforeach</div>
                    @else<span class="bpsc-calendar-number">{{ \App\Support\Ui::digits($day->day) }}</span>@endif
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
<section id="day-schedules" tabindex="-1"><h2 class="h3">{{ $selectedDate ? \App\Support\Ui::date($selectedDate,'d M Y (l)') : __('Select a date') }}</h2>
@if($selectedDate)<div class="d-flex flex-wrap gap-2 mb-3">@foreach(['xlsx'=>'Export XLSX','pdf'=>'Export PDF','print'=>'Print'] as $format=>$label)<a class="btn btn-outline-primary btn-sm" href="{{ route('schedules.export',array_merge($navigation,['format'=>$format,'date'=>$selectedDate,'scope'=>'week','display'=>'flat'])) }}" @if($format==='print')target="_blank" rel="noopener"@endif>{{ __($label) }}</a>@endforeach</div>@include('schedules.table')@else<p class="text-secondary">{{ __('Select a date to view its examination schedules.') }}</p>@endif
</section>
@endsection
