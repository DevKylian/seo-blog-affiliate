@extends('layouts.blog')

@section('title', 'Rédaction IA en cours — ' . ($title ?? 'Comparatif'))
@section('description', 'Cet article est en cours de génération automatique par notre intelligence artificielle.')

@push('head')
<meta http-equiv="refresh" content="5">
@endpush

@section('content')
<div style="max-width: 800px; margin: 60px auto; text-align: center; padding: 40px 24px; background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);">
    <div style="display: inline-flex; align-items: center; justify-content: center; width: 64px; height: 64px; border-radius: 50%; background: #eff6ff; color: #2563eb; margin-bottom: 20px;">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
        </svg>
    </div>
    
    <span style="display: inline-block; padding: 4px 12px; border-radius: 20px; background: #dbeafe; color: #1e40af; font-size: 13px; font-weight: 700; margin-bottom: 16px;">
        ⚡ Rédaction IA Gemini en cours
    </span>

    <h1 style="font-size: 26px; font-weight: 800; color: #0f172a; margin-bottom: 12px;">
        {{ $title ?? 'Rédaction du comparatif en cours...' }}
    </h1>

    <p style="font-size: 15px; color: #64748b; line-height: 1.6; max-width: 580px; margin: 0 auto 28px;">
        Notre intelligence artificielle analyse actuellement les offres, les fonctionnalités et les tarifs pour rédiger ce comparatif vérifié. 
        <br><strong style="color: #334155;">Cette page s'actualise automatiquement toutes les 5 secondes.</strong>
    </p>

    <div style="display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 30px;">
        <div style="width: 10px; height: 10px; border-radius: 50%; background: #2563eb; animation: pulse 1.5s infinite;"></div>
        <span style="font-size: 14px; font-weight: 600; color: #2563eb;">Analyse des données produit et rédaction...</span>
    </div>

    @auth
        <div style="border-top: 1px solid #f1f5f9; padding-top: 20px; margin-top: 20px;">
            <a href="{{ request()->fullUrlWithQuery(['generate_now' => 1]) }}" 
               style="display: inline-block; background: #2563eb; color: #ffffff; text-decoration: none; padding: 10px 20px; border-radius: 8px; font-weight: 700; font-size: 14px;">
                ⚡ Générer cet article immédiatement (en direct)
            </a>
        </div>
    @endauth
</div>

<style>
@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.4; transform: scale(1.2); }
}
</style>
@endsection
