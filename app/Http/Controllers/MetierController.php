<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MetierController extends Controller
{
    public function index()
    {
        $data = json_decode(file_get_contents(resource_path('data/data.json')), true);
        
        $secteurs = $data['mapping_outils_comptabilite']['secteurs'] ?? [];
        
        $brandColors = \App\Models\SeoProject::whereNotNull('brand_color')->pluck('brand_color', 'slug');

        return view('metiers.index', [
            'secteurs' => $secteurs,
            'brandColors' => $brandColors
        ]);
    }
}
