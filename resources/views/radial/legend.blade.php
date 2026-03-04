@props([
    'index' => 0,
    'label' => '',
])

<div
    {{ $attributes->merge(['class' => 'flex items-center gap-2 p-2 cursor-default']) }}
    @mouseenter="hovered = {{ $index }}"
    @mouseleave="hovered = null"
    @touchstart.prevent="hovered === {{ $index }} ? hovered = null : hovered = {{ $index }}"
>
    <div class="size-2.5 rounded-full" style="background-color: currentColor"></div>
    <div class="text-xs text-zinc-500 dark:text-zinc-300">{{ $label }}</div>
</div>
