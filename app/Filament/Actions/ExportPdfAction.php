<?php

namespace App\Filament\Actions;

use Barryvdh\Snappy\Facades\SnappyPdf;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Response;

class ExportPdfAction extends Action
{
    public static function make(string $name = 'export_pdf'): static
    {
        return parent::make($name)
            ->label('تصدير إلى PDF')
            ->icon('heroicon-o-document-text')
            ->color('danger')
            ->action(function (Collection $records, $livewire) {
                $data = $records->isNotEmpty() ? $records : $livewire->getFilteredTableQuery()->get();
                
                $html = view('exports.orders-pdf', [
                    'orders' => $data,
                ])->render();
                
                $pdf = SnappyPdf::loadHTML($html);
                return Response::make($pdf->output(), 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="orders.pdf"',
                ]);
            });
    }
}