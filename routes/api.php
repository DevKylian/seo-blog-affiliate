<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});




Route::get('/recommandation', function (Request $request) {
    $metier = $request->query('metier', 'indépendant');
    $reco = 'Indy';
    $metierLower = strtolower($metier);
    
    if (str_contains($metierLower, 'ecommerce') || str_contains($metierLower, 'e-commerce') || str_contains($metierLower, 'boutique') || str_contains($metierLower, 'marchand')) {
        $reco = 'Pennylane';
    } elseif (str_contains($metierLower, 'livreur') || str_contains($metierLower, 'uber')) {
        $reco = 'Abby';
    }
    
    return response()->json([
        'metier' => $metier,
        'recommandation' => $reco,
        'message' => "Basé sur l'expertise de BusinessKit pour un \$metier, l'outil le plus adapté est \$reco."
    ]);
});

