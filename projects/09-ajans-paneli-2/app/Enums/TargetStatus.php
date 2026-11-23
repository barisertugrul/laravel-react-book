<?php

namespace App\Enums;

enum TargetStatus: string
{
    case Pending = 'pending';     // kuyrukta sırasını bekliyor
    case Running = 'running';     // istek sitede
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled'; // işlem iptal edildi, bu siteye hiç gidilmedi

    public function isFinished(): bool
    {
        return in_array($this, [self::Succeeded, self::Failed, self::Cancelled], true);
    }
}
