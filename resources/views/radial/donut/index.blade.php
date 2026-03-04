@props([
    'data' => [],
    'label' => null,
    'value' => null,
    'hover' => null,
    'hoverLabel' => null,
    'legend' => false,
    'tooltip' => true,
    'cutout' => 70,
    'static' => false,
    'size' => '13rem',
])

@php
    $radius = 80;
    $total = array_sum(array_column($data, 'value'));
    $displayValue = $value ?? $total;

    // Clamp cutout between 0 and 95
    $cutoutClamped = max(0, min(95, $cutout));
    $thickness = (int) round($radius * (1 - $cutoutClamped / 100));
    $hoverThickness = $thickness + 2; // Reduced from 4 for smaller overall size
    $innerRadius = max(0, $radius - $thickness);

    $circumference = 2 * M_PI * $radius;
    $segmentCount = count(array_filter($data, fn ($item) => $item['value'] > 0));
    $gap = $segmentCount > 1 ? 0.5 : 0;
    $runningOffset = 0;

    $segments = [];
    foreach ($data as $item) {
        $length = $total > 0 ? ($item['value'] / $total) * $circumference : 0;
        $visibleLength = max(0, $length - $gap);

        $segments[] = [
            'label' => $item['label'],
            'value' => $item['value'],
            'class' => $item['class'] ?? '',
            'dasharray' => $visibleLength.' '.($circumference - $visibleLength),
            'dashoffset' => -($runningOffset + $gap / 2),
            'percentage' => $total > 0 ? round(($item['value'] / $total) * 100, 1) : 0,
        ];

        $runningOffset += $length;
    }

    // Same viewBox as pie (188) so both charts render at identical size in same container.
    $viewBoxSize = 188;
    $center = $viewBoxSize / 2;

    $legendPosition = is_string($legend) ? $legend : null;
    $showLegend = $legend !== false;
    $showLabel = filled($label);
    $hasHover = filled($hover);
    $hasHoverLabel = filled($hoverLabel);

    $isHorizontalLegend = in_array($legendPosition, ['left', 'right']);
    $isVerticalLegend = in_array($legendPosition, ['top', 'bottom']);

    $chartSize = $size;
@endphp

@php
    $containerClass = match (true) {
        $isHorizontalLegend => 'relative flex flex-row items-center justify-center gap-4 min-w-0 w-fit max-w-full mx-auto',
        $isVerticalLegend => 'relative flex flex-col items-center min-w-0 min-h-0 w-fit max-w-full mx-auto',
        default => 'relative min-w-0 w-fit max-w-full mx-auto',
    };
@endphp

<div
    {{ $attributes->merge(['class' => $containerClass]) }}
    x-data="{
        hovered: null,
        centerHovered: false,
        showTooltip: false,
        tooltipX: 0,
        tooltipY: 0,
        isStatic: @js($static),
        displayValue: @js($displayValue),
        label: @js($label ?? ''),
        hover: @js($hover ?? ''),
        hoverLabel: @js($hoverLabel ?? ''),
        hasHover: @js($hasHover),
        hasHoverLabel: @js($hasHoverLabel),
        segments: @js($segments),

        get currentValue() {
            if (this.centerHovered && this.hasHover) {
                return this.hover;
            }
            return this.hovered !== null ? this.segments[this.hovered].value : this.displayValue;
        },

        get currentLabel() {
            if (this.centerHovered && this.hasHoverLabel) {
                return this.hoverLabel;
            }
            return this.hovered !== null ? this.segments[this.hovered].label : this.label;
        },

        handleHover(event, index) {
            console.log('handleHover called', { isStatic: this.isStatic, index });
            if (this.isStatic) return;

            this.hovered = index;
            this.showTooltip = true;
            this.$nextTick(() => this.positionTooltip(event));
        },

        positionTooltip(event) {
            const container = this.$refs.container.getBoundingClientRect();
            const tooltip = this.$refs.tooltip;

            if (!tooltip) return;

            const tooltipRect = tooltip.getBoundingClientRect();
            const mouseX = event.clientX - container.left;
            const mouseY = event.clientY - container.top;

            const overflowsRight = container.width - (mouseX + tooltipRect.width + 15) < 0;
            const overflowsBottom = container.height - (mouseY + tooltipRect.height + 15) < 0;

            this.tooltipX = overflowsRight ? mouseX - tooltipRect.width - 15 : mouseX + 15;
            this.tooltipY = overflowsBottom ? mouseY - tooltipRect.height - 15 : mouseY + 15;
        },

        handleLeave() {
            if (this.isStatic) return;

            this.hovered = null;
            this.showTooltip = false;
            this.centerHovered = false;
        },

        handleCenterEnter() {
            if (! this.isStatic) {
                this.handleLeave();
            }
            
            if (this.hasHover) {
                this.centerHovered = true;
            }
        },

        handleCenterLeave() {
            this.centerHovered = false;
        },

        handleTap(event, index) {
            if (this.isStatic) return;

            if (this.hovered === index) {
                this.handleLeave();
                return;
            }

            this.handleHover(event.touches[0], index);
        },

        handleOutsideTap() {
            if (!this.isStatic && this.hovered !== null) {
                this.handleLeave();
            }
        },
    }"
>
    @if ($showLegend && $legendPosition === 'left')
        <div class="flex flex-col gap-1 shrink-0 order-first pr-4">
            @foreach ($segments as $i => $segment)
                <radial:legend :index="$i" :class="$segment['class']" :label="$segment['label']" />
            @endforeach
        </div>
    @elseif ($showLegend && $legendPosition === 'top')
        <div class="flex flex-wrap justify-center gap-x-4 gap-y-1 pb-4 w-full order-first">
            @foreach ($segments as $i => $segment)
                <radial:legend :index="$i" :class="$segment['class']" :label="$segment['label']" />
            @endforeach
        </div>
    @endif

    <div
        class="flex justify-center items-center aspect-square {{ $isVerticalLegend ? 'min-h-0 min-w-0 flex-shrink' : 'shrink-0' }}"
        @if ($showLegend)
            style="width: {{ $chartSize }}; {{ $isHorizontalLegend ? 'max-width: min(100%, calc(100% - 7rem));' : 'max-width: 100%; max-height: min(100%, calc(100% - 7rem));' }}"
        @else
            style="width: {{ $chartSize }}; max-width: 100%;"
        @endif
        x-ref="container"
    >
        <svg
            viewBox="0 0 {{ $viewBoxSize }} {{ $viewBoxSize }}"
            class="w-full h-full block"
            preserveAspectRatio="xMidYMid meet"
            @touchstart.self="handleOutsideTap()"
        >
            @foreach ($segments as $i => $segment)
                <circle
                    cx="{{ $center }}"
                    cy="{{ $center }}"
                    r="{{ $radius }}"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="{{ $thickness }}"
                    stroke-dasharray="{{ $segment['dasharray'] }}"
                    stroke-dashoffset="{{ $segment['dashoffset'] }}"
                    stroke-linecap="butt"
                    transform="rotate(-90 {{ $center }} {{ $center }})"
                    class="{{ $segment['class'] }} transition-all duration-150"
                    x-bind:style="{ opacity: hovered === {{ $i }} ? 1 : (hovered !== null ? 0.3 : 1) }"
                    :stroke-width="hovered === {{ $i }} ? {{ $hoverThickness }} : {{ $thickness }}"
                    @mouseenter="handleHover($event, {{ $i }})"
                    @mousemove="handleHover($event, {{ $i }})"
                    @mouseleave="handleLeave()"
                    @touchstart.prevent="handleTap($event, {{ $i }})"
                />
            @endforeach

            @if ($innerRadius > 0 && (! $static || $hasHover))
                <circle
                    cx="{{ $center }}"
                    cy="{{ $center }}"
                    r="{{ $innerRadius }}"
                    fill="transparent"
                    @mouseenter="handleCenterEnter()"
                    @mouseleave="handleCenterLeave()"
                />
            @endif

            <g class="text-zinc-900 dark:text-white" style="fill: currentColor;">
                <text
                    x="{{ $center }}"
                    y="{{ $showLabel ? $center - 6 : $center }}"
                    text-anchor="middle"
                    dominant-baseline="{{ $showLabel ? 'auto' : 'middle' }}"
                    class="pointer-events-none font-semibold"
                    style="font-size: 18px;"
                >
                    <tspan x="{{ $center }}" x-text="currentValue.toLocaleString()">{{ number_format($displayValue) }}</tspan>
                </text>
            </g>

            @if ($showLabel)
                <g class="text-zinc-500 dark:text-zinc-300" style="fill: currentColor;">
                    <text
                        x="{{ $center }}"
                        y="{{ $center + 14 }}"
                        text-anchor="middle"
                        dominant-baseline="auto"
                        class="pointer-events-none"
                        style="font-size: 10px;"
                    >
                        <tspan x="{{ $center }}" x-text="currentLabel">{{ $label }}</tspan>
                    </text>
                </g>
            @endif

            {{ $slot }}
        </svg>

        @if ($tooltip)
            <div
                x-ref="tooltip"
                x-show="showTooltip"
                x-cloak
                x-transition.opacity.duration.100ms
                class="absolute z-10 pointer-events-none rounded-lg shadow-lg border border-zinc-200 bg-white dark:border-zinc-500 dark:bg-zinc-700 overflow-hidden"
                :style="`left: ${tooltipX}px; top: ${tooltipY}px`"
            >
                <template x-if="hovered !== null">
                    <div>
                        <div class="flex items-center justify-between p-2 text-xs font-medium text-zinc-800 dark:text-zinc-100 bg-zinc-50 border-b border-zinc-200 dark:bg-zinc-600 dark:border-zinc-500">
                            <span x-text="segments[hovered].label"></span>
                        </div>

                        <div class="flex items-center gap-2 p-2 text-xs text-zinc-500 dark:text-zinc-300">
                            <template x-for="(segment, i) in segments" :key="i">
                                <div x-show="hovered === i" :class="segment.class">
                                    <div class="size-2.5 rounded-full" style="background-color: currentColor"></div>
                                </div>
                            </template>
                            <span x-text="segments[hovered].label"></span>
                            <div class="flex-1"></div>
                            <span x-text="segments[hovered].value.toLocaleString()"></span>
                            <span class="text-zinc-400 dark:text-zinc-500 ml-1" x-text="`(${segments[hovered].percentage}%)`"></span>
                        </div>
                    </div>
                </template>
            </div>
        @endif
    </div>

    @if ($showLegend && $legendPosition === 'right')
        <div class="flex flex-col gap-1 shrink-0 pl-4">
            @foreach ($segments as $i => $segment)
                <radial:legend :index="$i" :class="$segment['class']" :label="$segment['label']" />
            @endforeach
        </div>
    @elseif ($showLegend && $legendPosition === 'bottom')
        <div class="flex flex-wrap justify-center gap-x-4 gap-y-1 pt-4 w-full">
            @foreach ($segments as $i => $segment)
                <radial:legend :index="$i" :class="$segment['class']" :label="$segment['label']" />
            @endforeach
        </div>
    @endif
</div>
