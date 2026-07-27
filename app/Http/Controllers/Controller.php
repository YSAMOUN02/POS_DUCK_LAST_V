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

            // Generate unique filename
            $filename = rand(1, 100) . '-' . $Name;

            // Move file to public folder
            $file->move(public_path($folder), $filename);



            // Return relative path to use in DB
            return $filename;
        }

        return null;
    }
}
