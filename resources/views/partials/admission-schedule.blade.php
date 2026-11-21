<div class="row g-3 mt-2">
            <div class="col-12">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="custom_schedule" @checked(old('schedule'))>
                    <label class="form-check-label" for="custom_schedule">Use custom amounts and due dates instead</label>
                </div>
            </div>
        </div>
        <div id="schedule_rows" class="mt-3">
            <p class="form-section-note">Fill only needed rows. Amounts must add up to the fee after discount.</p>
            @for($index = 0; $index < 12; $index++)
                <div class="row g-3 mb-2">
                    <div class="col-md-6"><label class="form-label" for="amount_{{ $index }}">Installment {{ $index + 1 }} amount</label><input class="form-control" id="amount_{{ $index }}" name="schedule[{{ $index }}][amount]" type="number" min="0" step="0.01" value="{{ old('schedule.'.$index.'.amount') }}" disabled></div>
                    <div class="col-md-6"><label class="form-label" for="date_{{ $index }}">Due date</label><input class="form-control" id="date_{{ $index }}" name="schedule[{{ $index }}][due_date]" type="date" value="{{ old('schedule.'.$index.'.due_date') }}" disabled></div>
                </div>
            @endfor
        </div>
<template data-page-module="partials-admission-schedule"></template>

