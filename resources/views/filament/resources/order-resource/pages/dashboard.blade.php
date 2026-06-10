<x-filament-panels::page>
    {{-- الفلاتر العامة للصفحة --}}
    <x-filament::card class="mb-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <x-filament::input.wrapper>
                <x-filament::input
                    type="date"
                    wire:model.live="date_from"
                    placeholder="من تاريخ"
                />
            </x-filament::input.wrapper>
            <x-filament::input.wrapper>
                <x-filament::input
                    type="date"
                    wire:model.live="date_to"
                    placeholder="إلى تاريخ"
                />
            </x-filament::input.wrapper>
            <x-filament::input.wrapper>
                <select wire:model.live="status" class="w-full rounded-lg border-gray-300">
                    <option value="">جميع الحالات</option>
                    <option value="pending">قيد الانتظار</option>
                    <option value="completed">مكتمل</option>
                    <option value="cancelled">ملغي</option>
                </select>
            </x-filament::input.wrapper>
            <x-filament::input.wrapper>
                <select wire:model.live="interval" class="w-full rounded-lg border-gray-300">
                    <option value="day">يومي</option>
                    <option value="week">أسبوعي</option>
                    <option value="month">شهري</option>
                </select>
            </x-filament::input.wrapper>
        </div>
    </x-filament::card>

    {{-- الـ Widgets ستقرأ المتغيرات العامة --}}
    @livewire(\App\Filament\Widgets\OrderTrendChart::class, ['filters' => ['date_from' => $date_from ?? null, 'date_to' => $date_to ?? null, 'status' => $status ?? null, 'interval' => $interval ?? null]])
    @livewire(\App\Filament\Widgets\PeriodComparisonChart::class, ['filters' => ['date_from' => $date_from ?? null, 'date_to' => $date_to ?? null, 'status' => $status ?? null]])
    {{-- أضف باقي الـ Widgets بنفس الطريقة --}}
</x-filament-panels::page>