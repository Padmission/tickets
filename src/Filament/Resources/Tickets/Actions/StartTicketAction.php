<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Actions;

use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Padmission\Tickets\Filament\Forms\Components\TicketSubjectInput;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Rules\PlainText;
use Padmission\Tickets\Services\TicketStarter;
use Padmission\Tickets\TicketPlugin;
use RuntimeException;

use function Filament\Support\generate_icon_html;

/*
 * Supporters start a ticket from the ticket list, either for someone in their
 * organization or as a question of their own for the team they escalate to.
 * The choice comes first and has no default outside a tab, so neither side is
 * picked by accident.
 */
class StartTicketAction extends Action
{
    public const ORGANIZATION = 'organization';

    public const ESCALATION = 'escalation';

    protected const KEY = 'padmission-tickets::tickets.actions.start_ticket.';

    public static function getDefaultName(): ?string
    {
        return 'start-ticket';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__(self::KEY.'label'))
            ->icon(Heroicon::Plus)
            ->modalHeading(__(self::KEY.'label'))
            ->visible(fn (): bool => TicketPlugin::get()->canStartTickets())
            ->authorize('startTicket')
            // Whatever a host makes actions default to, this is a slide-over, as Escalate is.
            ->slideOver()
            ->modalWidth(Width::Large)
            ->closeModalByClickingAway(false)
            ->modalSubmitActionLabel(fn (Action $action): string => static::kind($action->getRawData()['kind'] ?? null) === self::ESCALATION
                ? static::teamText('submit_team')
                : __(self::KEY.'submit'))
            ->fillForm(fn (Component $livewire): array => [
                'kind' => static::kindForTab(property_exists($livewire, 'activeTab') ? $livewire->activeTab : null),
                'assign' => 'me',
            ])
            ->schema([
                Radio::make('kind')
                    ->label(__(self::KEY.'kind.label'))
                    ->hiddenLabel()
                    ->options(fn (): array => [
                        self::ORGANIZATION => static::choiceLabel(Heroicon::OutlinedUser, __(self::KEY.'kind.organization')),
                        self::ESCALATION => static::choiceLabel(Heroicon::OutlinedBuildingOffice, static::teamText('kind.escalation')),
                    ])
                    ->descriptions([
                        self::ORGANIZATION => __(self::KEY.'kind.organization_description'),
                        self::ESCALATION => __(self::KEY.'kind.escalation_description'),
                    ])
                    ->extraAttributes(['class' => 'pad-ti-start-choice'])
                    ->required()
                    ->live()
                    ->visible(fn (): bool => static::canAsk()),

                Group::make([
                    ...static::organizationFields(),
                    ...static::escalationFields(),

                    TicketSubjectInput::make('subject')
                        ->label(__(self::KEY.'subject'))
                        ->required()
                        ->maxLength(255),

                    RichEditor::make('message')
                        ->label(fn (Get $get): string => static::kind($get('kind')) === self::ESCALATION
                            ? static::teamText('message_to_team')
                            : (($name = static::requesterName($get('requester_id'))) === null ? __(self::KEY.'message') : __(self::KEY.'message_to', ['name' => $name])))
                        ->helperText(fn (Get $get): ?string => static::kind($get('kind')) === self::ESCALATION
                            ? __(self::KEY.'message_helper_team')
                            : (($name = static::requesterName($get('requester_id'))) === null ? null : __(self::KEY.'message_helper', ['name' => $name])))
                        ->required()
                        ->toolbarButtons(['bold', 'link', 'bulletList', 'orderedList']),

                    FileUpload::make('attachments')
                        ->label(__(self::KEY.'attachments'))
                        ->multiple()
                        ->storeFiles(false)
                        ->maxSize(fn (): int => intdiv(TicketPlugin::get()->getChatWidgetConfig()->getMaxUploadFileSize(), 1024))
                        ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            if ($value instanceof TemporaryUploadedFile && PlainText::hasMarkup($value->getClientOriginalName())) {
                                $fail('padmission-tickets::validation.plain_text')->translate();
                            }
                        })
                        ->visible(fn (): bool => TicketPlugin::get()->getChatWidgetConfig()->getAllowFileUploads()),

                    Text::make(__(self::KEY.'escalate_instead'))
                        ->color('gray')
                        ->visible(fn (Get $get): bool => static::kind($get('kind')) === self::ESCALATION),
                ])
                    ->extraAttributes(fn (): array => static::canAsk() ? ['class' => 'pad-ti-start-fields'] : [])
                    ->visible(fn (Get $get): bool => static::kind($get('kind')) !== null),
            ])
            ->action(function (array $data, Component $livewire): void {
                $attachments = array_values(array_filter($data['attachments'] ?? [], fn (mixed $file): bool => $file instanceof TemporaryUploadedFile));

                try {
                    $ticket = static::kind($data['kind'] ?? null) === self::ESCALATION
                        ? $this->ask($data, $attachments)
                        : $this->openFor($data, $attachments);
                } catch (RuntimeException $exception) {
                    // Statuses or priorities were never set up for the panel: a gap the user cannot fix from here.
                    report($exception);

                    Notification::make()
                        ->danger()
                        ->title(__('padmission-tickets::tickets.actions.create_linked_ticket.notifications.not_configured.title'))
                        ->body(__('padmission-tickets::tickets.actions.create_linked_ticket.notifications.not_configured.body', [
                            'panel' => TicketPlugin::get()->getEscalationTargetName() ?? Filament::getCurrentOrDefaultPanel()->getId(),
                        ]))
                        ->send();

                    $this->halt();

                    return;
                }

                $livewire->redirect(TicketResource::getUrl('view', ['record' => $ticket]));
            });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<TemporaryUploadedFile>  $attachments
     */
    protected function openFor(array $data, array $attachments): Ticket
    {
        $requester = static::requestersQuery()->whereKey($data['requester_id'] ?? null)->first();

        if ($requester === null) {
            $this->refuse(__(self::KEY.'invalid_requester'));
        }

        $assigneeId = match ($data['assign'] ?? 'me') {
            'colleague' => array_key_exists($data['assignee_id'] ?? '', static::colleagues()) ? $data['assignee_id'] : $this->refuse(__(self::KEY.'invalid_assignee')),
            'auto' => null,
            default => Filament::auth()->id(),
        };

        $ticket = resolve(TicketStarter::class)->openFor($requester, $data['subject'], $data['message'], $assigneeId, $attachments);

        $assignee = $ticket->assignee;

        Notification::make()
            ->success()
            ->title(__(self::KEY.'created.title'))
            ->body(implode(' ', [
                __(self::KEY.'created.body', ['name' => e(Filament::getUserName($requester))]),
                match (true) {
                    $assignee === null => __(self::KEY.'created.unassigned'),
                    (string) $assignee->getKey() === (string) Filament::auth()->id() => __(self::KEY.'created.assigned_you'),
                    default => __(self::KEY.'created.assigned', ['name' => e(Filament::getUserName($assignee))]),
                },
            ]))
            ->send();

        return $ticket;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<TemporaryUploadedFile>  $attachments
     */
    protected function ask(array $data, array $attachments): Ticket
    {
        $panels = TicketPlugin::get()->getLinkedTicketParentPanels();
        $targetPanelId = array_key_exists($data['panel'] ?? '', $panels) ? $data['panel'] : array_key_first($panels);

        $ticket = resolve(TicketStarter::class)->ask($targetPanelId, $data['subject'], $data['message'], $attachments);

        $team = TicketPlugin::find($targetPanelId)?->getSupportTeamName();
        $team = $team === null ? null : e($team);

        Notification::make()
            ->success()
            ->title(TicketPlugin::teamText(self::KEY.'sent.title', $team))
            ->body(TicketPlugin::teamText(self::KEY.'sent.body', $team))
            ->send();

        return $ticket;
    }

    protected function refuse(string $message): never
    {
        Notification::make()
            ->danger()
            ->title($message)
            ->send();

        $this->halt();

        throw new RuntimeException('Unreachable: halt() always throws.');
    }

    /**
     * @return array<int, mixed>
     */
    protected static function organizationFields(): array
    {
        $visible = fn (Get $get): bool => static::kind($get('kind')) === self::ORGANIZATION;

        return [
            Select::make('requester_id')
                ->label(__(self::KEY.'requester'))
                ->searchable()
                ->getSearchResultsUsing(fn (string $search): array => static::searchRequesters($search))
                ->getOptionLabelUsing(fn (mixed $value): ?string => static::requesterOption(static::findRequester($value)))
                ->allowHtml()
                ->helperText(fn (Get $get): ?string => ($name = static::requesterName($get('requester_id'))) === null ? null : __(self::KEY.'requester_helper', ['name' => $name]))
                ->required()
                ->live()
                ->visible($visible),

            ToggleButtons::make('assign')
                ->label(__(self::KEY.'assign'))
                ->options(fn (): array => static::assignOptions())
                ->inline()
                ->grouped()
                ->required()
                ->live()
                ->visible($visible),

            Select::make('assignee_id')
                ->label(__(self::KEY.'colleague'))
                ->options(fn (): array => static::colleagues())
                ->searchable()
                ->required()
                ->visible(fn (Get $get): bool => $visible($get) && $get('assign') === 'colleague'),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    protected static function escalationFields(): array
    {
        return [
            Select::make('panel')
                ->label(__(self::KEY.'panel'))
                ->options(fn (): array => collect(TicketPlugin::get()->getLinkedTicketParentPanels())
                    ->mapWithKeys(fn (Panel $panel): array => [$panel->getId() => TicketPlugin::getSupportTeamNameForPanel($panel) ?? ucfirst($panel->getId())])
                    ->all())
                ->required()
                ->visible(fn (Get $get): bool => static::kind($get('kind')) === self::ESCALATION
                    && count(TicketPlugin::get()->getLinkedTicketParentPanels()) > 1),
        ];
    }

    protected static function canAsk(): bool
    {
        return count(TicketPlugin::get()->getLinkedTicketParentPanels()) > 0;
    }

    /*
     * Without anyone to ask, every ticket started here is for the organization.
     */
    protected static function kind(mixed $kind): ?string
    {
        if (! static::canAsk()) {
            return self::ORGANIZATION;
        }

        return in_array($kind, [self::ORGANIZATION, self::ESCALATION], true) ? $kind : null;
    }

    protected static function kindForTab(?string $tab): ?string
    {
        return match ($tab) {
            'all', 'my' => self::ORGANIZATION,
            'linked', 'my_linked' => static::canAsk() ? self::ESCALATION : null,
            default => null,
        };
    }

    protected static function teamText(string $key): string
    {
        return TicketPlugin::teamText(self::KEY.$key, TicketPlugin::get()->getEscalationTargetName());
    }

    protected static function choiceLabel(Heroicon $icon, string $title): HtmlString
    {
        return new HtmlString(
            '<span class="pad-ti-start-choice__icon">'.generate_icon_html($icon)?->toHtml().'</span>'
            .'<span class="pad-ti-start-choice__title">'.e($title).'</span>'
        );
    }

    /**
     * @return array<string, string>
     */
    protected static function assignOptions(): array
    {
        return [
            'me' => __(self::KEY.'assign_me'),
            ...(static::colleagues() === [] ? [] : ['colleague' => __(self::KEY.'assign_colleague')]),
            ...(TicketPlugin::get()->getAssignmentStrategy() === null ? [] : ['auto' => __(self::KEY.'assign_automatic')]),
        ];
    }

    /**
     * @return array<int|string, string>
     */
    protected static function colleagues(): array
    {
        return once(function (): array {
            $supportersQuery = TicketPlugin::get()->getAllSupportersQuery();

            if ($supportersQuery === null) {
                return [];
            }

            return app()->call($supportersQuery)
                ->whereKeyNot(Filament::auth()->id())
                ->get()
                ->mapWithKeys(fn (Model $user): array => [$user->getKey() => Filament::getUserName($user)])
                ->sort()
                ->all();
        });
    }

    /**
     * Nobody opens a ticket for themselves here: their own go through the chat.
     *
     * @return Builder<Model>
     */
    protected static function requestersQuery(): Builder
    {
        return TicketPlugin::get()->getRequestersQuery()->whereKeyNot(Filament::auth()->id());
    }

    /**
     * Names repeat within an organization, so each option shows the email too.
     *
     * @return array<int|string, string>
     */
    protected static function searchRequesters(string $search): array
    {
        $query = static::requestersQuery();

        return $query
            ->where(fn (Builder $query): Builder => $query
                ->where($query->qualifyColumn('name'), 'like', "%{$search}%")
                ->orWhere($query->qualifyColumn('email'), 'like', "%{$search}%"))
            ->orderBy($query->qualifyColumn('name'))
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Model $user): array => [$user->getKey() => static::requesterOption($user)])
            ->all();
    }

    protected static function findRequester(mixed $id): ?Model
    {
        if (blank($id)) {
            return null;
        }

        return once(fn (): ?Model => static::requestersQuery()->whereKey($id)->first());
    }

    protected static function requesterName(mixed $id): ?string
    {
        $requester = static::findRequester($id);

        return $requester === null ? null : Filament::getUserName($requester);
    }

    protected static function requesterOption(?Model $user): ?string
    {
        if ($user === null) {
            return null;
        }

        $email = $user->getAttribute('email');

        return e(Filament::getUserName($user)).(blank($email) ? '' : ' <span class="pad-ti-start-email">'.e($email).'</span>');
    }
}
