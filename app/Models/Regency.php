<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Regency extends Model
{
    use HasFactory;

    protected $fillable = [
        'bps_code',
        'kemendagri_code',
        'name',
        'province_name',
    ];
}
