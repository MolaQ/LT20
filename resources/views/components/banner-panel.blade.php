{{--
    Banery partnerów w prawej kolumnie (na telefonie w menu „Więcej”), format 300×250: zmieniają się same,
    cała grafika i tekst pod nią prowadzą do partnera przez licznik kliknięć. Brak kampanii = nic się nie pokazuje.
--}}
@php
    $campaigns = \App\Models\BannerCampaign::forSlot(\App\Enums\BannerSlot::Panel);
@endphp

@if ($campaigns->isNotEmpty())
    <section class="overflow-hidden rounded-2xl border border-ui-line bg-ui-surface"
        x-data="bannerRotator(@js(['slides' => $campaigns->map(fn ($c) => ['id' => $c->id])->values(), 'viewsUrl' => route('banner.views'), 'token' => csrf_token()]))"
        @mouseenter="paused = true" @mouseleave="paused = false" @focusin="paused = true" @focusout="paused = false">
        <header class="flex items-center justify-between gap-2 px-4 pb-2 pt-3">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-ui-muted">{{ __('Partners') }}</h2>
            @if ($campaigns->count() > 1)
                <span class="flex gap-1">
                    @foreach ($campaigns as $i => $campaign)
                        <button type="button" @click="go({{ $i }})" class="size-2 rounded-full bg-ui-line transition"
                            :class="index === {{ $i }} && '!bg-club'" aria-label="{{ __('Banner :number', ['number' => $i + 1]) }}"></button>
                    @endforeach
                </span>
            @endif
        </header>
        <div class="grid">
            @foreach ($campaigns as $i => $campaign)
                <a href="{{ route('banner.click', $campaign) }}" target="_blank" rel="noopener sponsored"
                    @class(['group col-start-1 row-start-1 block transition-opacity duration-700 motion-reduce:transition-none', 'opacity-0 pointer-events-none' => $i > 0])
                    :class="index === {{ $i }} ? 'opacity-100' : 'opacity-0 pointer-events-none'"
                    @if ($i > 0) aria-hidden="true" @endif :aria-hidden="index !== {{ $i }}">
                    <img src="{{ $campaign->imageUrl() }}" alt="{{ $campaign->name }}" class="aspect-[6/5] w-full object-cover" @if ($i > 0) loading="lazy" @endif>
                    <span class="flex items-center gap-2 px-4 py-3">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-ui-ink group-hover:text-club">{{ $campaign->title ?: $campaign->name }}</span>
                            @if (filled($campaign->title))
                                <span class="block truncate text-xs text-ui-muted">{{ $campaign->name }}</span>
                            @endif
                        </span>
                        <x-ui.icon name="arrow-right" variant="micro" class="text-ui-muted group-hover:text-club" />
                    </span>
                </a>
            @endforeach
        </div>
    </section>
@endif
