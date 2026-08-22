@extends('layouts.blog')

@section('title', 'À propos de BusinessKit - Kylian Dev')
@section('description', 'Découvrez la méthodologie de BusinessKit et son créateur, Kylian Dev, spécialiste des logiciels B2B pour indépendants.')

@section('content')

<div style="background: var(--home-bg); padding: 80px 24px; border-bottom: 1px solid var(--home-border);">
    <div style="max-width: 800px; margin: 0 auto; text-align: center;">
        <h1 style="font-family: 'Manrope', sans-serif; font-weight: 800; font-size: 48px; color: var(--home-primary); margin-bottom: 24px;">À propos de BusinessKit</h1>
        <p style="font-size: 20px; color: var(--home-muted); line-height: 1.6;">Notre mission : aider les freelances et auto-entrepreneurs à choisir les meilleurs outils pour gagner du temps et se concentrer sur leur cœur de métier.</p>
    </div>
</div>

<main style="max-width: 800px; margin: 60px auto; padding: 0 24px;">
    
    <div style="display: flex; gap: 40px; align-items: flex-start; margin-bottom: 60px;">
        <div style="flex-shrink: 0; width: 150px; height: 150px; border-radius: 50%; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1);"><img src="/images/author-kylian.png" alt="Kylian" style="width: 100%; height: 100%; object-fit: cover;" /></div>
        <div>
            <h2 style="font-family: 'Manrope', sans-serif; font-size: 28px; font-weight: 800; margin-bottom: 8px;">Kylian Dev.</h2>
            <p style="color: var(--home-accent); font-weight: 700; margin-bottom: 16px;">Créateur de BusinessKit</p>
            <p style="line-height: 1.7; color: var(--home-text); margin-bottom: 16px;">
                Depuis 2025 je compare les logiciels destinés aux freelances français. Chaque logiciel est testé selon une grille publique de 25 critères et mis à jour plusieurs fois par an. L'objectif de BusinessKit est d'offrir un comparateur <strong>100% transparent et objectif</strong>.
            </p>
            <a href="https://linkedin.com/in/kyliandev" target="_blank" rel="noopener noreferrer" style="display: inline-flex; align-items: center; gap: 8px; font-weight: 700; color: #0077b5; text-decoration: none;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>
                Suivre sur LinkedIn
            </a>
        </div>
    </div>

    <hr style="border: none; border-top: 1px solid var(--home-border); margin: 60px 0;">

    <h2 id="methodologie" style="font-family: 'Manrope', sans-serif; font-size: 32px; font-weight: 800; margin-bottom: 24px;">Notre Méthodologie</h2>
    <p style="line-height: 1.7; color: var(--home-text); margin-bottom: 24px;">
        L'indépendance de nos recommandations est la clé de notre média. Tous les logiciels listés sur BusinessKit passent par une grille d'évaluation stricte de 25 critères précis :
    </p>

    <div style="background: #f8fafc; border: 1px solid var(--home-border); border-radius: 12px; padding: 32px; margin-bottom: 40px;">
        <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 16px;">
            <li style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--home-border); padding-bottom: 12px;">
                <strong>Prix et transparence de la grille tarifaire</strong>
                <span style="color: var(--home-accent); font-weight: 700;">20%</span>
            </li>
            <li style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--home-border); padding-bottom: 12px;">
                <strong>Fonctionnalités & Richesse de l'outil</strong>
                <span style="color: var(--home-accent); font-weight: 700;">25%</span>
            </li>
            <li style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--home-border); padding-bottom: 12px;">
                <strong>Facilité d'utilisation & Ergonomie (UX)</strong>
                <span style="color: var(--home-accent); font-weight: 700;">20%</span>
            </li>
            <li style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--home-border); padding-bottom: 12px;">
                <strong>Réactivité et qualité du support client</strong>
                <span style="color: var(--home-accent); font-weight: 700;">15%</span>
            </li>
            <li style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--home-border); padding-bottom: 12px;">
                <strong>Capacité d'automatisation des tâches</strong>
                <span style="color: var(--home-accent); font-weight: 700;">10%</span>
            </li>
            <li style="display: flex; justify-content: space-between;">
                <strong>Sécurité et conformité (RGPD, FEC)</strong>
                <span style="color: var(--home-accent); font-weight: 700;">10%</span>
            </li>
        </ul>
    </div>

    <h3 style="font-family: 'Manrope', sans-serif; font-size: 24px; font-weight: 800; margin-bottom: 16px;">Exemple concret d'évaluation</h3>
    <p style="line-height: 1.7; color: var(--home-text); margin-bottom: 40px;">
        Prenons un logiciel qui coûte 49€/mois (score Prix: 5/10), mais qui excelle absolument sur l'automatisation de la TVA et de l'URSSAF (score Automatisation: 10/10) avec un support qui répond en 5 minutes (Support: 9/10). Notre algorithme de pondération va équilibrer ces facteurs pour générer une note finale objective. C'est ce qui nous différencie des blogs qui se contentent de copier-coller les pages de vente des éditeurs.
    </p>

    <div style="padding: 24px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px; color: #1e3a8a; line-height: 1.6;">
        <strong>Transparence sur l'affiliation :</strong> 
        BusinessKit est un média indépendant financé par l'affiliation. Cela signifie que si vous souscrivez à un outil en passant par l'un de nos liens, nous pouvons toucher une commission. Cela n'impacte en rien le prix que vous payez, ni l'objectivité de nos tests. Les logiciels moins bien notés ne sont pas cachés.
    </div>

</main>

@endsection
