<?php

it('defines dark mode tokens for the support panel and admin activity thread', function () {
    $css = file_get_contents(__DIR__.'/../../../resources/css/tickets.css');

    expect($css)
        ->toContain(':is(.dark, .fi-dark, [data-theme="dark"]) .sf-shell')
        ->toContain(':is(.dark, .fi-dark, [data-theme="dark"]) .sf-admin-thread')
        ->toContain('.sf-shell.dark')
        ->toContain('--pm-bg: #111827')
        ->toContain('--pm-text: #f8fafc')
        ->toContain('--pm-border: #334155')
        ->toContain(':is(.dark, .fi-dark, [data-theme="dark"]) .sf-backdrop')
        ->toContain(':is(.dark, .fi-dark, [data-theme="dark"]) .sf-topbtn');
});

it('uses support color tokens instead of hard-coded light panel surfaces', function () {
    $css = file_get_contents(__DIR__.'/../../../resources/css/tickets.css');

    expect($css)
        ->toContain(".sf-record {\n\tpadding: .8rem 1rem .9rem;\n\tborder-bottom: 1px solid var(--pm-border);\n\tbackground: var(--pm-s-50);")
        ->toContain(".sf-record__edit select,\n.sf-record__edit input {")
        ->toContain("\tbackground: var(--pm-bg);\n\tpadding: .25rem .45rem;")
        ->toContain(".sf-inputrow {\n\tdisplay: flex;\n\talign-items: flex-end;\n\tgap: .5rem;\n\tborder: 1px solid var(--pm-border-strong);\n\tborder-radius: .375rem;\n\tpadding: .45rem;\n\tbackground: var(--pm-bg);")
        ->toContain(".sf-inputrow textarea {\n\tflex: 1;\n\tmin-height: 2rem;");
});

it('keeps the discussing record strip dark in dark mode', function () {
    $css = file_get_contents(__DIR__.'/../../../resources/css/tickets.css');

    expect($css)
        ->toContain(":is(.dark, .fi-dark, [data-theme=\"dark\"]) .sf-record,\n.sf-shell.dark .sf-record {\n\tbackground: #111827;")
        ->toContain(':is(.dark, .fi-dark, [data-theme="dark"]) .sf-record__title')
        ->toContain(':is(.dark, .fi-dark, [data-theme="dark"]) .sf-record__sub');
});
