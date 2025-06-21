<?php

namespace Padmission\Tickets;

use Closure;
use Exception;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;
use Padmission\Tickets\Services\TicketAuth;

final class ChatWidgetConfig
{
    public bool|Closure $allowEmailAuthentication = false;

    public bool|Closure $allowGuests = false;

    public int|Closure $otpExpiresAfterMinutes = 10;

    public string|Htmlable|Closure|null $placeholder = null;

    public string|Htmlable|Closure|null $introMessage = null;

    public string|Htmlable|Closure|null $autoResponse = null;

    public string|array|Closure|null $primaryColor = null;

    public static function make(): self
    {
        return new self;
    }

    public function allowEmailAuthentication(
        bool|Closure $allow = true,
        bool|Closure $allowGuests = false,
        int|Closure $otpExpiresAfterMinutes = 10,
    ): self {
        $this->allowEmailAuthentication = $allow;
        $this->allowGuests = $allowGuests;
        $this->otpExpiresAfterMinutes = $otpExpiresAfterMinutes;

        return $this;
    }

    public function getAllowEmailAuthentication(): bool
    {
        return value($this->allowEmailAuthentication);
    }

    public function getOtpExpiresAfterMinutes(): int
    {
        return value($this->otpExpiresAfterMinutes);
    }

    /**
     * Message that is sent after users send their first message.
     */
    public function placeholder(string|Htmlable|Closure $placeholder): self
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function getPlaceholder(): ?string
    {
        $value = value($this->placeholder);

        return $value instanceof Htmlable
                    ? $value->toHtml()
                    : $value;
    }

    /**
     * Message that is shown to the user when he opens a chat.
     */
    public function introMessage(string|Htmlable|Closure $message): self
    {
        $this->introMessage = $message;

        return $this;
    }

    public function getIntroMessage(): ?string
    {
        $value = value($this->introMessage);

        return $value instanceof Htmlable
            ? $value->toHtml()
            : $value;
    }

    /**
     * Message that is sent after users send their first message.
     */
    public function autoResponse(string|Htmlable|Closure $response): self
    {
        $this->autoResponse = $response;

        return $this;
    }

    public function getAutoResponse(): ?string
    {
        $value = value($this->autoResponse);

        return $value instanceof Htmlable
            ? $value->toHtml()
            : $value;
    }

    public function primaryColor(string|array|Closure $color): self
    {
        $this->primaryColor = $color;

        return $this;
    }

    public function getPrimaryColor(): string
    {
        $color = $this->primaryColor
            ?? Filament::getCurrentPanel()->getColors()['primary']
            ?? null;

        if ($color === null) {
            throw new Exception('No primary color given for ChatWidgetConfig');
        }

        if ($color instanceof Closure) {
            $color = $color();
        }

        return 'rgb('.FilamentColor::processColor($color)[600].')';
    }

    public function toJs(): string
    {
        $auth = resolve(TicketAuth::class);

        return json_encode([
            'panelId' => 'panel-'.Filament::getId(),
            'userId' => $auth->getUserId(),
            'placeholder' => $this->getPlaceholder(),
            'introMessage' => $this->getIntroMessage(),
            'lang' => Arr::dot(__('padmission-tickets::chat')),
        ]);
    }
}
