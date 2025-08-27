@php
    use Filament\Facades\Filament;

    $user = $getState();

    if (! $user) {
        return;
    }

    $avatarUrl = Filament::getUserAvatarUrl($user);
    $name = Filament::getUserName($user);
@endphp
<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    <div class="avatar-entry">
        <x-filament::avatar
            :src="$avatarUrl"
            :name="$name"
            size="sm"
        />

        <span>
            {{ $name }}
        </span>
    </div>
</x-dynamic-component>
