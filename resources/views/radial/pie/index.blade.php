@props([
    'data' => [],
    'legend' => false,
    'tooltip' => true,
    'static' => false,
    'size' => '13rem',
])

@php
    // Match donut's visible outer edge: donut stroke is centered on r=80, so outer edge = 80 + thickness/2.
    // Default donut thickness (70% cutout) = 24, so outer edge at 92. Draw pie at 92 so sizes match.
    $radius = 92;
    $total = array_sum(array_column($data, 'value'));
    
    $viewBoxSize = 188;
    $center = $viewBoxSize / 2;

    $runningAngle = 0;

    $segments = [];
    foreach ($data as $item) {
        $percentage = $total > 0 ? ($item['value'] / $total) : 0;
        $angle = $percentage * 360;
        
        $startAngle = $runningAngle;
        $endAngle = $runningAngle + $angle;
        
        $startRadians = deg2rad($startAngle - 90);
        $endRadians = deg2rad($endAngle - 90);
        
        $cx = $center;
        $cy = $center;
        
        $x1 = $cx + ($radius * cos($startRadians));
        $y1 = $cy + ($radius * sin($startRadians));
        $x2 = $cx + ($radius * cos($endRadians));
        $y2 = $cy + ($radius * sin($endRadians));
        
        $largeArc = $angle > 180 ? 1 : 0;
        
        $path = "M {$cx} {$cy} L {$x1} {$y1} A {$radius} {$radius} 0 {$largeArc} 1 {$x2} {$y2} Z";

        $segments[] = [
            'label' => $item['label'],
            'value' => $item['value'],
            'class' => $item['class'] ?? '',
            'path' => $path,
            'percentage' => $total > 0 ? round($percentage * 100, 1) : 0,
        ];

        $runningAngle += $angle;
    }

    $legendPosition = is_string($legend) ? $legend : null;
    $showLegend = $legend !== false;

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
        showTooltip: false,
        tooltipX: 0,
        tooltipY: 0,
        isStatic: @js($static),
        segments: @js($segments),

        handleHover(event, index) {
            console.log('pie handleHover called', { isStatic: this.isStatic, index });
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
                <path
                    d="{{ $segment['path'] }}"
                    fill="currentColor"
                    class="{{ $segment['class'] }} transition-all duration-150"
                    x-bind:style="{ opacity: hovered === {{ $i }} ? 1 : (hovered !== null ? 0.3 : 1) }"
                    @mouseenter="handleHover($event, {{ $i }})"
                    @mousemove="handleHover($event, {{ $i }})"
                    @mouseleave="handleLeave()"
                    @touchstart.prevent="handleTap($event, {{ $i }})"
                />
            @endforeach

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
