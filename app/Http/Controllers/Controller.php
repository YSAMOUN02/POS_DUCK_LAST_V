<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\Request;

abstract class Controller
{
    public function uploadFileToPublic(Request $request, $fieldName, $Name)
    {
        if ($request->hasFile($fieldName)) {
            $file = $request->file($fieldName);
            $folder = 'assets/startic_img';

            // Same convention as ProductController::update — a timestamp plus the
            // original extension. This was rand(1, 100) . '-' . $Name, which is
            // not unique (two products of the same name collide 1 time in 100)
            // and dropped the extension entirely. A collision now matters more
            // than it used to: static assets are cached for 30 days, so a reused
            // filename would keep serving the PREVIOUS product's photo.
            $filename = time() . '-' . $Name . '.' . $file->getClientOriginalExtension();

            // Move file to public folder
            $file->move(public_path($folder), $filename);



            // Return relative path to use in DB
            return $filename;
        }

        return null;
    }
}
