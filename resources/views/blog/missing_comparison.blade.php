@extends('layouts.blog')

@section('title', 'Comparatif ' . ($title ?? 'non disponible'))
@section('description', 'Ce comparatif n\'est pas encore rédigé.')

@section('content')
<div style="max-width: 800px; margin: 60px auto; text-align: center; padding: 40px 24px; background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);">
    <div style="display: inline-flex; align-items: center; justify-content: center; width: 64px; height: 64px; border-radius: 50%; background: #fef3c7; color: #d97706; margin-bottom: 20px;">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
    </div>

    <h1 style="font-size: 26px; font-weight: 800; color: #0f172a; margin-bottom: 12px;">
        {{ $title ?? 'Comparatif disponible sur demande' }}
    </h1>

    <p style="font-size: 15px; color: #64748b; line-height: 1.6; max-width: 580px; margin: 0 auto 28px;">
        Ce comparatif n'est pas encore en ligne. Vous pouvez lancer sa rédaction automatique par notre IA dès maintenant.
    </p>

    @auth
        <div style="border-top: 1px solid #f1f5f9; padding-top: 20px; margin-top: 20px;">
            <a href="{{ request()->fullUrlWithQuery(['generate_now' => 1]) }}" 
               style="display: inline-block; background: #2563eb; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: 700; font-size: 15px; box-shadow: 0 4px 12px rgba(37,99,235,0.2);">
                ✨ Générer ce comparatif avec l'IA (en direct)
            </a>
        </div>
    @else
        <a href="{{ route('home') }}" style="color: #2563eb; font-weight: 600; text-decoration: none;">← Retour à l'accueil</a>
    @endauth
</div>
@endsection
