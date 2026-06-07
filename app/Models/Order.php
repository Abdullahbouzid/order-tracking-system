<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'customerName', 'customerContactNumber', 'orderId', 'orderDate',
        'priceList', 'total', 'employeeName',
        'orderForApprove', 'orderApproved', 'orderForPayment',
        'collectPayment', 'sellApprove', 'releaseApprove',
        'startPreparation', 'readyToDeliver', 'outForDeliver',
        'delivered', 'currentStatus', 'user_id'
    ];

    protected $casts = [
        'orderDate'          => 'date',
        'total'              => 'decimal:2',
        'orderForApprove'    => 'datetime',
        'orderApproved'      => 'datetime',
        'orderForPayment'    => 'datetime',
        'collectPayment'     => 'datetime',
        'sellApprove'        => 'datetime',
        'releaseApprove'     => 'datetime',
        'startPreparation'   => 'datetime',
        'readyToDeliver'     => 'datetime',
        'outForDeliver'      => 'datetime',
        'delivered'          => 'datetime',
    ];

    // تنظيف القيمة عند التعيين
    public function setAttribute($key, $value)
    {
        if (is_string($value)) {
            $value = $this->cleanUtf8($value);
        }
        return parent::setAttribute($key, $value);
    }

    // تنظيف القيمة عند القراءة
    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);
        if (is_string($value)) {
            $value = $this->cleanUtf8($value);
        }
        return $value;
    }

    // تنظيف النصوص من الأحرف غير الصالحة لـ UTF-8
    protected function cleanUtf8($string)
    {
        $string = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
        $string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $string);
        $string = preg_replace('/^\xEF\xBB\xBF/', '', $string);
        return $string;
    }

    // العلاقة مع المستخدم المنشئ
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // نطاق لتصفية الطلبات حسب صلاحية المستخدم (يعتمد على user_id)
    public function scopeForUser($query, $userId)
    {
        if (auth()->user()->hasRole('super-admin')) {
            return $query;
        }
        return $query->where('user_id', $userId);
    }

    // الوقت الإجمالي من الإنشاء إلى التسليم
    public function getTotalTimeAttribute(): ?string
    {
        if (!$this->delivered) return null;
        return $this->formatMinutes($this->delivered->diffInMinutes($this->created_at));
    }

    // الوقت بين كل مرحلتين
    public function getStageTimesAttribute(): array
    {
        return [
            'إنشاء → طلب موافقة'      => $this->diffInMinutes($this->created_at, $this->orderForApprove),
            'طلب موافقة → موافقة'      => $this->diffInMinutes($this->orderForApprove, $this->orderApproved),
            'موافقة → طلب دفع'         => $this->diffInMinutes($this->orderApproved, $this->orderForPayment),
            'طلب دفع → تحصيل'          => $this->diffInMinutes($this->orderForPayment, $this->collectPayment),
            'تحصيل → موافقة بيع'       => $this->diffInMinutes($this->collectPayment, $this->sellApprove),
            'موافقة بيع → إفراج'        => $this->diffInMinutes($this->sellApprove, $this->releaseApprove),
            'إفراج → بدء تجهيز'         => $this->diffInMinutes($this->releaseApprove, $this->startPreparation),
            'بدء تجهيز → جاهز للتسليم'  => $this->diffInMinutes($this->startPreparation, $this->readyToDeliver),
            'جاهز للتسليم → خرج للتسليم'=> $this->diffInMinutes($this->readyToDeliver, $this->outForDeliver),
            'خرج للتسليم → تم التسليم'   => $this->diffInMinutes($this->outForDeliver, $this->delivered),
        ];
    }

    private function diffInMinutes($start, $end): ?string
    {
        if (!$start || !$end) return 'لم يبدأ';
        $minutes = $start->diffInMinutes($end);
        return $this->formatMinutes($minutes);
    }

    private function formatMinutes(int $minutes): string
    {
        $days = floor($minutes / 1440);
        $hours = floor(($minutes % 1440) / 60);
        $mins = $minutes % 60;
        $parts = [];
        if ($days) $parts[] = "$days يوم";
        if ($hours) $parts[] = "$hours ساعة";
        if ($mins) $parts[] = "$mins دقيقة";
        return implode(' ', $parts) ?: '0 دقيقة';
    }

    // دالة مساعدة لحساب المدة بين تاريخين
    public static function calculateDuration($start, $end): string
    {
        if (!$start || !$end) return 'لم يبدأ';
        $startDate = Carbon::parse($start);
        $endDate = Carbon::parse($end);
        $minutes = $startDate->diffInMinutes($endDate);
        $days = floor($minutes / 1440);
        $hours = floor(($minutes % 1440) / 60);
        $mins = $minutes % 60;
        $parts = [];
        if ($days) $parts[] = "$days يوم";
        if ($hours) $parts[] = "$hours ساعة";
        if ($mins) $parts[] = "$mins دقيقة";
        return implode(' ', $parts) ?: '0 دقيقة';
    }
}