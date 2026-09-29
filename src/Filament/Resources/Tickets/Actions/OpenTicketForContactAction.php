<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use Padmission\Tickets\Filament\Forms\Components\TicketSubjectInput;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\Concerns\StartsTickets;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Services\TicketStarter;
use Padmission\Tickets\TicketPlugin;
use RuntimeException;

/*
 * A panel that receives escalations logs a question that a supporter of an
 * organization asked it some other way, such as by phone, as the question
 * that supporter could have asked from their own list.
 */
class OpenTicketForContactAction extends Action
{
    use StartsTickets;

    protected const KEY = 'padmission-tickets::tickets.actions.open_ticket_for_contact.';

    public static function getDefaultName(): ?string
    {
        return 'open-ticket-for-contact';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('padmission-tickets::tickets.actions.start_ticket.label'))
            ->icon(Heroicon::Plus)
            ->modalHeading(__('padmission-tickets::tickets.actions.start_ticket.label'))
            ->modalDescription(fn (): string => __(self::KEY.'modal_description'))
            ->visible(fn (): bool => TicketPlugin::get()->canStartTickets() && static::sourcePanelId() !== null)
            ->authorize('openTicketFromList')
            // Whatever a host makes actions default to, this is a slide-over, as Escalate is.
            ->slideOver()
            ->modalWidth(Width::Large)
            ->closeModalByClickingAway(false)
            ->modalSubmitActionLabel(__(self::KEY.'submit'))
            ->schema([
                Select::make('tenant_id')
                    ->label(__(self::KEY.'organization'))
                    // Listed as soon as it opens; search reaches past the first ones.
                    ->options(fn (): array => static::searchTenants(''))
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => static::searchTenants($search))
                    ->getOptionLabelUsing(fn (mixed $value): ?string => static::findTenant($value)?->getAttribute('name'))
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('contact_id', null))
                    ->visible(fn (): bool => static::usesTenants()),

                Select::make('contact_id')
                    ->label(__(self::KEY.'contact'))
                    ->options(fn (Get $get): array => collect(static::contacts($get('tenant_id')))->map(fn (Model $user): string => (string) static::personOption($user))->all())
                    ->allowHtml()
                    ->searchable()
                    ->required()
                    ->live()
                    ->helperText(fn (Get $get): ?string => static::usesTenants() && filled($get('tenant_id')) && static::contacts($get('tenant_id')) === []
                        ? __(self::KEY.'nobody')
                        : null)
                    ->visible(fn (Get $get): bool => ! static::usesTenants() || filled($get('tenant_id'))),

                Group::make([
                    TicketSubjectInput::make('subject')
                        ->label(__('padmission-tickets::tickets.actions.start_ticket.subject'))
                        ->required()
                        ->maxLength(255),

                    RichEditor::make('message')
                        ->label(fn (Get $get): string => ($name = static::contactName($get('tenant_id'), $get('contact_id'))) === null
                            ? __('padmission-tickets::tickets.actions.start_ticket.message')
                            : __('padmission-tickets::tickets.actions.start_ticket.message_to', ['name' => $name]))
                        ->helperText(fn (Get $get): ?string => ($name = static::contactName($get('tenant_id'), $get('contact_id'))) === null
                            ? null
                            : __('padmission-tickets::tickets.actions.start_ticket.message_helper', ['name' => $name]))
                        ->required()
                        ->toolbarButtons(['bold', 'link', 'bulletList', 'orderedList']),

                    static::attachmentsField(),
                ])
                    ->visible(fn (Get $get): bool => filled($get('contact_id'))),

                Text::make(__(self::KEY.'regular_users'))
                    ->color('gray'),
            ])
            ->action(function (array $data, Component $livewire): void {
                $tenantId = static::usesTenants() ? ($data['tenant_id'] ?? null) : null;
                $contact = static::usesTenants() && static::findTenant($tenantId) === null ? null : (static::contacts($tenantId)[$data['contact_id'] ?? ''] ?? null);

                if ($contact === null) {
                    Notification::make()->danger()->title(__(self::KEY.'invalid_contact'))->send();

                    $this->halt();

                    return;
                }

                try {
                    $ticket = resolve(TicketStarter::class)->askFor($contact, $tenantId, (string) static::sourcePanelId(), $data['subject'], $data['message'], static::uploadedFiles($data));
                } catch (RuntimeException $exception) {
                    report($exception);

                    Notification::make()->danger()->title(__(self::KEY.'not_configured'))->send();

                    $this->halt();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title(__(self::KEY.'created.title'))
                    ->body(__(self::KEY.'created.body', ['name' => e(Filament::getUserName($contact))]))
                    ->send();

                $livewire->redirect(TicketResource::getUrl('view', ['record' => $ticket]));
            });
    }

    /*
     * The panel whose supporters escalate here, where the contact answers
     * tickets and where the question is listed as theirs.
     */
    protected static function sourcePanelId(): ?string
    {
        return array_key_first(TicketPlugin::get()->getLinkedTicketChildPanels());
    }

    protected static function usesTenants(): bool
    {
        return (bool) config('padmission-tickets.tenancy.enabled');
    }

    /**
     * @return Builder<Model>
     */
    protected static function tenantsQuery(): Builder
    {
        return TicketPlugin::get()->getTicketTenantsQuery()
            ?? config('padmission-tickets.tenancy.tenancy_model')::query()->whereRaw('1 = 0');
    }

    /**
     * @return array<int|string, string>
     */
    protected static function searchTenants(string $search): array
    {
        $query = static::tenantsQuery();

        return $query
            ->when(filled($search), fn (Builder $query): Builder => $query->where($query->qualifyColumn('name'), 'like', "%{$search}%"))
            ->orderBy($query->qualifyColumn('name'))
            ->limit(50)
            ->pluck($query->qualifyColumn('name'), $query->getModel()->getQualifiedKeyName())
            ->all();
    }

    protected static function findTenant(mixed $id): ?Model
    {
        return blank($id) ? null : once(fn (): ?Model => static::tenantsQuery()->whereKey($id)->first());
    }

    /**
     * The organization's supporters, from the panel they answer tickets in,
     * pinned to the organization through a ticket of its own.
     *
     * @return array<int|string, Model>
     */
    protected static function contacts(mixed $tenantId): array
    {
        $sourcePanelId = static::sourcePanelId();

        if ($sourcePanelId === null || (static::usesTenants() && blank($tenantId))) {
            return [];
        }

        return once(function () use ($sourcePanelId, $tenantId): array {
            $supportersQuery = TicketPlugin::get($sourcePanelId)->getAllSupportersQuery();

            if ($supportersQuery === null) {
                return [];
            }

            $model = TicketPlugin::resolveModelClass(Ticket::class);
            $ticket = (new $model)->forceFill(['panel' => $sourcePanelId, ...(static::usesTenants() ? ['tenant_id' => $tenantId] : [])]);

            return app()->call($supportersQuery, ['ticket' => $ticket])
                ->get()
                ->sortBy(fn (Model $user): string => Filament::getUserName($user))
                ->mapWithKeys(fn (Model $user): array => [$user->getKey() => $user])
                ->all();
        });
    }

    protected static function contactName(mixed $tenantId, mixed $contactId): ?string
    {
        $contact = blank($contactId) ? null : (static::contacts($tenantId)[$contactId] ?? null);

        return $contact === null ? null : Filament::getUserName($contact);
    }
}
