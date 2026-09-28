<?php

use Illuminate\Support\HtmlString;
use Padmission\Tickets\ChatWidgetConfig;

it('returns correct json structure for toJs method', function () {
    $config = ChatWidgetConfig::make()
        ->placeholder('Enter your message...')
        ->introMessage('Welcome to support chat');

    $result = $config->toJs();
    $decoded = json_decode($result, true);

    expect($result)->toBeJson()
        ->and($decoded)->toHaveKeys(['panelId', 'userId', 'placeholder', 'introMessage', 'lang'])
        ->placeholder->toBe('Enter your message...')
        ->introMessage->toBe('Welcome to support chat');
});

it('handles closure values in toJs method', function () {
    $config = ChatWidgetConfig::make()
        ->placeholder(fn () => 'Dynamic placeholder')
        ->introMessage(fn () => 'Dynamic intro message');

    $result = $config->toJs();
    $decoded = json_decode($result, true);

    expect($decoded)
        ->placeholder->toBe('Dynamic placeholder')
        ->introMessage->toBe('Dynamic intro message');
});

it('handles null values in toJs method', function () {
    $config = ChatWidgetConfig::make();

    $result = $config->toJs();
    $decoded = json_decode($result, true);

    expect($decoded)
        ->placeholder->toBeNull()
        ->introMessage->toBe(__('padmission-tickets::chat.defaults.intro_message'));
});

it('uses the package defaults when no intro or auto-response is set', function (bool $configured, ?string $returned) {
    $config = ChatWidgetConfig::make();

    if ($configured) {
        $config->introMessage(fn (): ?string => $returned)->autoResponse(fn (): ?string => $returned);
    }

    expect($config->getIntroMessage())->toStartWith('Our support team of real people is here to help.')
        ->and($config->getAutoResponse())->toBe('Thanks for your message! We will respond soon.');
})->with([
    'nothing set' => [false, null],
    'closure returning null' => [true, null],
    'closure returning an empty string' => [true, ''],
]);

it('names the defaults an unset message falls back to, for settings forms to show', function () {
    $config = ChatWidgetConfig::make();

    expect(ChatWidgetConfig::defaultIntroMessage())->toBe($config->getIntroMessage())
        ->toBe(__('padmission-tickets::chat.defaults.intro_message'))
        ->and(ChatWidgetConfig::defaultAutoResponse())->toBe($config->getAutoResponse())
        ->toBe(__('padmission-tickets::chat.defaults.auto_response'));
});

it('prefers a configured auto-response over the default', function () {
    expect(ChatWidgetConfig::make()->autoResponse('We are on it.')->getAutoResponse())->toBe('We are on it.');
});

it('formats panel id correctly', function () {
    $config = ChatWidgetConfig::make();

    $result = $config->toJs();
    $decoded = json_decode($result, true);

    expect($decoded)->panelId->toStartWith('panel-');
});

it('includes language translations', function () {
    $config = ChatWidgetConfig::make();

    $result = $config->toJs();
    $decoded = json_decode($result, true);

    expect($decoded)
        ->toHaveKey('lang')
        ->lang->toBeArray();
});

it('allows HtmlString in placeholder and introMessage', function () {
    $config = ChatWidgetConfig::make()
        ->placeholder(new HtmlString('<span>HTML placeholder</span>'))
        ->introMessage(new HtmlString('<span>HTML intro message</span>'));

    $result = $config->toJs();
    $decoded = json_decode($result, true);

    expect($decoded)
        ->placeholder->toBe('<span>HTML placeholder</span>')
        ->introMessage->toBe('<span>HTML intro message</span>');
});
