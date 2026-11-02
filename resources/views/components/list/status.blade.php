@props(['value', 'label' => null])
@php
    $tone = match ((string) $value) {
        'active', 'paid', 'ongoing', 'present', 'Active' => 'ok',
        'due', 'partial', 'late', 'pending', 'upcoming', 'scheduled' => 'warn',
        'overdue', 'inactive', 'absent', 'Inactive' => 'bad',
        'cancelled', 'dropped', 'withdrawn', 'transferred', 'excused', 'reversed' => 'muted',
        default => 'info',
    };
@endphp
<span {{ $attributes->class(['status-pill', 'tone-'.$tone]) }}>{{ $label ?? str_replace('_', ' ', $value) }}</span>
