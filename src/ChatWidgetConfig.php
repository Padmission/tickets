<?php

namespace Padmission\Tickets;

use Closure;
use Composer\InstalledVersions;
use Exception;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Padmission\Tickets\Services\TicketAuth;
use Padmission\Tickets\Support\AttachmentTypes;

final class ChatWidgetConfig
{
    public bool|Closure $allowEmailAuthentication = false;

    public bool|Closure $allowGuests = false;

    public ?Closure $allowEmailAuthenticationFor = null;

    public int|Closure $otpExpiresAfterMinutes = 10;

    public string|Htmlable|Closure|null $placeholder = null;

    public string|Htmlable|Closure|null $introMessage = null;

    public string|Htmlable|Closure|null $autoResponse = null;

    public string|array|Closure|null $primaryColor = null;

    public bool|Closure $allowFileUploads = false;

    public int|Closure $maxUploadFileSize = 10 * 1024 * 1024;

    public bool|Closure $allowScreenshots = false;

    public Closure|string|null $documentationUrl = null;

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

    public function getAllowGuests(): bool
    {
        return value($this->allowGuests);
    }

    /*
     * The host's own say on who may sign in with an emailed code, on top of
     * the accounts the package always sends to their password: those with
     * multi-factor authentication and the supporters of any ticket panel.
     */
    public function allowEmailAuthenticationFor(?Closure $callback): self
    {
        $this->allowEmailAuthenticationFor = $callback;

        return $this;
    }

    public function allowsEmailAuthenticationFor(Model $user): bool
    {
        return $this->allowEmailAuthenticationFor === null
            || (bool) app()->call($this->allowEmailAuthenticationFor, ['user' => $user]);
    }

    public function getOtpExpiresAfterMinutes(): int
    {
        return value($this->otpExpiresAfterMinutes);
    }

    public function allowFileUploads(
        bool|Closure $enable = true,
        int|Closure $maxFileSize = 10 * 1024 * 1024,
    ): self {

        if (! InstalledVersions::isInstalled('league/flysystem-aws-s3-v3')) {
            throw new Exception('Ticket Plugin: allowFileUploads() option requires league/flysystem-aws-s3-v3 package.');
        }

        $this->allowFileUploads = $enable;
        $this->maxUploadFileSize = $maxFileSize;

        return $this;
    }

    public function getAllowFileUploads(): bool
    {
        return value($this->allowFileUploads);
    }

    public function getMaxUploadFileSize(): int
    {
        return value($this->maxUploadFileSize);
    }

    public function allowScreenshots(bool|Closure $enable = true): self
    {
        $this->allowScreenshots = $enable;

        return $this;
    }

    public function getAllowScreenshots(): bool
    {
        return value($this->allowScreenshots);
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
     * Message that is shown to the user when they open a chat. A closure may
     * return null to use the package default.
     */
    public function introMessage(string|Htmlable|Closure|null $message): self
    {
        $this->introMessage = $message;

        return $this;
    }

    public function getIntroMessage(): string
    {
        return $this->resolveMessage($this->introMessage) ?? self::defaultIntroMessage();
    }

    /*
     * What the widget shows when no intro message is set, for a settings form
     * to show as the message an empty field uses.
     */
    public static function defaultIntroMessage(): string
    {
        return __('padmission-tickets::chat.defaults.intro_message');
    }

    /**
     * Message that is sent after users send their first message. A closure
     * may return null to use the package default.
     */
    public function autoResponse(string|Htmlable|Closure|null $response): self
    {
        $this->autoResponse = $response;

        return $this;
    }

    public function getAutoResponse(): string
    {
        return $this->resolveMessage($this->autoResponse) ?? self::defaultAutoResponse();
    }

    public static function defaultAutoResponse(): string
    {
        return __('padmission-tickets::chat.defaults.auto_response');
    }

    protected function resolveMessage(string|Htmlable|Closure|null $message): ?string
    {
        $value = value($message);
        $value = $value instanceof Htmlable ? $value->toHtml() : $value;

        return filled($value) ? $value : null;
    }

    public function primaryColor(string|array|Closure $color): self
    {
        $this->primaryColor = $color;

        return $this;
    }

    public function getPrimaryColor(): string
    {
        $color = $this->primaryColor
            ?? Filament::getCurrentOrDefaultPanel()->getColors()['primary']
            ?? Color::Blue;

        if ($color instanceof Closure) {
            $color = $color();
        }

        $color = is_array($color) ? $color : Color::generatePalette($color);

        return $color[600];
    }

    public function documentationUrl(string|Closure $url): self
    {
        $this->documentationUrl = $url;

        return $this;
    }

    public function getDocumentationUrl(): ?string
    {
        return value($this->documentationUrl);
    }

    public function toJs(): string
    {
        $auth = resolve(TicketAuth::class);
        $targetPanelId = TicketPlugin::get()->getTargetPanelId()
            ?? Filament::getId();

        return json_encode([
            'panelId' => 'panel-'.Filament::getId(),
            'targetPanelId' => 'panel-'.$targetPanelId,
            'userId' => $auth->getUserId(),
            'placeholder' => $this->getPlaceholder(),
            'introMessage' => $this->getIntroMessage(),
            'allowScreenshots' => $this->getAllowScreenshots(),
            'allowFileUploads' => $this->getAllowFileUploads(),
            'maxUploadFileSize' => $this->getMaxUploadFileSize(),
            'acceptedFileTypes' => AttachmentTypes::accept(),
            'documentationUrl' => $this->getDocumentationUrl(),
            'lang' => Arr::dot(__('padmission-tickets::chat')),
        ]);
    }
}
