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

    /**
     * Build the picker thumbnail for a just-uploaded image, so the product's
     * tile is small from its first appearance instead of waiting for the lazy
     * fill in ProductImages::url().
     *
     * A thumbnail is an optimisation. Whatever goes wrong here, the upload
     * itself has already succeeded and must stay that way — the pickers simply
     * fall back to the original until a thumbnail exists.
     */
    protected function makeThumbnail(?string $filename): void
    {
        if (! $filename) {
            return;
        }

        try {
            \App\Services\ProductImages::make($filename);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
