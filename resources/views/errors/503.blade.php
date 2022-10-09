<x-error-layout>
    <x-slot name="title">
        Maintenance
    </x-slot>

    <x-slot name="error_code">
        503
    </x-slot>

    <x-slot name="context">
        Maintenance mode enabled
    </x-slot>

    <x-slot name="admin_helptext">
        Try running "<code>php artisan up</code>". If that didn't help, you might want to ask for help in our Discord server.
    </x-slot>

    <x-slot name="admin_action_text">
        Join our Discord server
    </x-slot>

    <x-slot name="admin_action_link">
        https://fossbilling.org/discord
    </x-slot>
</x-error-layout>