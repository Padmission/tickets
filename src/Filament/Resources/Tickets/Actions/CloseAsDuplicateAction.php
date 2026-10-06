<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Services\TicketCloser;
use Padmission\Tickets\Services\TicketDuplicates;

class CloseAsDuplicateAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'close-as-duplicate';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $key = 'padmission-tickets::tickets.actions.close_as_duplicate.';

        $this
            ->label(__($key.'label'))
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color('gray')
            ->visible(fn (Ticket $record): bool => resolve(TicketCloser::class)->canClose($record))
            ->disabled(fn (Ticket $record): bool => resolve(TicketDuplicates::class)->involvesEscalation($record))
            ->tooltip(fn (Ticket $record): ?string => resolve(TicketDuplicates::class)->involvesEscalation($record) ? __($key.'escalation') : null)
            ->modalHeading(__($key.'modal_heading'))
            ->modalDescription(__($key.'modal_description'))
            ->modalSubmitActionLabel(__($key.'submit'))
            ->slideOver(false)
            ->schema(fn (Ticket $record): array => [
                Select::make('original')
                    ->label(__($key.'original'))
                    ->helperText(__($key.'original_help'))
                    ->searchable()
                    ->options(fn (): array => $this->optionsFor($record))
                    ->getSearchResultsUsing(fn (string $search): array => $this->optionsFor($record, $search))
                    ->getOptionLabelUsing(fn ($value): ?string => ($original = resolve(TicketDuplicates::class)->candidates($record)->find($value)) === null
                        ? null : $this->ticketLabel($original))
                    ->required(),
                ...(resolve(TicketCloser::class)->dispositionsFor($record)->exists() ? [
                    Select::make('disposition')
                        ->label(__('padmission-tickets::tickets.actions.close.disposition.label'))
                        ->relationship('disposition', 'display_name', fn (Builder $query): Builder => resolve(TicketCloser::class)->dispositionsFor($record, $query))
                        ->required(),
                ] : []),
            ])
            ->successNotificationTitle(__($key.'success'))
            ->action(function (Ticket $record, Component $livewire, Schema $schema, array $data): void {
                try {
                    resolve(TicketDuplicates::class)->close($record, $data['original'], $data['disposition'] ?? null);
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages(collect($exception->errors())
                        ->mapWithKeys(fn (array $messages, string $field): array => [$schema->getStatePath().'.'.$field => $messages])
                        ->all());
                }

                $this->success();
                $livewire->dispatch('refresh-sidebar');
            });
    }

    /** @return array<int|string, string> */
    protected function optionsFor(Ticket $record, ?string $search = null): array
    {
        return resolve(TicketDuplicates::class)->candidates($record)
            ->when(filled($search), fn (Builder $query): Builder => $query->where(function (Builder $query) use ($search): void {
                $query->whereLike('subject', "%{$search}%");

                if (ctype_digit(ltrim($search, '#'))) {
                    $query->orWhere($query->getModel()->getQualifiedKeyName(), (int) ltrim($search, '#'));
                }
            }))
            ->latest('id')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Ticket $ticket): array => [$ticket->getKey() => $this->ticketLabel($ticket)])
            ->all();
    }

    protected function ticketLabel(Ticket $ticket): string
    {
        return '#'.$ticket->getKey().' – '.$ticket->subject;
    }
}
