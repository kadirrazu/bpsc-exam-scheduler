@extends('layouts.app')
@section('title',__('Exam Details'))
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><h1 class="page-title d-flex align-items-center gap-2"><i class="bi bi-calendar2-check" aria-hidden="true"></i>{{ __('Exam Details') }}</h1><div class="d-flex flex-wrap gap-2"><a class="btn btn-outline-secondary" href="{{ route('schedules.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> {{ __('Back') }}</a>@can('edit-schedules')<a class="btn btn-primary" href="{{ route('schedules.edit',$schedule) }}"><i class="bi bi-pencil-square" aria-hidden="true"></i> {{ __('Edit Schedule') }}</a>@endcan</div></div>
<section class="card exam-details-summary mb-3" aria-labelledby="exam-summary-title">
    <div class="card-body">
        <div class="exam-details-eyebrow">{{ __('Exam summary') }}</div>
        <h2 id="exam-summary-title" class="exam-details-post">{{ \App\Support\Ui::digits($schedule->post_name ?? $schedule->title) }}@if($schedule->ministry) <span class="exam-details-ministry">({{ \App\Support\Ui::digits($schedule->ministry) }})</span>@endif</h2>
        <div class="exam-details-meta">
            <div><i class="bi bi-building" aria-hidden="true"></i><span><small>{{ __('Unit') }}</small><strong class="exam-details-unit">{{ $schedule->unitLabel() }}</strong></span></div>
            <div><i class="bi bi-list-check" aria-hidden="true"></i><span><small>{{ __('Exam Type') }}</small><strong class="exam-details-type">{{ $schedule->typeLabel() }}</strong></span></div>
            <div><i class="bi bi-calendar3" aria-hidden="true"></i><span><small>{{ __('Exam Date') }}</small><strong>{{ \App\Support\Ui::date($schedule->exam_date,'d M Y (l)') }}</strong></span></div>
        </div>
    </div>
</section>
@php
    $advertisementNumber = (string) ($schedule->advertisement_number ?? '');
    $advertisementYear = $schedule->advertisement_year;
    $advertisementValue = $advertisementNumber !== ''
        ? \App\Support\Ui::digits($advertisementNumber).($advertisementYear !== null ? ' ('.\App\Support\Ui::digits($advertisementYear).')' : '')
        : ($advertisementYear !== null ? \App\Support\Ui::digits($advertisementYear) : '—');
    $detailRows = [
        'Ministry/Organization' => \App\Support\Ui::digits($schedule->ministry ?? '—'),
        'Advertisement Number & Year' => $advertisementValue,
        'Exam Date' => \App\Support\Ui::date($schedule->exam_date,'d M Y (l)'),
        'Time' => $schedule->timeLabel(),
    ];
    if ($schedule->isViva()) {
        $detailRows['Board Structure'] = $schedule->structureLabel() ?: '—';
    }
    $detailRows['Number of Vacant Posts'] = \App\Support\Ui::number($schedule->vacant_posts);
    $detailRows['Number of Candidates'] = \App\Support\Ui::number($schedule->candidate_count);
    $detailRows[$schedule->isViva() ? 'Number of Boards' : 'Number of Exam Centers (Non-Viva)'] = \App\Support\Ui::number($schedule->isViva() ? $schedule->board_count : $schedule->center_count);
    $detailRows['Status'] = __(config('scheduler.statuses.'.$schedule->status));
    $detailRows['Notes/Remarks'] = \App\Support\Ui::digits($schedule->notes ?? '—');
    $detailRows['Last Updated'] = \App\Support\Ui::date($schedule->updated_at,'d M Y h:i A');
@endphp
<div class="card exam-details-card mb-3">
    <dl class="exam-details-inline-meta">
        <div><dt>{{ __('Exam Type') }}</dt><dd>{{ $schedule->typeLabel() }}</dd></div>
        <div><dt>{{ __('Unit') }}</dt><dd>{{ $schedule->unitLabel() }}</dd></div>
        <div><dt>{{ __('Post Code') }}</dt><dd class="exam-details-code-value">{{ \App\Support\Ui::digits($schedule->reference ?? '—') }}</dd></div>
    </dl>
    <table class="table table-bordered table-vcenter mb-0 exam-details-table">
        <caption class="visually-hidden">{{ __('Exam Details') }}</caption>
        <tbody>
            <tr class="exam-details-post-row"><th scope="row">{{ __('Post Name & Grade') }}</th><td class="exam-details-value"><strong class="exam-details-post-value">{{ \App\Support\Ui::digits($schedule->post_name ?? $schedule->title) }}</strong>@if($schedule->post_grade !== null) <strong class="exam-details-grade-value">({{ __('Grade') }}: {{ \App\Support\Ui::digits($schedule->post_grade) }})</strong>@endif</td></tr>
            @foreach($detailRows as $label=>$value)<tr><th scope="row">{{ __($label) }}</th><td class="exam-details-value {{ $label==='Ministry/Organization' ? 'exam-details-ministry-value' : '' }} {{ $label==='Status' ? 'status-cell status-'.$schedule->status : '' }}"><strong>{{ $value }}</strong></td></tr>@endforeach
        </tbody>
    </table>
</div>
@can('manage-system')<form id="delete-schedule" method="post" action="{{ route('schedules.destroy',$schedule) }}" class="card card-body" data-confirm-delete data-confirm-message="{{ __('Delete this record? This action will be audited.') }}">@csrf @method('DELETE')<input type="hidden" name="version" value="{{ $schedule->version }}"><label for="confirmation" class="form-label"><i class="bi bi-exclamation-circle" aria-hidden="true"></i> {{ __('Type DELETE to delete this schedule. Audit history will remain.') }}</label><div class="d-flex flex-wrap gap-2"><input class="form-control w-auto" id="confirmation" name="confirmation" required pattern="DELETE" autocomplete="off"><button class="btn btn-outline-danger">{{ __('Delete Schedule') }}</button></div></form>@endcan
@endsection
