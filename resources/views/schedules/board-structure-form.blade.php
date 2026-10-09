<div class="col-12" data-board-structure @if(!$isViva) hidden @endif>
    <div class="border rounded p-3">
        <h3 class="h4">{{ __('Board Structure (Optional)') }}</h3>
        <p class="text-secondary small">{{ __('Structure calculates totals; you may edit Candidates and Boards manually afterwards.') }}</p>
        <div data-structure-rows>
            @php($structureRows = old('board_structure',$schedule->board_structure))
            @foreach(is_array($structureRows) && $structureRows ? $structureRows : [['candidates_per_board'=>'','boards'=>'']] as $row)
            <div class="row g-2 mb-2 align-items-end" data-structure-row>
                <div class="col-5"><label class="form-label"><span>{{ __('Candidates per board') }}</span><input class="form-control" data-structure-candidates name="board_structure[{{ $loop->index }}][candidates_per_board]" value="{{ is_array($row) && is_scalar($row['candidates_per_board'] ?? null) ? $row['candidates_per_board'] : '' }}" type="text" inputmode="numeric" pattern="[0-9০-৯]+" maxlength="8" @disabled(!$isViva)></label></div>
                <div class="col-5"><label class="form-label"><span>{{ __('Boards') }}</span><input class="form-control" data-structure-boards name="board_structure[{{ $loop->index }}][boards]" value="{{ is_array($row) && is_scalar($row['boards'] ?? null) ? $row['boards'] : '' }}" type="text" inputmode="numeric" pattern="[0-9০-৯]+" maxlength="6" @disabled(!$isViva)></label></div>
                <div class="col-2"><button class="btn btn-outline-danger mb-2" type="button" data-remove-structure title="{{ __('Remove row') }}" aria-label="{{ __('Remove row') }}" hidden><i class="bi bi-trash" aria-hidden="true"></i></button></div>
            </div>
            @endforeach
        </div>
        <button class="btn btn-outline-primary btn-sm" type="button" data-add-structure hidden><i class="bi bi-plus-circle" aria-hidden="true"></i> {{ __('Add structure row') }}</button>
        <noscript><p class="form-hint">{{ __('Enter one structure row, or fill Candidates and Boards manually.') }}</p></noscript>
    </div>
</div>
