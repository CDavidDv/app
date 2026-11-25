<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class UploadSession extends Model
{
    use HasUlids;

    protected $guarded = [];

    public function chunkDir(): string
    {
        return config('pedacito.chunk_dir').DIRECTORY_SEPARATOR.$this->id;
    }
}
