@php
    // In Markdown indented HTML is rendered as code blocks.
    // To make code more readable, this component takes the content
    // and un-indents it for further processing through the Markdown renderer.

    $lines = explode("\n", $slot);
    $lines = array_map(
        fn ($line) => ltrim($line),
        $lines
    );

    $slot = implode("\n", $lines);

    echo $slot;
@endphp

