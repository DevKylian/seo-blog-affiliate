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
        
                $projectsWithColors = \App\Models\SeoProject::whereNotNull('brand_color')->get();
        $brandColors = [];
        foreach ($projectsWithColors as $project) {
            $brandColors[$project->slug] = [
                'badge' => $project->brand_color,
                'text' => $project->brand_text_color,
                'icon' => $project->brand_color
            ];
        }

        $publishedMetiers = \App\Models\Article::where('type', 'metier')->where('status', 'published')->pluck('slug')->toArray();

        return view('metiers.index', [
            'secteurs' => $secteurs,
            'brandColors' => $brandColors,
            'publishedMetiers' => $publishedMetiers
        ]);
    }
}
