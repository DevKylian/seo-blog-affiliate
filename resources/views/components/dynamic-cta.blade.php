@props(['goal' => 'general', 'class' => ''])

@php
    $link = 'https://www.indy.fr/?ae=1776';
    $text = 'Essayer Indy gratuitement';

    switch ($goal) {
        case 'create_company':
            $link = 'https://urls.fr/QDk1cj';
            $text = 'Créer son entreprise gratuitement';
            break;
        case 'invoice':
            $link = 'https://urls.fr/qAxSuF';
            $text = 'Recevoir et faire ses factures gratuitement';
            break;
        case 'account':
            $link = 'https://urls.fr/OJ8ERj';
            $text = 'Ouvrir un compte pro gratuit';
            break;
        case 'accounting':
        case 'plus':
        case 'micro':
            $link = 'https://www.indy.fr/?ae=1776';
            $text = 'Automatiser sa compta et ses déclarations';
            break;
    }
@endphp

<a href="{{ $link }}" target="_blank" rel="sponsored noopener" class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 shadow-sm transition-colors duration-200 {{ $class }}">
    {{ $text }}
    <svg class="ml-2 -mr-1 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
    </svg>
</a>
