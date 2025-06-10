<x-padmission-tickets::chat-widget />
@if ($primaryColor)
    <style>
        chat-widget {
            --color-primary: rgb({{ $primaryColor['600'] }});
        }
    </style>
@endif
