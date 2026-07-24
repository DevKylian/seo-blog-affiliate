@extends('layouts.blog')
@section('title','FreelanceOS : Fini la phobie administrative')
@section('description','Gérez devis, factures, et déclarations URSSAF facilement. Découvrez l\'outil recommandé par 100 000+ freelances en France.')
@section('content')

<section class="pro-hero">
    <div class="pro-hero-inner">
        <div class="pro-hero-content">
            <span class="pro-eyebrow">Outil Recommandé n°1</span>
            
            @if($intention == 'tva_sasu')
                <h1>Gérez votre TVA de SASU sans erreurs.</h1>
                <p>Déclarations, déductions et calculs : simplifiez la gestion de la TVA pour votre SASU grâce à un outil automatisé qui évite les oublis coûteux.</p>
            @elseif($intention == 'deductions')
                <h1>Optimisez vos déductions fiscales.</h1>
                <p>Ne passez plus à côté d'aucune charge déductible. L'outil catégrise automatiquement vos dépenses pour maximiser votre rentabilité.</p>
            @else
                <h1>L'administratif n'est plus votre pire ennemi.</h1>
                <p>Entre les devis, les relances manuelles, la comptabilité et l'URSSAF, vous perdez un temps précieux. Centralisez toute votre gestion dans une interface claire, conçue pour les indépendants.</p>
            @endif
            
            <div class="pro-trust-badges">
                <span class="pro-badge"><strong style="color:#eab308;">★ 4.9/5</strong> Trustpilot</span>
                <span class="pro-badge"><strong>+100 000</strong> utilisateurs</span>
                <span class="pro-badge"><strong>100%</strong> gratuit en base</span>
            </div>

            <div class="pro-hero-cta">
                <a href="{{ route('affiliate.redirect', 'indy') }}" class="pro-btn-primary" target="_blank" rel="sponsored nofollow">
                    🎁 Profiter de l'offre gratuite
                </a>
            </div>
        </div>
    </div>
</section>

<section class="pro-feature-section">
    <div class="pro-feature-inner">
        <div class="pro-feature-header">
            <h2>Pourquoi nous recommandons Indy.</h2>
            <p>La méthode artisanale (Word + Excel) atteint vite ses limites. Voici comment un outil centralisé change votre quotidien de freelance.</p>
        </div>
        <div class="pro-feature-grid">
            <div class="pro-feature-card">
                <div class="icon-wrapper">📄</div>
                <h3>Facturation Express</h3>
                <p>Éditez des devis et factures conformes en quelques clics. Transformez un devis en facture automatiquement.</p>
            </div>
            <div class="pro-feature-card">
                <div class="icon-wrapper">🏦</div>
                <h3>Synchro Bancaire</h3>
                <p>Connectez votre compte pro. Chaque transaction est automatiquement catégorisée pour la comptabilité.</p>
            </div>
            <div class="pro-feature-card">
                <div class="icon-wrapper">🏛️</div>
                <h3>Déclarations URSSAF</h3>
                <p>Ne ratez plus aucune échéance. Vos cotisations sont calculées et poussées automatiquement à l'URSSAF.</p>
            </div>
            <div class="pro-feature-card">
                <div class="icon-wrapper">📊</div>
                <h3>Pilotage Facile</h3>
                <p>Suivez votre chiffre d'affaires, vos cotisations et ce qu'il vous reste réellement à la fin du mois en un clin d'œil.</p>
            </div>
        </div>
    </div>
</section>

<!-- Deep Problem Section -->
<section class="pro-deep-problem">
    <div class="pro-deep-problem-inner">
        <div class="pro-dp-header">
            <span class="pro-eyebrow-alert">Le coût de l'inaction</span>
            <h2>Pourquoi Word et Excel sont devenus vos pires ennemis.</h2>
            <p>Quand on se lance en freelance, la méthode artisanale semble être la plus économique. Pourtant, elle vous coûte cher en temps et en charge mentale :</p>
        </div>
        <div class="pro-problem-grid">
            <div class="pro-problem-card">
                <div class="problem-icon">⏳</div>
                <h3>Le temps perdu</h3>
                <p>Retaper les mêmes informations client sur chaque devis, calculer la TVA à la main, vérifier manuellement chaque virement bancaire pour pointer vos factures.</p>
            </div>
            <div class="pro-problem-card">
                <div class="problem-icon">⚠️</div>
                <h3>Le risque légal</h3>
                <p>Oublier une mention légale obligatoire sur une facture (ex: délai de paiement, indemnité forfaitaire) peut vous coûter cher en cas de contrôle de l'administration.</p>
            </div>
            <div class="pro-problem-card">
                <div class="problem-icon">🤯</div>
                <h3>La charge mentale URSSAF</h3>
                <p>Peur de se tromper de case lors de la déclaration de chiffre d'affaires, stress de la date limite ou erreur de calcul du montant dû.</p>
            </div>
        </div>
    </div>
</section>

<!-- Steps Section -->
<section class="pro-steps">
    <div class="pro-steps-inner">
        <div class="pro-steps-header">
            <h2>Indy vous remet aux commandes en 3 étapes.</h2>
        </div>
        <div class="pro-step-list">
            <div class="pro-step">
                <div class="step-number">1</div>
                <div class="step-content">
                    <h3>Synchronisation de votre compte</h3>
                    <p>Connectez votre compte bancaire professionnel. Vos transactions remontent automatiquement, de manière 100% sécurisée (Indy est agréé Banque de France).</p>
                </div>
            </div>
            <div class="pro-step">
                <div class="step-number">2</div>
                <div class="step-content">
                    <h3>Facturation en 1 minute</h3>
                    <p>Générez des devis conformes à votre image. Transformez-les en factures en un clic. L'outil gère vos relances clients et le suivi des impayés sans que vous ayez à y penser.</p>
                </div>
            </div>
            <div class="pro-step">
                <div class="step-number">3</div>
                <div class="step-content">
                    <h3>Déclarations assistées</h3>
                    <p>L'outil calcule automatiquement ce que vous devez à l'URSSAF et pousse votre déclaration de chiffre d'affaires sans que vous ayez à remplir les formulaires complexes.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Comparison Section -->
<section class="pro-comparison">
    <div class="pro-comparison-inner">
        <div class="pro-comparison-header">
            <h2>Comparatif des méthodes de gestion</h2>
        </div>
        <div class="pro-table-wrapper">
            <table class="pro-table">
                <thead>
                    <tr>
                        <th></th>
                        <th>Fichiers Excel / Word</th>
                        <th class="highlight-col">Logiciel (Indy)</th>
                        <th>Expert-Comptable</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Prix</strong></td>
                        <td>0 €</td>
                        <td class="highlight-col"><strong>Gratuit</strong> (ou ~20€/mois)</td>
                        <td>~1500 € / an</td>
                    </tr>
                    <tr>
                        <td><strong>Mentions légales garanties</strong></td>
                        <td class="bad">Non</td>
                        <td class="highlight-col good">Oui</td>
                        <td class="good">Oui</td>
                    </tr>
                    <tr>
                        <td><strong>Déclaration URSSAF / TVA</strong></td>
                        <td class="bad">Manuelle</td>
                        <td class="highlight-col good">Automatique</td>
                        <td class="good">Déléguée</td>
                    </tr>
                    <tr>
                        <td><strong>Autonomie</strong></td>
                        <td class="good">Totale</td>
                        <td class="highlight-col good">Totale</td>
                        <td class="bad">Faible</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- Testimonials Section -->
<section class="pro-testimonials">
    <div class="pro-testimonials-inner">
        <div class="pro-testi-header">
            <h2>Ils ont gagné un jour par semaine.</h2>
        </div>
        <div class="pro-testi-grid">
            <div class="pro-testi-card">
                <div class="stars">★★★★★</div>
                <p>"Je passais mes samedis matins à refaire mes tableaux Excel pour la TVA. Avec Indy, tout se calcule tout seul. J'ai retrouvé mes week-ends."</p>
                <div class="author">Sophie M. <span>Développeuse Freelance</span></div>
            </div>
            <div class="pro-testi-card">
                <div class="stars">★★★★★</div>
                <p>"L'interface est d'une fluidité incroyable. Créer un devis me prend 30 secondes chrono, et les relances se font toutes seules."</p>
                <div class="author">Thomas L. <span>Consultant SEO</span></div>
            </div>
            <div class="pro-testi-card">
                <div class="stars">★★★★★</div>
                <p>"Fini la peur de l'URSSAF. L'outil me dit exactement combien je dois payer et remplit la déclaration à ma place. Magique."</p>
                <div class="author">Camille D. <span>Graphiste Indépendante</span></div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ Section -->
<section class="pro-faq">
    <div class="pro-faq-inner">
        <div class="pro-faq-header">
            <h2>Questions fréquentes</h2>
        </div>
        <div class="pro-faq-list">
            <details>
                <summary>Indy est-il vraiment 100% gratuit ?</summary>
                <div class="details-content">Oui. L'offre de base (qui inclut la facturation illimitée, la synchronisation bancaire et la gestion des notes de frais) est totalement gratuite, sans limite de temps. Indy se rémunère sur son abonnement Premium destiné à des besoins plus avancés ou pour les sociétés (ex: accompagnement personnalisé, liasse fiscale).</div>
            </details>
            <details>
                <summary>Mon statut d'indépendant est-il compatible ?</summary>
                <div class="details-content">Indy s'adresse à la très grande majorité des indépendants français : Auto-entrepreneurs (Micro-entreprise), Entreprises Individuelles (EI/EIRL), SASU, EURL, et les professions libérales (BNC).</div>
            </details>
            <details>
                <summary>Mes données bancaires sont-elles en sécurité ?</summary>
                <div class="details-content">Absolument. Indy est un établissement de paiement agréé par l'ACPR (Banque de France). Ils n'ont qu'un accès en lecture seule à vos transactions, sécurisé par les normes bancaires européennes (DSP2). Ils ne peuvent pas faire de virement depuis votre compte.</div>
            </details>
            <details>
                <summary>Puis-je l'utiliser si j'ai déjà un expert-comptable ?</summary>
                <div class="details-content">Oui. Beaucoup d'indépendants utilisent Indy pour la gestion quotidienne (devis, factures, notes de frais, tableau de bord) tout en donnant un simple accès "lecture" à leur expert-comptable pour la partie liasse fiscale en fin d'année.</div>
            </details>
        </div>
    </div>
</section>

<section class="pro-cta-banner">
    <div class="pro-cta-inner">
        <h2>Prêt à gagner 1 jour par semaine ?</h2>
        <p>Rejoignez les indépendants qui ont automatisé leur gestion.</p>
        <a href="{{ route('affiliate.redirect', 'indy') }}" class="pro-btn-primary" target="_blank" rel="sponsored nofollow">Essayer Indy (Gratuit)</a>
    </div>
</section>

<section class="freelance-section home-latest">
    <div class="freelance-section-head" style="text-align: center; max-width: 650px; margin: 0 auto 50px;">
        <span class="pro-eyebrow">Documentation alternative</span>
        <h2 style="font: 800 36px/1.2 'Manrope', sans-serif; color: #0f172a; margin: 16px 0;">Vous préférez la méthode manuelle ?</h2>
        <p style="font-size: 18px; color: #475569; line-height: 1.6;">Parcourez nos guides techniques pour comprendre comment gérer vos obligations vous-même.</p>
    </div>
    <div class="blog-grid" style="max-width: 1100px; margin: 0 auto;">
        @forelse($latestArticles as $article)
            <article class="blog-card" style="transition: transform 0.3s ease; border-radius: 16px; border: 1px solid #e2e8f0; padding: 24px;">
                <div class="blog-card-top" style="margin-bottom: 16px;">
                    <span style="color: #2563eb; background: #eff6ff; padding: 4px 10px; border-radius: 6px; font-size: 10px; font-weight: 800;">{{ str_replace('_',' ',$article->type) }}</span>
                    <time style="color: #94a3b8; font-size: 12px; font-weight: 600;">{{ $article->published_at?->translatedFormat('d M Y') }}</time>
                </div>
                <h3 style="font: 800 20px/1.4 'Manrope', sans-serif; margin: 0 0 12px;"><a href="{{ $article->public_url }}" style="color: #0f172a; text-decoration: none;">{{ $article->title }}</a></h3>
                <p style="color: #475569; font-size: 15px; line-height: 1.6; margin-bottom: 24px;">{{ $article->meta_description }}</p>
                <footer style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 16px;">
                    <span style="color: #64748b; font-size: 12px; font-weight: 600;">{{ $article->project->name }}</span>
                    <a href="{{ $article->public_url }}" style="color: #2563eb; font-weight: 700; font-size: 13px; text-decoration: none;">Lire le guide →</a>
                </footer>
            </article>
        @empty
            <div class="blog-empty" style="grid-column: 1/-1; padding: 60px; text-align: center; background: #f8fafc; border-radius: 16px; border: 1px dashed #cbd5e1;">
                <span style="font-size: 40px; display: block; margin-bottom: 16px;">📚</span>
                <strong style="font: 800 20px 'Manrope', sans-serif; color: #0f172a;">La bibliothèque publique est prête.</strong>
                <p style="color: #475569; margin: 12px 0 24px;">Validez vos premiers contenus dans le Studio pour les afficher ici.</p>
                <a href="{{ route('login') }}" class="pro-btn-secondary">Ouvrir le Studio</a>
            </div>
        @endforelse
    </div>
    <div class="pro-fallback-links" style="margin-top: 50px; text-align: center; display: flex; justify-content: center; gap: 16px;">
        <a href="{{ route('blog.index') }}" class="pro-btn-secondary">Parcourir tous les guides</a>
        <a href="{{ route('free-tools.index') }}" class="pro-btn-secondary" style="background: #f8fafc;">Nos outils gratuits</a>
    </div>
</section>

@endsection
