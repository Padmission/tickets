@php
    use Padmission\Tickets\Enums\ActivitySender;
    use Padmission\Tickets\Enums\ActivityType;
    use Padmission\Tickets\Services\TicketActivityService;

    $activities = app(TicketActivityService::class)
        ->getActivities($this->record, view: 'supporter')
        ->reverse()
        ->values();
@endphp

<div class="sf-admin-thread" wire:poll.10s>
    @foreach ($activities as $activity)
        @if ($activity->type === ActivityType::Message && $activity->sender === ActivitySender::Ai)
            <div class="sf-admin-turn sf-admin-turn--ai">
                <div class="sf-aihead"><span class="sf-aihead__mark">AI</span><span>AI answer</span></div>
                <div class="sf-ai">
                    @foreach (data_get($activity->data, 'blocks', []) as $index => $block)
                        @include('padmission-tickets::filament.livewire.support.block', ['block' => $block, 'key' => "admin-{$activity->id}-{$index}"])
                    @endforeach
                </div>
            </div>
        @elseif ($activity->type === ActivityType::Message)
            <div class="sf-admin-turn">
                <div class="sf-human-who">{{ $activity->user_name }} <span class="sf-human-who__role">{{ $activity->sender->value }}</span></div>
                <div class="sf-bubble sf-bubble--human">{!! $activity->content !!}</div>
            </div>
        @else
            <div class="sf-event">
                <span class="sf-event__line"></span>
                <span class="sf-event__pill">{{ $activity->content }}</span>
                <span class="sf-event__line"></span>
            </div>
        @endif
    @endforeach
</div>
