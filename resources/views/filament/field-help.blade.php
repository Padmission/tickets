@php
    $hint = fn (string $key): string => __("padmission-tickets::tickets.resources.tickets.hints.{$key}");
    $label = fn (string $key): string => __("padmission-tickets::tickets.resources.tickets.{$key}");

    $fields = [
        $label('status') => $hint('status'),
        $label('priority') => $hint('priority'),
        $label('submitter') => $hint('submitter'),
        $label('escalated_by') => $hint('escalated_by'),
        $label('assignee') => $hint('assignee'),
        $label('turn') => $hint('turn'),
        $label('disposition') => $hint('disposition'),
    ];
@endphp

<dl class="pad-ti-field-help">
    @foreach ($fields as $term => $definition)
        <div>
            <dt>{{ $term }}</dt>
            <dd>{{ $definition }}</dd>
        </div>
    @endforeach
</dl>
