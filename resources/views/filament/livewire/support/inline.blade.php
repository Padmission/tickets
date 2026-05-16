@if (is_array($value) && filled($value['kind'] ?? null))
    @switch($value['kind'])
        @case('MoneyValue')
            <span class="sf-money">{{ $value['text'] ?? $value['amount'] ?? '' }}</span>
            @break
        @case('DateChip')
            <span class="sf-chip">{{ $value['text'] ?? $value['date'] ?? '' }}</span>
            @break
        @case('PersonChip')
            <span class="sf-chip">{{ $value['name'] ?? $value['text'] ?? '' }}</span>
            @break
        @case('GlossaryChip')
            <span class="sf-chip sf-chip--glossary">{{ $value['term'] ?? $value['text'] ?? '' }}</span>
            @break
        @case('StatusPill')
            <span class="sf-statuspill">{{ $value['label'] ?? $value['text'] ?? '' }}</span>
            @break
        @default
            <span>{{ json_encode($value) }}</span>
    @endswitch
@else
    <span>{{ is_scalar($value) ? $value : json_encode($value) }}</span>
@endif
