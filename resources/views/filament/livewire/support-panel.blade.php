@php
    use Filament\Support\Facades\FilamentAsset;
    use Padmission\Tickets\Enums\ActivitySender;
    use Padmission\Tickets\Enums\ActivityType;
@endphp
@php
    $isHumanTicket = $activeTicket && $activeTicket->status?->seed_key !== 'ai_in_progress';
    $isResolvedTicket = $activeTicket && $activeTicket->closed_at;
    $recordContext = $currentRecordContext ?? [];
@endphp

<div
    x-data="{
        open: false,
        openPanel() {
            this.open = true;
            this.$wire.setCurrentContext(this.detectRecordContext());
        },
        detectRecordContext() {
            if (window.PadmissionSupportRecordContext) {
                return window.PadmissionSupportRecordContext;
            }

            const path = window.location.pathname;
            const title = document.title || 'Journey';
            const patterns = [
                { type: 'household', label: 'Household', regex: /\/households\/(\d+)/ },
                { type: 'tenancy', label: 'Tenancy', regex: /\/tenancies\/(\d+)/ },
                { type: 'rfta', label: 'RFTA', regex: /\/rftas\/(\d+)/ },
                { type: 'housing_search', label: 'Housing Search', regex: /\/housing-searches\/(\d+)/ },
                { type: 'voucher', label: 'Voucher', regex: /\/vouchers\/(\d+)/ },
                { type: 'transaction', label: 'Transaction', regex: /\/transactions\/(\d+)/ },
            ];

            for (const pattern of patterns) {
                const match = path.match(pattern.regex);

                if (match) {
                    return {
                        type: pattern.type,
                        id: match[1],
                        label: `${pattern.label} #${match[1]}`,
                        subtitle: title.replace(' - Padmission Journey', ''),
                        url: path,
                    };
                }
            }

            return {};
        },
    }"
    x-on:padmission-support-open.window="openPanel()"
    x-on:padmission-support-close.window="open = false"
    x-on:padmission-support-stream.window="window.PadmissionSupportStream?.start($event.detail.payload)"
    x-show="open"
    x-cloak
    class="sf-shell"
>
    <div class="sf-backdrop" x-on:click="open = false"></div>

    <aside class="sf-panel" role="dialog" aria-label="Support">
        <header class="sf-head">
            <div>
                <div class="sf-eyebrow">Padmission support</div>
                <h2>Ask AI or open a ticket</h2>
            </div>
            <button type="button" class="sf-iconbtn" x-on:click="open = false" aria-label="Close support panel">
                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            </button>
        </header>

        <nav class="sf-tabs" aria-label="Support tabs">
            <button type="button" @class(['is-active' => $activeTab === 'ask']) wire:click="$set('activeTab', 'ask')">Ask AI</button>
            <button type="button" @class(['is-active' => $activeTab === 'history']) wire:click="$set('activeTab', 'history')">AI history ({{ $counts['conversations'] }})</button>
            <button type="button" @class(['is-active' => $activeTab === 'tickets']) wire:click="$set('activeTab', 'tickets')">Tickets ({{ $counts['open'] }})</button>
        </nav>

        @if ($activeTab === 'ask')
            <section class="sf-chat">
                @if ($recordContext || $editingRecordContext)
                    <div class="sf-record">
                        @if ($editingRecordContext)
                            <div class="sf-record__edit">
                                <label>
                                    <span>Record type</span>
                                    <select wire:model="recordTypeInput">
                                        @foreach ($recordContextOptions as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label>
                                    <span>Record ID</span>
                                    <input type="text" wire:model="recordIdInput">
                                </label>
                                <button type="button" class="sf-btn sf-btn--primary" wire:click="applyRecordContext">Apply</button>
                                <button type="button" class="sf-btn" wire:click="clearRecordContext">Clear</button>
                            </div>
                        @else
                            <div class="sf-record__label">Discussing</div>
                            <div class="sf-record__body">
                                <div class="sf-record__mark">{{ data_get($recordContext, 'mark') ?: strtoupper(substr((string) data_get($recordContext, 'type', 'AI'), 0, 2)) }}</div>
                                <div class="sf-record__main">
                                    <div class="sf-record__title">{{ data_get($recordContext, 'label') }}</div>
                                    <div class="sf-record__sub">{{ data_get($recordContext, 'subtitle') ?: data_get($recordContext, 'url') }}</div>
                                </div>
                                <button type="button" class="sf-btn" wire:click="editRecordContext">Change</button>
                            </div>
                        @endif
                    </div>
                @endif

                <div class="sf-context">
                    <div class="sf-context__label">Current page</div>
                    <div class="sf-context__value" x-text="document.title || 'Journey'"></div>
                </div>

                <div class="sf-thread">
                    @if ($streamError)
                        <div class="sf-turn sf-turn--ai">
                            <div class="sf-aihead">
                                <span class="sf-aihead__mark">AI</span>
                                <span>Support error</span>
                            </div>
                            <div class="sf-ai">
                                @include('padmission-tickets::filament.livewire.support.block', [
                                    'block' => ['kind' => 'Callout', 'props' => ['tone' => 'danger', 'body' => $streamError]],
                                    'key' => 'stream-error',
                                ])
                            </div>
                        </div>
                    @endif

                    @forelse ($activities as $activity)
                        @if ($activity->type === ActivityType::Message && $activity->sender === ActivitySender::User)
                            <div class="sf-turn sf-turn--user" wire:key="activity-{{ $activity->id }}">
                                <div class="sf-bubble sf-bubble--user">{{ $activity->content }}</div>
                            </div>
                        @elseif ($activity->type === ActivityType::Message && $activity->sender === ActivitySender::Ai)
                            <div class="sf-turn sf-turn--ai" wire:key="activity-{{ $activity->id }}">
                                <div class="sf-aihead">
                                    <span class="sf-aihead__mark">AI</span>
                                    <span>{{ data_get($activity->data, 'status') === 'in_progress' ? 'Thinking' : 'Answer' }}</span>
                                </div>
                                <div class="sf-ai">
                                    @if (data_get($activity->data, 'status') === 'in_progress')
                                        @include('padmission-tickets::filament.livewire.support.thinking')
                                    @endif

                                    @foreach (data_get($activity->data, 'blocks', []) as $index => $block)
                                        @include('padmission-tickets::filament.livewire.support.block', ['block' => $block, 'key' => "{$activity->id}-{$index}"])
                                    @endforeach

                                    @if (filled(data_get($activity->data, 'escalation_reason')) && $activeTicket?->status?->seed_key === 'ai_in_progress')
                                        @include('padmission-tickets::filament.livewire.support.escalation-offer', ['reason' => data_get($activity->data, 'escalation_reason')])
                                    @endif

                                    @php
                                        $feedback = data_get($activity->data, 'feedback');
                                        $isFeedbackFormOpen = $feedbackActivityId === $activity->id;
                                    @endphp

                                    <div class="sf-feedback">
                                        @if ($isFeedbackFormOpen)
                                            <form class="sf-feedback__form" wire:submit="submitAiFeedback">
                                                <label for="ai-feedback-{{ $activity->id }}">Why was this answer wrong?</label>
                                                <textarea id="ai-feedback-{{ $activity->id }}" wire:model="feedbackReason" rows="3" placeholder="Example: URP is a fixed amount set by a tenancy action."></textarea>
                                                @error('feedbackReason')
                                                    <div class="sf-feedback__error">{{ $message }}</div>
                                                @enderror
                                                <div class="sf-feedback__actions">
                                                    <button type="submit" class="sf-btn sf-btn--primary">Save flag</button>
                                                    <button type="button" class="sf-btn" wire:click="cancelAiFeedback">Cancel</button>
                                                </div>
                                            </form>
                                        @elseif (data_get($feedback, 'incorrect'))
                                            <div class="sf-feedback__saved">
                                                <span>Flagged as incorrect</span>
                                                <button type="button" wire:click="startAiFeedback({{ $activity->id }})">Edit reason</button>
                                            </div>
                                        @else
                                            <button type="button" class="sf-feedback__trigger" wire:click="startAiFeedback({{ $activity->id }})">
                                                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 4.5v6M10 14.5h.01M4.8 17h10.4c1.1 0 1.78-1.2 1.22-2.15L11.22 5.8a1.4 1.4 0 00-2.44 0l-5.2 9.05A1.42 1.42 0 004.8 17Z" stroke="currentColor" stroke-width="1.35" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                Flag as incorrect
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @elseif ($activity->type === ActivityType::Message)
                            <div class="sf-turn sf-turn--human" wire:key="activity-{{ $activity->id }}">
                                <div class="sf-human-who">
                                    {{ $activity->user_name }}
                                    <span class="sf-human-who__role">Padmission support</span>
                                </div>
                                <div class="sf-bubble sf-bubble--human">{!! $activity->content !!}</div>
                            </div>
                        @else
                            <div class="sf-event" wire:key="activity-{{ $activity->id }}">
                                <span class="sf-event__line"></span>
                                <span class="sf-event__pill">{{ $activity->content }}</span>
                                <span class="sf-event__line"></span>
                            </div>
                        @endif
                    @empty
                        @if ($isStreaming)
                            <div class="sf-turn sf-turn--ai">
                                <div class="sf-aihead">
                                    <span class="sf-aihead__mark">AI</span>
                                    <span>Thinking</span>
                                </div>
                                <div class="sf-ai">
                                    @include('padmission-tickets::filament.livewire.support.thinking')
                                </div>
                            </div>
                        @else
                            <div class="sf-empty">
                                <div class="sf-empty__mark">AI</div>
                                <h3>What can I help you find?</h3>
                                <p>Ask about Journey workflows, records, payments, audits, or documentation.</p>
                            </div>
                        @endif
                    @endforelse

                    @if ($activeTicket && $activeTicket->status?->seed_key !== 'ai_in_progress')
                        @include('padmission-tickets::filament.livewire.support.ticket-status-banner', ['ticket' => $activeTicket])
                    @endif
                </div>

                <div class="sf-actionbar">
                    <a href="/kb" class="sf-actionchip">
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M5 4.5h6.5A2.5 2.5 0 0114 7v8.5H7.5A2.5 2.5 0 015 13V4.5Z" stroke="currentColor" stroke-width="1.4"/></svg>
                        Read docs
                    </a>
                    @if (! $isHumanTicket)
                        <button type="button" class="sf-actionchip sf-actionchip--accent" wire:click="openTicket" @disabled(! $activeTicket || $activeTicket->status?->seed_key !== 'ai_in_progress')>
                            <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 5.5h12v8H9l-3.5 2.5v-2.5H4v-8Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>
                            Open a ticket
                        </button>
                    @elseif (! $isResolvedTicket)
                        <button type="button" class="sf-actionchip" wire:click="markResolved">
                            <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4.5 10.5l3.5 3.5 7.5-8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Mark resolved
                        </button>
                    @endif
                </div>

                <form class="sf-composer" wire:submit="sendMessage">
                    <div class="sf-composer__hint">
                        <span>{{ $isResolvedTicket ? 'This ticket is resolved.' : ($isHumanTicket ? 'Message Padmission support.' : 'AI answers can be attached to a support ticket.') }}</span>
                        <span class="sf-keyhint">Cmd/Ctrl Enter</span>
                    </div>
                    <div class="sf-inputrow">
                        <textarea
                            wire:model="message"
                            rows="1"
                            placeholder="{{ $isResolvedTicket ? 'This ticket is resolved' : ($isHumanTicket ? 'Send a message to Padmission support' : 'Ask about this page or a Journey workflow') }}"
                            @disabled($isResolvedTicket)
                            x-init="$el.dispatchEvent(new Event('input'))"
                            x-on:input="
                                $el.style.height = 'auto';
                                const styles = getComputedStyle($el);
                                const max = (parseFloat(styles.lineHeight) * 5) + parseFloat(styles.paddingTop) + parseFloat(styles.paddingBottom);
                                $el.style.height = Math.min($el.scrollHeight, max) + 'px';
                            "
                            x-on:keydown.meta.enter.prevent="$el.form?.requestSubmit()"
                            x-on:keydown.ctrl.enter.prevent="$el.form?.requestSubmit()"
                        ></textarea>
                        <button type="submit" aria-label="Send" @disabled($isResolvedTicket)>
                            <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M3.5 10h12M11 5.5L15.5 10 11 14.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                    </div>
                </form>
            </section>
        @elseif ($activeTab === 'history')
            <section class="sf-ticketspane">
                <div class="sf-ticketspane__head">
                    <div>
                        <div class="sf-ticketspane__title">AI conversations</div>
                        <div class="sf-ticketspane__sub">Questions you asked AI before choosing whether to open a ticket.</div>
                    </div>
                    <button type="button" class="sf-btn sf-btn--primary" wire:click="$set('activeTab', 'ask')">Ask AI</button>
                </div>
                <div class="sf-ticketspane__list">
                    @forelse ($conversations as $conversation)
                        <button type="button" class="sf-ticket" wire:click="selectTicket({{ $conversation->id }})" wire:key="conversation-{{ $conversation->id }}">
                            <span class="sf-ticket__head">
                                <span class="sf-ticket__id">AI #{{ $conversation->id }}</span>
                                <span class="sf-statuspill">AI conversation</span>
                            </span>
                            <span class="sf-ticket__subject">{{ $conversation->subject }}</span>
                            <span class="sf-ticket__snippet">{{ $conversation->latestUserMessage?->content ?: 'No messages yet.' }}</span>
                            <span class="sf-ticket__foot">{{ $conversation->updated_at?->diffForHumans() }}</span>
                        </button>
                    @empty
                        <div class="sf-empty">
                            <div class="sf-empty__mark">AI</div>
                            <h3>No AI conversations yet</h3>
                            <p>Ask AI a question, then open a ticket only if you still need human support.</p>
                        </div>
                    @endforelse
                </div>
            </section>
        @else
            <section class="sf-ticketspane">
                <div class="sf-ticketspane__head">
                    <div>
                        <div class="sf-ticketspane__title">Your support tickets</div>
                        <div class="sf-ticketspane__sub">Conversations escalated to Padmission support.</div>
                    </div>
                    <button type="button" class="sf-btn sf-btn--primary" wire:click="$set('activeTab', 'ask')">Ask AI</button>
                </div>
                <div class="sf-ticketspane__filter">
                    <span class="sf-filterchip is-active">All · {{ $counts['all'] }}</span>
                    <span class="sf-filterchip">Open · {{ $counts['open'] }}</span>
                    <span class="sf-filterchip">Resolved · {{ $counts['resolved'] }}</span>
                </div>
                <div class="sf-ticketspane__list">
                    @forelse ($tickets as $ticket)
                        <button type="button" class="sf-ticket" wire:click="selectTicket({{ $ticket->id }})" wire:key="ticket-{{ $ticket->id }}">
                            <span class="sf-ticket__head">
                                <span class="sf-ticket__id">#{{ $ticket->id }}</span>
                                <span class="sf-statuspill">{{ $ticket->status?->display_name }}</span>
                            </span>
                            <span class="sf-ticket__subject">{{ $ticket->subject }}</span>
                            <span class="sf-ticket__snippet">{{ $ticket->latestMessage?->content ?: $ticket->latestUserMessage?->content ?: 'No messages yet.' }}</span>
                            <span class="sf-ticket__foot">{{ $ticket->updated_at?->diffForHumans() }}</span>
                        </button>
                    @empty
                        <div class="sf-empty">
                            <div class="sf-empty__mark">0</div>
                            <h3>No tickets yet</h3>
                            <p>Ask AI first. Open a ticket when you decide a person should take over.</p>
                        </div>
                    @endforelse
                </div>
                <div class="sf-ticketspane__foot">Tickets older than 90 days are archived. <a href="/tickets">Search archive</a></div>
            </section>
        @endif
    </aside>

    @php
        $supportPanelStreamAsset = public_path('js/padmission/tickets/support-panel-stream.js');
    @endphp
    <script src="{{ asset('js/padmission/tickets/support-panel-stream.js') }}?t={{ file_exists($supportPanelStreamAsset) ? filemtime($supportPanelStreamAsset) : time() }}" type="module"></script>
</div>
