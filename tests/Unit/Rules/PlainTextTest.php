<?php

use Padmission\Tickets\Rules\PlainText;

it('finds markup in plain text', function (string $text, bool $hasMarkup) {
    expect(PlainText::hasMarkup($text))->toBe($hasMarkup);
})->with([
    'a tag' => ['<b>Rent</b> is wrong', true],
    'a script' => ['<script>alert(1)</script>', true],
    'an event handler in a tag' => ['<img src=x onerror=alert(1)>', true],
    'a closing tag' => ['Rent is wrong</div>', true],
    'a comment' => ['Rent <!-- note -->', true],
    'a script URL' => ['Open javascript:alert(1)', true],
    'a data URL' => ['data:text/html;base64,PHNjcmlwdD4=', true],
    'a less-than sign before a space' => ['Rent < 200', false],
    'a less-than sign before a number' => ['Rent <200 since March', false],
    'arrows' => ['Rent -> utilities <- deposit', false],
    'the word data before a colon' => ['Missing data: rent roll', false],
    'an event name in words' => ['The onclick= handler in the error', false],
]);

it('removes markup from text nobody typed, keeping the rest', function (string $text, string $clean) {
    expect(PlainText::clean($text))->toBe($clean);
})->with([
    'tags' => ['<b>Rent</b> is <i>wrong</i>', 'Rent is wrong'],
    'a script, whose text is left harmless' => ['Help <script>alert(1)</script> please', 'Help alert(1) please'],
    'a script URL' => ['Open javascript:alert(1)', 'Open alert(1)'],
    'a less-than sign that starts no tag' => ['Rent < 200', 'Rent < 200'],
]);
