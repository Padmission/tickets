<?php

use Padmission\Tickets\Copilot\Schemas\BlockValidator;

it('accepts every support block shape', function (array $block) {
    app(BlockValidator::class)->assert($block);

    expect(true)->toBeTrue();
})->with([
    'lede' => [['kind' => 'Lede', 'props' => ['text' => 'Short answer.']]],
    'headline' => [['kind' => 'HeadlineBand', 'props' => ['label' => 'HAP', 'value' => '$1,250']]],
    'kv' => [['kind' => 'KVBlock', 'props' => ['rows' => [['label' => 'Status', 'value' => 'Open']]]]],
    'definition' => [['kind' => 'DefinitionCard', 'props' => ['term' => 'RFTA', 'definition' => 'Request for tenancy approval.']]],
    'steps' => [['kind' => 'StepList', 'props' => ['steps' => [['title' => 'Open', 'body' => 'Open the record.']]]]],
    'callout' => [['kind' => 'Callout', 'props' => ['tone' => 'warn', 'body' => 'Check this first.']]],
    'diff' => [['kind' => 'DiffRow', 'props' => ['field' => 'HAP', 'from' => '$1', 'to' => '$2']]],
    'audit' => [['kind' => 'AuditTrail', 'props' => ['entries' => [['summary' => 'Changed HAP.']]]]],
    'source' => [['kind' => 'SourceCitation', 'props' => ['title' => 'RFTA docs']]],
    'meta' => [['kind' => 'Meta', 'props' => ['confidence' => 0.8, 'escalation_reason' => null]]],
]);

it('rejects invalid support block shapes', function () {
    app(BlockValidator::class)->assert(['kind' => 'StepList', 'props' => []]);
})->throws(InvalidArgumentException::class);
