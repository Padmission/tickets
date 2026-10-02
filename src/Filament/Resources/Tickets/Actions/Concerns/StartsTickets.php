<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Actions\Concerns;

use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Padmission\Tickets\Rules\PlainText;
use Padmission\Tickets\Support\AttachmentName;
use Padmission\Tickets\Support\AttachmentTypes;
use Padmission\Tickets\TicketPlugin;

/*
 * What both ways of starting a ticket from the list share: attachments that
 * follow the chat's upload rules, and people shown with their email, since
 * names repeat.
 */
trait StartsTickets
{
    protected static function attachmentsField(): FileUpload
    {
        return FileUpload::make('attachments')
            ->label(__('padmission-tickets::tickets.actions.start_ticket.attachments'))
            ->multiple()
            ->storeFiles(false)
            ->acceptedFileTypes(fn (): array => AttachmentTypes::allowed())
            ->maxSize(fn (): int => intdiv(TicketPlugin::get()->getChatWidgetConfig()->getMaxUploadFileSize(), 1024))
            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                if ($value instanceof TemporaryUploadedFile && PlainText::hasMarkup($value->getClientOriginalName())) {
                    $fail('padmission-tickets::validation.plain_text')->translate();
                }

                if ($value instanceof UploadedFile && ! AttachmentTypes::nameIsAllowed(AttachmentName::safe($value->getClientOriginalName()))) {
                    $fail('padmission-tickets::validation.attachment_name')->translate();
                }
            })
            ->visible(fn (): bool => TicketPlugin::get()->getChatWidgetConfig()->getAllowFileUploads());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<TemporaryUploadedFile>
     */
    protected static function uploadedFiles(array $data): array
    {
        return array_values(array_filter($data['attachments'] ?? [], fn (mixed $file): bool => $file instanceof TemporaryUploadedFile));
    }

    protected static function personOption(?Model $user): ?string
    {
        if ($user === null) {
            return null;
        }

        $email = $user->getAttribute('email');

        return e(Filament::getUserName($user)).(blank($email) ? '' : ' <span class="pad-ti-start-email">'.e($email).'</span>');
    }
}
