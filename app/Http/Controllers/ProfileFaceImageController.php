<?php

namespace App\Http\Controllers;

use App\Services\SensitiveStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProfileFaceImageController extends Controller
{
    public function show(Request $request, SensitiveStorageService $storage): Response
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $path = $user->face_image_path;
        abort_if(! is_string($path) || $path === '', 404);
        abort_unless($storage->existsAnywhere($path), 404);

        return $storage->inlineImageResponse($path);
    }
}
