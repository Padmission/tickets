<?php

use Padmission\Tickets\Tests\TestCase;
use Tiptap\Editor;

uses(TestCase::class)->in('Feature');
uses(TestCase::class)->in('Unit');

function tiptapDocument(string $html): array
{
    return (new Editor)
        ->setContent($html)
        ->getDocument();
}
