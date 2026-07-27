<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Mike42\Escpos\Printer;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

class ThermalPrintController extends Controller
{
    public function print(Request $request)
{
    $request->validate([
        'image'  => 'required|string',
        'doc_no' => 'nullable|string|max:50',
    ]);

    $tmp = null;

    try {
        $data = preg_replace('#^data:image/\w+;base64,#', '', $request->input('image'));

        // use Laravel storage dir instead of system temp (IIS-safe)
        $dir = storage_path('app/receipts');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $tmp = $dir . '/rcpt_' . uniqid() . '.png';
        file_put_contents($tmp, base64_decode($data));

        $connector = new WindowsPrintConnector("smb://localhost/POS80");
        $printer   = new Printer($connector);

        $printer->bitImage(EscposImage::load($tmp, false));
        $printer->feed(3);
        $printer->cut();
        $printer->close();

        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
    } finally {
        if ($tmp && file_exists($tmp)) {
            @unlink($tmp);
        }
    }
}
}
