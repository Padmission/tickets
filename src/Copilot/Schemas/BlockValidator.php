<?php

namespace Padmission\Tickets\Copilot\Schemas;

use InvalidArgumentException;

class BlockValidator
{
    /**
     * @var array<string, array<int, string>>
     */
    protected array $requiredProps = [
        'Lede' => ['text'],
        'HeadlineBand' => ['label', 'value'],
        'KVBlock' => ['rows'],
        'DefinitionCard' => ['term', 'definition'],
        'StepList' => ['steps'],
        'Callout' => ['tone'],
        'DiffRow' => ['field', 'from', 'to'],
        'AuditTrail' => ['entries'],
        'SourceCitation' => ['title'],
        'Meta' => [],
    ];

    public function assert(array $block): void
    {
        $kind = $block['kind'] ?? null;

        if (! is_string($kind) || ! array_key_exists($kind, $this->requiredProps)) {
            throw new InvalidArgumentException('Unknown block kind.');
        }

        $props = $block['props'] ?? null;

        if (! is_array($props)) {
            throw new InvalidArgumentException('Block props must be an object.');
        }

        foreach ($this->requiredProps[$kind] as $prop) {
            if (! array_key_exists($prop, $props)) {
                throw new InvalidArgumentException("Missing required prop [{$prop}] for [{$kind}].");
            }
        }

        if ($kind === 'Meta') {
            $confidence = $props['confidence'] ?? null;

            if ($confidence !== null && (! is_numeric($confidence) || $confidence < 0 || $confidence > 1)) {
                throw new InvalidArgumentException('Meta confidence must be between 0 and 1.');
            }
        }
    }
}
