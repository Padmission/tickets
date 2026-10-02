<?php

namespace Padmission\Tickets\Rules;

use Closure;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Contracts\Validation\ValidationRule;
use Padmission\Tickets\Support\MessageHtml;

/*
 * A rich editor's message, refused on its own field when its cleaned HTML
 * would not fit the content column: cleaning writes each quote and ampersand
 * out as an entity, so a message can grow several times over.
 */
class MessageFitsColumn implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $html = is_array($value) ? RichContentRenderer::make($value)->toUnsafeHtml() : (string) $value;

        if (strlen(MessageHtml::sanitize($html)) > MessageHtml::MAX_BYTES) {
            $fail('padmission-tickets::validation.message_too_long')->translate();
        }
    }
}
