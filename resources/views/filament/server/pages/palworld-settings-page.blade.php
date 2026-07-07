{{--
    Palworld settings page view.

    This is a copy of Pelican's native `filament.server.pages.server-form-page`
    view with ONE deliberate difference: it does NOT put `wire:submit="save"` on
    the page wrapper. Everything else (the `id` and the root `wire:key`) is kept
    identical to the native view so Livewire's DOM morphing — and therefore the
    shared action-modal container's Alpine state — stays stable across re-renders.

    Why drop wire:submit
    --------------------
    The native view binds `wire:submit="save"` to the page's root element, which
    is an ancestor of every action modal (Filament renders them centrally via
    `<x-filament-actions::modals />` inside `<x-filament-panels::page>`). Each of
    those modals is itself a `<form wire:submit.prevent="callMountedAction(...)">`.
    `.prevent` blocks the default submit but NOT its propagation, so confirming
    ANY action modal (Apply preset, Reset, Restart, Restore, Delete) let the
    `submit` event bubble up to the page-level `wire:submit="save"` and fire it.
    The Save action writes to disk with no confirmation of its own, so a stray
    bubbled submit would silently trigger an immediate write — dropping the
    page-level `wire:submit` prevents that.

    Saving is driven entirely by the "Save" header action — clicking it, or its
    `mod+s` key binding, runs the write directly — so no page-level form submit is
    needed here. Keeping the `wire:key` (which the native view sets) is important:
    dropping it destabilises morphing and makes confirmation modals intermittently
    fail to open after a re-render.
--}}
<x-filament-panels::page
    id="form"
    :wire:key="$this->getId() . '.forms.' . $this->getFormStatePath()"
>
    {{ $this->form }}
</x-filament-panels::page>
