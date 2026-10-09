@if($schedule->isViva())
@foreach($schedule->structureLines() as $line){{ $line }}<br>@endforeach
@if($schedule->board_structure)<strong>{{ __('Total Boards') }}: {{ \App\Support\Ui::number($schedule->board_count) }}</strong>@else{{ \App\Support\Ui::number($schedule->board_count) }}@endif
@else—@endif
