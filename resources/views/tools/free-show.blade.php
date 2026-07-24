@extends('layouts.blog')
@section('title',$tool['title'])
@section('description',$tool['description'])
@section('content')
<article class="free-tool-page" data-tool="{{ $tool['type'] }}" style="padding-bottom: 80px;">
    <header class="pro-hero" style="padding: 100px 20px 80px; text-align: center; background: linear-gradient(135deg, #020617, #0f172a, #1e1b4b); color: white; margin-bottom: -100px; padding-bottom: 160px;">
        <div class="pro-hero-inner">
            <a href="{{ route('free-tools.index') }}" style="display: inline-flex; align-items: center; gap: 8px; color: #94a3b8; text-decoration: none; font-weight: 700; margin-bottom: 24px;">← Tous les outils gratuits</a>
            <br>
            <span class="pro-eyebrow" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3);">Outil interactif</span>
            <h1 style="font: 800 48px/1.1 'Manrope', sans-serif; color: white; margin: 0 0 20px;">{{ $tool['title'] }}</h1>
            <p style="font-size: 18px; line-height: 1.6; color: #cbd5e1; max-width: 600px; margin: 0 auto;">{{ $tool['description'] }}</p>
        </div>
    </header>

    <div style="max-width: 800px; margin: 0 auto; position: relative; z-index: 10;">
        @if($tool['type'] === 'tjm')
            <section class="tool-calculator" data-calculator="tjm" style="background: white; border-radius: 24px; padding: 40px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; display: grid; gap: 24px;">
                <label style="display: grid; gap: 8px; font-weight: 700; color: #0f172a;">Objectif annuel net souhaité (€)
                    <input type="number" data-input="goal" value="50000" style="padding: 16px; border-radius: 12px; border: 1px solid #cbd5e1; font-size: 18px; width: 100%; outline: none; transition: border-color 0.2s;" onfocus="this.style.borderColor='#2563eb'" onblur="this.style.borderColor='#cbd5e1'">
                </label>
                <label style="display: grid; gap: 8px; font-weight: 700; color: #0f172a;">Jours facturables par an
                    <input type="number" data-input="days" value="180" style="padding: 16px; border-radius: 12px; border: 1px solid #cbd5e1; font-size: 18px; width: 100%; outline: none; transition: border-color 0.2s;" onfocus="this.style.borderColor='#2563eb'" onblur="this.style.borderColor='#cbd5e1'">
                </label>
                <label style="display: grid; gap: 8px; font-weight: 700; color: #0f172a;">Marge charges et imprévus (%)
                    <input type="number" data-input="buffer" value="35" style="padding: 16px; border-radius: 12px; border: 1px solid #cbd5e1; font-size: 18px; width: 100%; outline: none; transition: border-color 0.2s;" onfocus="this.style.borderColor='#2563eb'" onblur="this.style.borderColor='#cbd5e1'">
                </label>
                <output style="margin-top: 16px; padding: 24px; background: linear-gradient(135deg, #eff6ff, #dbeafe); border-radius: 16px; border: 1px solid #bfdbfe; text-align: center; display: block;">
                    <span style="display: block; font-weight: 800; color: #1e3a8a; text-transform: uppercase; font-size: 13px; letter-spacing: 0.05em; margin-bottom: 8px;">TJM estimé recommandé</span>
                    <strong data-output="result" style="font: 800 48px/1 'Manrope', sans-serif; color: #2563eb;">-</strong>
                </output>
            </section>
        @elseif($tool['type'] === 'revenu')
            <section class="tool-calculator" data-calculator="revenu" style="background: white; border-radius: 24px; padding: 40px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; display: grid; gap: 24px;">
                <label style="display: grid; gap: 8px; font-weight: 700; color: #0f172a;">Chiffre d'affaires mensuel (€)
                    <input type="number" data-input="revenue" value="5000" style="padding: 16px; border-radius: 12px; border: 1px solid #cbd5e1; font-size: 18px; width: 100%; outline: none; transition: border-color 0.2s;" onfocus="this.style.borderColor='#2563eb'" onblur="this.style.borderColor='#cbd5e1'">
                </label>
                <label style="display: grid; gap: 8px; font-weight: 700; color: #0f172a;">Charges estimées (URSSAF %)
                    <input type="number" data-input="charges" value="21.1" step="0.1" style="padding: 16px; border-radius: 12px; border: 1px solid #cbd5e1; font-size: 18px; width: 100%; outline: none; transition: border-color 0.2s;" onfocus="this.style.borderColor='#2563eb'" onblur="this.style.borderColor='#cbd5e1'">
                </label>
                <label style="display: grid; gap: 8px; font-weight: 700; color: #0f172a;">Frais et abonnements pros (€)
                    <input type="number" data-input="expenses" value="200" style="padding: 16px; border-radius: 12px; border: 1px solid #cbd5e1; font-size: 18px; width: 100%; outline: none; transition: border-color 0.2s;" onfocus="this.style.borderColor='#2563eb'" onblur="this.style.borderColor='#cbd5e1'">
                </label>
                <output style="margin-top: 16px; padding: 24px; background: linear-gradient(135deg, #eff6ff, #dbeafe); border-radius: 16px; border: 1px solid #bfdbfe; text-align: center; display: block;">
                    <span style="display: block; font-weight: 800; color: #1e3a8a; text-transform: uppercase; font-size: 13px; letter-spacing: 0.05em; margin-bottom: 8px;">Revenu estimé avant impôt</span>
                    <strong data-output="result" style="font: 800 48px/1 'Manrope', sans-serif; color: #2563eb;">-</strong>
                </output>
            </section>
        @else
            <section class="checklist-tool" style="background: white; border-radius: 24px; padding: 40px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; display: grid; gap: 16px;">
                @foreach(['Vérifier le SIRET et les informations administratives','Ouvrir un compte bancaire dédié (ou pro)','Préparer un modèle de devis et facture conforme','Lister les dates des déclarations URSSAF','Mettre en place le suivi des dépenses','Vérifier les seuils de TVA','Choisir un outil pour centraliser la gestion (ex: Indy)'] as $item)
                    <label style="display: flex; align-items: center; gap: 16px; padding: 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; cursor: pointer; transition: background 0.2s;">
                        <input type="checkbox" style="width: 24px; height: 24px; accent-color: #2563eb; cursor: pointer;" onchange="this.parentElement.style.background = this.checked ? '#eff6ff' : '#f8fafc'; this.parentElement.style.borderColor = this.checked ? '#bfdbfe' : '#e2e8f0';"> 
                        <span style="font-size: 16px; font-weight: 600; color: #334155;">{{ $item }}</span>
                    </label>
                @endforeach
            </section>
        @endif

        <aside class="tool-conversion" style="margin-top: 60px; background: linear-gradient(135deg, #f8fafc, #eff6ff); border: 1px solid #bfdbfe; border-radius: 24px; padding: 40px; text-align: center;">
            <strong style="display: block; font: 800 24px 'Manrope', sans-serif; color: #0f172a; margin-bottom: 12px;">Passez à la vitesse supérieure</strong>
            <p style="font-size: 16px; color: #475569; margin: 0 auto 24px; max-width: 600px;">Pourquoi calculer tout cela manuellement ? Découvrez comment des logiciels comme Indy automatisent vos devis, factures, et calculs de cotisations directement depuis votre compte pro.</p>
            <a href="{{ route('affiliate.redirect', 'indy') }}" class="pro-btn-primary" target="_blank" rel="sponsored nofollow" style="display: inline-flex; align-items: center; justify-content: center; padding: 18px 42px; font: 800 17px 'Manrope', sans-serif; color: white; background: linear-gradient(135deg, #2563eb, #4f46e5); border-radius: 12px; text-decoration: none; box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.4);">
                Essayer Indy gratuitement
            </a>
        </aside>
    </div>
</article>

@if(in_array($tool['type'], ['tjm','revenu'], true))
<script>
(() => {
    const root = document.querySelector('[data-calculator="{{ $tool['type'] }}"]');
    if (!root) return;
    const number = name => Number(root.querySelector(`[data-input="${name}"]`)?.value || 0);
    const output = root.querySelector('[data-output="result"]');
    const format = value => new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(Math.max(0, value));
    const compute = () => {
        if (root.dataset.calculator === 'tjm') {
            const goal = number('goal');
            const days = Math.max(1, number('days'));
            const buffer = 1 + (number('buffer') / 100);
            output.textContent = format((goal * buffer) / days) + ' / jour';
        } else {
            const revenue = number('revenue');
            const charges = revenue * (number('charges') / 100);
            const expenses = number('expenses');
            output.textContent = format(revenue - charges - expenses) + ' / mois';
        }
    };
    root.querySelectorAll('input').forEach(input => input.addEventListener('input', compute));
    compute();
})();
</script>
@endif
@endsection
