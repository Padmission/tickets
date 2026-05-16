@php
    use Illuminate\Support\Str;

    $kind = data_get($block, 'kind');
    $props = data_get($block, 'props', []);
@endphp

<div class="sf-block sf-block--{{ Str::kebab($kind) }}" wire:key="block-{{ $key ?? md5(json_encode($block)) }}">
    @switch($kind)
        @case('Lede')
            <p class="sf-lede">{{ $props['text'] ?? '' }}</p>
            @break

        @case('HeadlineBand')
            <div class="sf-headline">
                <div class="sf-headline__label">{{ $props['label'] ?? '' }}</div>
                <div class="sf-headline__value">{{ data_get($props, 'value.text', $props['value'] ?? '') }}</div>
                @if (filled($props['subtext'] ?? null))
                    <div class="sf-headline__sub">{{ $props['subtext'] }}</div>
                @endif
            </div>
            @break

        @case('KVBlock')
            <dl class="sf-kv">
                @foreach (($props['rows'] ?? []) as $row)
                    <div>
                        <dt>{{ $row['label'] ?? '' }}</dt>
                        <dd>@include('padmission-tickets::filament.livewire.support.inline', ['value' => $row['value'] ?? ''])</dd>
                    </div>
                @endforeach
            </dl>
            @break

        @case('DefinitionCard')
            <div class="sf-def">
                <div class="sf-def__term">{{ $props['term'] ?? '' }}</div>
                <div class="sf-def__body">{{ $props['definition'] ?? '' }}</div>
                @if (filled($props['related'] ?? []))
                    <div class="sf-chiprow">
                        @foreach ($props['related'] as $term)
                            <span class="sf-chip">{{ $term }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
            @break

        @case('StepList')
            <ol class="sf-steps">
                @foreach (($props['steps'] ?? []) as $step)
                    <li>
                        <strong>{{ $step['title'] ?? '' }}</strong>
                        <span>{{ $step['body'] ?? $step['text'] ?? '' }}</span>
                    </li>
                @endforeach
            </ol>
            @break

        @case('Callout')
            <div class="sf-callout sf-callout--{{ $props['tone'] ?? 'info' }}">
                <strong>{{ $props['title'] ?? Str::headline($props['tone'] ?? 'Note') }}</strong>
                <span>{{ $props['body'] ?? $props['text'] ?? '' }}</span>
            </div>
            @break

        @case('AuditTrail')
            <div class="sf-audit">
                @foreach (($props['entries'] ?? []) as $entry)
                    <div class="sf-audit__entry">
                        <div class="sf-audit__meta">
                            <span>{{ data_get($entry, 'person.name', $entry['person'] ?? 'Unknown') }}</span>
                            <span>{{ data_get($entry, 'date.text', $entry['date'] ?? '') }}</span>
                        </div>
                        <div class="sf-audit__body">{{ $entry['summary'] ?? '' }}</div>
                        @foreach (($entry['diffs'] ?? []) as $diff)
                            <div class="sf-diff"><span>{{ $diff['field'] ?? '' }}</span><del>{{ $diff['from'] ?? '' }}</del><ins>{{ $diff['to'] ?? '' }}</ins></div>
                        @endforeach
                    </div>
                @endforeach
            </div>
            @break

        @case('DiffRow')
            <div class="sf-diff"><span>{{ $props['field'] ?? '' }}</span><del>{{ $props['from'] ?? '' }}</del><ins>{{ $props['to'] ?? '' }}</ins></div>
            @break

        @case('SourceCitation')
            <div class="sf-source">
                <span>{{ $props['label'] ?? 'Source' }}</span>
                @if (filled($props['url'] ?? null))
                    <a href="{{ $props['url'] }}">{{ $props['title'] ?? $props['url'] }}</a>
                @else
                    <strong>{{ $props['title'] ?? $props['record'] ?? '' }}</strong>
                @endif
            </div>
            @break
    @endswitch
</div>
