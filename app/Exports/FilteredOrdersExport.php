<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FilteredOrdersExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $orders;

    public function __construct($orders)
    {
        $this->orders = $orders;
    }

    public function collection()
    {
        return $this->orders;
    }

    public function headings(): array
    {
        return [
            '#', 'اسم العميل', 'رقم الهاتف', 'رقم الطلب (orderId)', 'تاريخ الطلب',
            'طلب الموافقة', 'تمت الموافقة', 'طلب الدفع', 'تحصيل الدفع',
            'موافقة البيع', 'موافقة الإفراج', 'بدء التجهيز', 'جاهز للتسليم',
            'خرج للتسليم', 'تم التسليم', 'الوقت الإجمالي', 'الحالة الحالية',
            'نوع الدفع', 'الإجمالي (LYD)', 'اسم الموظف', 'المنشئ'
        ];
    }

    public function map($order): array
    {
        return [
            $order->id,
            $order->customerName,
            $order->customerContactNumber,
            $order->orderId,
            $order->orderDate ? $order->orderDate->format('d/m/Y H:i:s') : '',
            $order->orderForApprove ? $order->orderForApprove->format('d/m/Y H:i:s') : '',
            $order->orderApproved ? $order->orderApproved->format('d/m/Y H:i:s') : '',
            $order->orderForPayment ? $order->orderForPayment->format('d/m/Y H:i:s') : '',
            $order->collectPayment ? $order->collectPayment->format('d/m/Y H:i:s') : '',
            $order->sellApprove ? $order->sellApprove->format('d/m/Y H:i:s') : '',
            $order->releaseApprove ? $order->releaseApprove->format('d/m/Y H:i:s') : '',
            $order->startPreparation ? $order->startPreparation->format('d/m/Y H:i:s') : '',
            $order->readyToDeliver ? $order->readyToDeliver->format('d/m/Y H:i:s') : '',
            $order->outForDeliver ? $order->outForDeliver->format('d/m/Y H:i:s') : '',
            $order->delivered ? $order->delivered->format('d/m/Y H:i:s') : '',
            $order->total_time ?? '',
            $order->currentStatus,
            $order->priceList,
            number_format($order->total, 2),
            $order->employeeName,
            $order->user->name ?? '',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 12]],
        ];
    }
}