<?php

namespace Padmission\Tickets\Configuration\Data;

class EventNotificationSettings
{
    public function __construct(
        public readonly array $userTriggered = [],
        public readonly array $supporterTriggered = [],
    ) {}

    public function merge(EventNotificationSettings $other): self
    {
        return new self(
            userTriggered: array_merge($this->userTriggered, $other->userTriggered),
            supporterTriggered: array_merge($this->supporterTriggered, $other->supporterTriggered),
        );
    }

    public function getSettingsFor(string $triggerType): array
    {
        return match ($triggerType) {
            'user_triggered' => $this->userTriggered,
            'supporter_triggered' => $this->supporterTriggered,
            default => [],
        };
    }

    public function shouldNotify(string $triggerType, string $recipient): bool
    {
        $settings = $this->getSettingsFor($triggerType);

        // Support both old boolean format and new channel format
        if (isset($settings["notify_{$recipient}"])) {
            return (bool) $settings["notify_{$recipient}"];
        }

        // If no specific setting, check if any channels are enabled for this recipient
        $recipientChannels = $this->getChannelsFor($triggerType, $recipient);

        return ! empty(array_filter($recipientChannels));
    }

    public function getChannelsFor(string $triggerType, string $recipient): array
    {
        $settings = $this->getSettingsFor($triggerType);
        $channels = [];

        foreach ($settings as $key => $value) {
            // Skip old boolean format
            if (str_starts_with($key, 'notify_')) {
                continue;
            }

            // Channel format: email_user, slack_supporter, sms_both, etc.
            if (str_ends_with($key, "_{$recipient}") || str_ends_with($key, '_both')) {
                // Only include enabled channels
                if ($value) {
                    $channelName = str_replace(["_{$recipient}", '_both'], '', $key);
                    $channels[$channelName] = $value;
                }
            }
        }

        return $channels;
    }

    public function isChannelEnabledFor(string $triggerType, string $recipient, string $channel): bool
    {
        $channels = $this->getChannelsFor($triggerType, $recipient);

        return $channels[$channel] ?? false;
    }

    public function toArray(): array
    {
        return [
            'user_triggered' => $this->userTriggered,
            'supporter_triggered' => $this->supporterTriggered,
        ];
    }
}
