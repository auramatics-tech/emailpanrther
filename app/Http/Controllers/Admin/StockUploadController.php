<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class StockUploadController extends Controller
{

    public function upload_sheet(Request $request)
    {

        if ($request->isMethod('post')) {
        }

        return view('backend.admin.supplier_excel_upload');
    }
}
