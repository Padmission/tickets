<?php

namespace Padmission\Tickets;

use Closure;
use Exception;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Contracts\Support\Htmlable;

final class ChatWidgetConfig
{
    public string|Htmlable|Closure|null $introMessage = null;

    public string|Htmlable|Closure|null $autoResponse = null;

    public string|array|Closure|null $primaryColor = null;

    public static function make(): self
    {
        return new self;
    }

    /**
     * Message that is shown to the user when he opens a chat.
     */
    public function introMessage(string|Htmlable|Closure $message): self
    {
        $this->introMessage = $message;

        return $this;
    }

    public function getIntroMessage(): string|Htmlable
    {
        return value($this->introMessage);
    }

    /**
     * Message that is sent after users send their first message.
     */
    public function autoResponse(string|Htmlable|Closure $reponse): self
    {
        $this->autoResponse = $reponse;

        return $this;
    }

    public function getAutoResponse(): string|Htmlable
    {
        return value($this->autoResponse);
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

        return 'rgb('.FilamentColor::processColor($color)['600'].')';
    }
}
