<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    //
    public function getSignedGcsUrl(Request $request)
    {
        $expirationTime = 60;

        return Storage::disk('gcs')
            ->getAdapter()
            ->getBucket()
            ->object(request('url'))
            ->signedUrl(new \DateTime('+ ' . $expirationTime . ' seconds'));
    }
}
