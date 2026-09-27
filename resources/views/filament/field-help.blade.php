@php
    use Padmission\Tickets\TicketPlugin;

    $hint = fn (string $key): string => __("padmission-tickets::tickets.resources.tickets.hints.{$key}");
    $label = fn (string $key): string => __("padmission-tickets::tickets.resources.tickets.{$key}");
    $plugin = TicketPlugin::get();

    $fields = [
        $label('status') => $hint('status'),
        $label('priority') => $hint('priority'),
        $label('submitter') => $hint('submitter'),
        ...($plugin->getLinkedTicketParentPanels() === [] ? [] : [
            $label('handled_by') => TicketPlugin::teamText('padmission-tickets::tickets.resources.tickets.hints.handled_by', $plugin->getEscalationTargetName()),
        ]),
        ...($plugin->getLinkedTicketChildPanels() === [] ? [] : [
            $label('contact') => $hint('contact'),
        ]),
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
