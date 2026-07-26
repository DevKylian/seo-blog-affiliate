@extends('layouts.blog')

@section('title', 'Nos critères de sélection - BusinessKit')

@section('content')
<div class="container" style="max-width: 800px; margin: 40px auto; padding: 0 20px;">
    <h1 style="margin-bottom: 30px; font-size: 2.5rem; color: #1e293b;">Nos critères de sélection</h1>
    
    <div style="line-height: 1.8; color: #475569;">
        <p>Chez BusinessKit, notre objectif est de vous recommander les meilleurs outils pour votre activité. Pour cela, nous évaluons chaque logiciel selon une grille stricte et impartiale :</p>
        
        <h2 style="font-size: 1.5rem; color: #0f172a; margin-top: 30px;">1. Rapport qualité/prix</h2>
        <p>Nous analysons le coût réel de l'outil par rapport aux fonctionnalités offertes, sans oublier les frais cachés éventuels (frais d'installation, options payantes indispensables, etc.).</p>

        <h2 style="font-size: 1.5rem; color: #0f172a; margin-top: 30px;">2. Facilité d'utilisation</h2>
        <p>Un logiciel performant doit être intuitif. Nous testons l'ergonomie, la prise en main et la clarté de l'interface pour garantir un gain de temps au quotidien.</p>

        <h2 style="font-size: 1.5rem; color: #0f172a; margin-top: 30px;">3. Conformité légale (France)</h2>
        <p>Particulièrement pour les logiciels de facturation et de comptabilité, nous vérifions s'ils respectent les lois françaises en vigueur (loi anti-fraude à la TVA, facturation électronique, etc.).</p>

        <h2 style="font-size: 1.5rem; color: #0f172a; margin-top: 30px;">4. Support client</h2>
        <p>La réactivité et la qualité de l'assistance (chat, email, téléphone) sont cruciales en cas de problème technique. Nous prenons en compte les avis utilisateurs sur ce point.</p>
    </div>
</div>
@endsection
