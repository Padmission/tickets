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
        ->introMessage->toBeNull();
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
