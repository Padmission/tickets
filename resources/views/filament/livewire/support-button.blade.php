<button
    type="button"
    class="sf-topbtn"
    onclick="window.dispatchEvent(new CustomEvent('padmission-support-open'))"
    title="Support"
>
    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M12 3.75l1.52 4.7 4.93 1.55-4.93 1.55L12 16.25l-1.52-4.7L5.55 10l4.93-1.55L12 3.75Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
        <path d="M18.5 15.5l.58 1.78 1.87.59-1.87.58-.58 1.8-.58-1.8-1.87-.58 1.87-.59.58-1.78Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
    </svg>
    @if ($this->openTicketCount > 0)
        <span>{{ $this->openTicketCount }}</span>
    @endif
</button>
