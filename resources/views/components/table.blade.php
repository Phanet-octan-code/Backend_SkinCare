@props([
    'headers' => [],
    'striped' => false,
    'hover' => true,
    'small' => false,
    'noMargin' => false,
    'card' => true
])

@php
    $tableClasses = 'table align-middle mb-0 table-skincare';
    if ($striped) $tableClasses .= ' table-striped';
    if ($hover) $tableClasses .= ' table-hover';
    if ($small) $tableClasses .= ' table-sm';
@endphp

@if($card)
<div class="card-skincare overflow-hidden {{ $noMargin ? 'mb-0' : 'mb-4' }}">
    @if(isset($cardHeader))
    <div class="card-header-skincare">
        {{ $cardHeader }}
    </div>
    @endif

    <div class="table-responsive">
        <table {{ $attributes->merge(['class' => $tableClasses]) }}>
            @if(count($headers) > 0 || isset($thead))
            <thead>
                @if(isset($thead))
                    {{ $thead }}
                @else
                    <tr>
                        @foreach($headers as $header)
                            @if(is_array($header))
                                <th class="{{ $header['class'] ?? '' }}" style="{{ $header['style'] ?? '' }}">
                                    {{ $header['label'] ?? '' }}
                                </th>
                            @else
                                <th>{{ $header }}</th>
                            @endif
                        @endforeach
                    </tr>
                @endif
            </thead>
            @endif

            <tbody>
                {{ $slot }}
            </tbody>
        </table>
    </div>

    @if(isset($empty) && (!isset($hasRows) || !$hasRows))
        {{ $empty }}
    @endif

    @if(isset($pagination))
    <div class="p-3 border-top bg-light d-flex align-items-center justify-content-between flex-wrap gap-2" style="border-color: var(--border-color) !important;">
        {{ $pagination }}
    </div>
    @endif
</div>
@else
<div class="table-responsive">
    <table {{ $attributes->merge(['class' => $tableClasses]) }}>
        @if(count($headers) > 0 || isset($thead))
        <thead>
            @if(isset($thead))
                {{ $thead }}
            @else
                <tr>
                    @foreach($headers as $header)
                        @if(is_array($header))
                            <th class="{{ $header['class'] ?? '' }}" style="{{ $header['style'] ?? '' }}">
                                {{ $header['label'] ?? '' }}
                            </th>
                        @else
                            <th>{{ $header }}</th>
                        @endif
                    @endforeach
                </tr>
            @endif
        </thead>
        @endif

        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>
@endif
