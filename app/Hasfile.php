<?php

namespace App;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

trait Hasfile
{
   public function upload_file(Request $request, string $colomn, string $folder): ?string
    {
        return $request->hasfile($colomn) ? $request->file($colomn)->store($folder, 'public') : null;
    }

    public function update_file(Request $request, Model $model, string $colomn, string $folder): ?string
    {
        if ($request->hasFile($colomn)) {
            if ($model->$colomn) {
                Storage::disk('public')->delete($model->$colomn);
            }
            $thumbnail = $request->file($colomn)->store($folder, 'public');
        } else {
            $thumbnail = $model->$colomn;
        }

        return $thumbnail;
    }

    public function delete_file(Model $model, string $colomn): void
    {
        if ($model->$colomn) {
            Storage::disk('public')->delete($model->$colomn);
        }
    }
}
