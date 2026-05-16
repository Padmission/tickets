<div class="sf-escalate">
    <div class="sf-escalate__head">
        <span class="sf-escalate__eyebrow">Human support recommended</span>
        <span class="sf-escalate__sub">2 business hours</span>
    </div>
    <div class="sf-escalate__body">
        {{ __("padmission-tickets::copilot.escalation_offers.{$reason}", ['reason' => str_replace('_', ' ', $reason)]) }}
    </div>
    <div class="sf-escalate__actions">
        <button type="button" class="sf-btn sf-btn--primary" wire:click="openTicket">Open a ticket</button>
    </div>
</div>
