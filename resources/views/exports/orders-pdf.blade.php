<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تقرير الطلبات</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; direction: rtl; }
        .header { text-align: center; margin-bottom: 20px; }
        .logo-placeholder { 
            width: 80px; height: 80px; margin: 0 auto 10px auto; 
            background: #3490dc; border-radius: 50%; 
            display: flex; align-items: center; justify-content: center; 
            color: white; font-size: 14px; font-weight: bold; 
        }
        h2 { margin: 5px 0; color: #333; }
        .subtitle { color: #555; font-size: 14px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #aaa; padding: 8px; text-align: center; vertical-align: top; }
        th { background-color: #f2f2f2; }
        .order-details { margin-bottom: 10px; background: #f9f9f9; padding: 8px; text-align: right; }
        .stage-time { font-size: 10px; color: #2c3e50; }
        footer { text-align: center; margin-top: 30px; font-size: 10px; color: #777; }
    </style>
</head>
<body>

<div class="header">
    <div class="logo-placeholder">شعار</div>
    <h2>نظام تتبع الطلبات</h2>
    <div class="subtitle">تقرير تفصيلي بالطلبات وتواريخها وأوقات المراحل</div>
</div>

@foreach($orders as $order)
    <div class="order-details">
        <strong>رقم الطلب: {{ $order->orderId }}</strong> | 
        العميل: {{ $order->customerName }} | 
        تاريخ الطلب: {{ \Carbon\Carbon::parse($order->orderDate)->format('Y-m-d') }}<br>
        الحالة الحالية: {{ $order->currentStatus }} | 
        الوقت الإجمالي: {{ $order->total_time ?? 'لم يكتمل' }}<br>
        منشئ الطلب: {{ $order->user->name ?? 'غير معروف' }}
    </div>

    <table>
        <thead>
            <tr><th>المرحلة</th><th>التاريخ والوقت (Y-m-d H:i:s)</th><th>الوقت المستغرق من المرحلة السابقة</th></tr>
        </thead>
        <tbody>
            @php
                $stages = [
                    'إنشاء الطلب'     => $order->created_at,
                    'طلب موافقة'      => $order->orderForApprove,
                    'تمت الموافقة'    => $order->orderApproved,
                    'طلب دفع'         => $order->orderForPayment,
                    'تحصيل الدفع'     => $order->collectPayment,
                    'موافقة البيع'    => $order->sellApprove,
                    'موافقة الإفراج'  => $order->releaseApprove,
                    'بدء التجهيز'     => $order->startPreparation,
                    'جاهز للتسليم'    => $order->readyToDeliver,
                    'خرج للتسليم'     => $order->outForDeliver,
                    'تم التسليم'      => $order->delivered,
                ];
                $prevTime = null;
            @endphp

            @foreach($stages as $stageName => $stageTime)
                @php
                    $duration = '';
                    if ($prevTime && $stageTime) {
                        $duration = \App\Models\Order::calculateDuration($prevTime, $stageTime);
                    } elseif ($stageTime && !$prevTime && $stageName != 'إنشاء الطلب') {
                        $duration = 'بداية';
                    }
                    $prevTime = $stageTime ?: $prevTime;
                @endphp
                <tr>
                    <td>{{ $stageName }}</td>
                    <td>
                        @if($stageTime)
                            {{ \Carbon\Carbon::parse($stageTime)->format('Y-m-d H:i:s') }}
                        @else
                            <span style="color:gray;">لم تبدأ بعد</span>
                        @endif
                    </td>
                    <td class="stage-time">{{ $duration ?: '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <br><br>
@endforeach

<footer>
    تم إنشاء التقرير بتاريخ {{ now()->format('Y-m-d H:i:s') }} - نظام إدارة الطلبات الاحترافي
</footer>
</body>
</html>