<x-filament-panels::page>
    <form
        wire:submit="applyFilters"
        class="space-y-6"
    >
        <div class="grid gap-4 md:grid-cols-4">
            <x-filament::input.wrapper>
                <x-filament::input.select
                    wire:model="siteId"
                    aria-label="{{ __('capell-referer::report.site') }}"
                >
                    @foreach ($sites as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>

            <x-filament::input.wrapper>
                <x-filament::input.select
                    wire:model.live="preset"
                    aria-label="{{ __('capell-referer::report.window') }}"
                >
                    @foreach ($presetOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>

            <x-filament::input.wrapper :hidden="$preset !== 'custom'">
                <x-filament::input
                    type="date"
                    wire:model="startsOn"
                    aria-label="{{ __('capell-referer::report.starts_on') }}"
                />
            </x-filament::input.wrapper>

            <x-filament::input.wrapper :hidden="$preset !== 'custom'">
                <x-filament::input
                    type="date"
                    wire:model="endsOn"
                    aria-label="{{ __('capell-referer::report.ends_on') }}"
                />
            </x-filament::input.wrapper>
        </div>

        <div class="flex gap-2">
            <x-filament::button
                type="submit"
                >{{ __('capell-referer::report.apply') }}</x-filament::button
            >
            <x-filament::button
                type="button"
                color="gray"
                wire:click="refreshReport"
                >{{ __('capell-referer::report.refresh') }}</x-filament::button
            >
        </div>

        @error('siteId')
            <p class="text-sm text-danger-600" role="alert">{{ $message }}</p>
        @enderror
        @error('preset')
            <p class="text-sm text-danger-600" role="alert">{{ $message }}</p>
        @enderror
        @error('startsOn')
            <p class="text-sm text-danger-600" role="alert">{{ $message }}</p>
        @enderror
        @error('endsOn')
            <p class="text-sm text-danger-600" role="alert">{{ $message }}</p>
        @enderror
    </form>

    @if ($report instanceof \Capell\Referer\Data\RefererReportData && ! $report->available)
        <x-filament::section>
            <p class="text-sm text-gray-600 dark:text-gray-300">{{ __('capell-referer::report.unavailable') }}</p>
        </x-filament::section>
    @elseif ($report instanceof \Capell\Referer\Data\RefererReportData && $report->isEmpty())
        <x-filament::section>
            <p class="text-sm text-gray-600 dark:text-gray-300">{{ __('capell-referer::report.empty') }}</p>
        </x-filament::section>
    @elseif ($report instanceof \Capell\Referer\Data\RefererReportData)
        <x-filament::section :heading="__('capell-referer::report.results')">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left">
                            <th class="px-3 py-2">
                                {{ __('capell-referer::report.source') }}
                            </th>
                            <th class="px-3 py-2">
                                {{ __('capell-referer::report.requests') }}
                            </th>
                            <th class="px-3 py-2">
                                {{ __('capell-referer::report.share') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($reportRows as $row)
                            <tr class="border-b last:border-0">
                                <td class="px-3 py-2">{{ $row->label }}</td>
                                <td class="px-3 py-2">
                                    {{ number_format($row->count) }}
                                </td>
                                <td class="px-3 py-2">
                                    {{ number_format($row->share, 1) }}%
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-4 text-xs text-gray-500">
                {{ __('capell-referer::report.measured', ['count' => number_format($report->measuredRequests)]) }} {{ __('capell-referer::report.utc') }}
            </p>
            <p class="mt-2 text-xs text-gray-500">{{ __('capell-referer::report.coverage') }}</p>
            @if ($report->collectionStartedOn instanceof \Carbon\CarbonImmutable)
                <p class="mt-2 text-xs text-gray-500">{{ __('capell-referer::report.collection_started', ['date' => $report->collectionStartedOn->toDateString()]) }}</p>
            @endif
            @if ($report->availableThroughOn instanceof \Carbon\CarbonImmutable)
                <p class="mt-2 text-xs text-gray-500">{{ __('capell-referer::report.available_through', ['date' => $report->availableThroughOn->toDateString()]) }}</p>
            @endif
            @if ($reportPages > 1)
                <div class="mt-4 flex items-center justify-between">
                    <x-filament::button
                        type="button"
                        color="gray"
                        :disabled="$reportPage <= 1"
                        wire:click="$set('reportPage', {{ max(1, $reportPage - 1) }})"
                    >
                        {{ __('capell-referer::report.previous_page') }}
                    </x-filament::button>
                    <span class="text-xs text-gray-500"
                        >{{ $reportPage }} / {{ $reportPages }}</span
                    >
                    <x-filament::button
                        type="button"
                        color="gray"
                        :disabled="$reportPage >= $reportPages"
                        wire:click="$set('reportPage', {{ min($reportPages, $reportPage + 1) }})"
                    >
                        {{ __('capell-referer::report.next_page') }}
                    </x-filament::button>
                </div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
