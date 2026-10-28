<section id="schedule-history" class="card mb-3 d-print-none">
<div class="card-header"><h2 class="card-title d-flex align-items-center gap-2"><i class="bi bi-clock-history" aria-hidden="true"></i>{{ __('Creation and edit history') }}</h2></div>
<div class="table-responsive"><table class="table table-bordered table-vcenter mb-0 schedule-history"><thead><tr><th>#</th><th>{{ __('Action') }}</th><th>{{ __('Performed by') }}</th><th>{{ __('Date and time') }}</th><th>{{ __('Changed fields') }}</th></tr></thead><tbody>
@forelse($history as $row)<tr><td>{{ \App\Support\Ui::digits($history->firstItem()+$loop->index) }}</td><td>{{ __($row['action']) }}</td><td>{{ $row['actor'] }}</td><td class="text-nowrap">{{ \App\Support\Ui::date($row['at'],'d M Y h:i A') }}</td><td>{{ $row['action']==='Created' ? __('Schedule created') : ($row['fields'] ? implode(', ',$row['fields']) : __('Saved without changes to displayed fields')) }}</td></tr>@empty<tr><td colspan="5" class="text-center text-secondary py-4">{{ __('No creation or edit history is available for this record.') }}</td></tr>@endforelse
</tbody></table></div><div class="card-footer">{{ $history->links() }}</div>
</section>
