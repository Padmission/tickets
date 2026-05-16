<div @class(['sf-status', 'sf-status--resolved' => $ticket->closed_at, 'sf-status--waiting' => ! $ticket->closed_at])>
    <span class="sf-status__dot"></span>
    <div>
        <div class="sf-status__title">{{ $ticket->closed_at ? 'Resolved' : 'Waiting on Padmission support' }}</div>
        <div class="sf-status__sub">2 business hours · Mon-Fri, 8am-6pm MT</div>
    </div>
</div>
