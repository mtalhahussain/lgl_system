<form method="get" class="mb-3 d-print-none">
    <label for="start_date">From</label><input id="start_date" name="start_date" type="date" value="{{ request('start_date') }}">
    <label for="end_date">To</label><input id="end_date" name="end_date" type="date" value="{{ request('end_date') }}">
    <label for="batch_id">Batch</label><select id="batch_id" name="batch_id"><option value="">All batches</option>@foreach($batches as $batch)<option value="{{ $batch->id }}" @selected(request('batch_id') == $batch->id)>{{ $batch->name }}</option>@endforeach</select>
    <label for="course_id">Course</label><select id="course_id" name="course_id"><option value="">All courses</option>@foreach($courses as $course)<option value="{{ $course->id }}" @selected(request('course_id') == $course->id)>{{ $course->name }}</option>@endforeach</select>
    <button class="btn btn-primary">Apply</button>
    <button type="button" class="btn btn-outline-primary" onclick="window.print()">Print</button>
</form>
