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
    <div class="flex gap-2">
        <x-filament::avatar
            :src="$avatarUrl"
            :name="$name"
            size="sm"
        />

        <div class="text-sm leading-6 text-gray-950 dark:text-white">
            {{ $name }}
        </div>
    </div>
</x-dynamic-component>
