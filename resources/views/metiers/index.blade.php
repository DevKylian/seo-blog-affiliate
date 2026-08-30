@extends('layouts.blog')

@section('title', 'Annuaire des métiers indépendants - Outils recommandés')
@section('meta_description', 'Découvrez les meilleurs logiciels de facturation et de comptabilité recommandés spécifiquement pour votre métier indépendant (BNC, artisan, tech, santé...).')




@section('content')
<section class="pro-hero" style="padding: 100px 20px; background: linear-gradient(135deg, #020617, #0f172a, #1e1b4b); color: white;">
    <div class="pro-hero-inner">
        <span class="pro-eyebrow">Annuaire des métiers</span>
        <h1 style="color: white; background: none; -webkit-text-fill-color: white;">Trouvez l'outil parfait pour votre métier.</h1>
        <p>Nous avons analysé les besoins spécifiques de chaque profession pour vous recommander le meilleur logiciel de comptabilité et de facturation.</p>
    </div>
</section>

<div x-data="metierDirectory()" style="margin-top: 0; padding-top: 40px; padding-bottom: 80px; background: #f8fafc; padding-inline: 24px;">
    <div style="max-width: 1200px; margin: 0 auto; width: 100%;">
        
                <!-- Search & Filter Bar -->
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 20px; padding: 24px; margin-bottom: 40px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
            <div style="position: relative;">
                <span style="position: absolute; left: 16px; top: 14px; color: #94a3b8;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </span>
                <input type="text" x-model="searchQuery" placeholder="Rechercher un métier (ex: Infirmier, Développeur...)" style="width: 100%; padding: 14px 16px 14px 48px; border: 1px solid #cbd5e1; border-radius: 12px; font-size: 16px; outline: none; transition: border-color 0.2s;" onfocus="this.style.borderColor='#2563eb'" onblur="this.style.borderColor='#cbd5e1'">
            </div>
        </div>

        <!-- Job Cards Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px; padding-bottom: 80px;">
            <template x-for="job in filteredJobs" :key="job.nom">
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 20px; padding: 24px; display: flex; flex-direction: column; transition: all 0.3s; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px -1px rgba(0, 0, 0, 0.05)';">
                    
                    <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 20px;">
                        <div style="font-size: 32px; width: 56px; height: 56px; background: #f8fafc; border-radius: 16px; display: flex; align-items: center; justify-content: center;" x-text="job.emoji"></div>
                        <h3 style="font-size: 18px; font-weight: 800; color: #0f172a; margin: 0; line-height: 1.3;" x-text="job.nom"></h3>
                    </div>

                    <div style="margin-bottom: 16px;">
                        <p style="font-size: 12px; font-weight: 800; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px; margin-bottom: 8px;">Statuts compatibles</p>
                        <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                            <template x-for="statut in job.statuts_compatibles">
                                <span style="background: #f1f5f9; color: #475569; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600;" x-text="statut"></span>
                            </template>
                        </div>
                    </div>

                    <div style="margin-bottom: 24px;">
                        <p style="font-size: 12px; font-weight: 800; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px; margin-bottom: 8px;">Besoins Spécifiques</p>
                        <ul style="margin: 0; padding-left: 16px; color: #334155; font-size: 13px;">
                            <template x-for="besoin in job.besoins_specifiques">
                                <li style="margin-bottom: 4px;" x-text="besoin"></li>
                            </template>
                        </ul>
                    </div>

                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; position: relative; display: flex; flex-direction: column; flex-grow: 1;">
                        <div style="position: absolute; top: -10px; right: 16px; background: white; color: #475569; border: 1px solid #e2e8f0; font-size: 10px; font-weight: 800; padding: 2px 8px; border-radius: 10px; text-transform: uppercase; letter-spacing: 0.5px;">Recommandé</div>
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <span :style="`color: ${getToolColor(job.outils_recommandes[0]?.nom).icon};`"><svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg></span>
                            <span style="font-size: 18px; font-weight: 900; color: #0f172a;" x-text="job.outils_recommandes[0]?.nom"></span>
                        </div>
                        <p style="margin: 0 0 16px 0; font-size: 13px; color: #475569; line-height: 1.4;" x-text="job.outils_recommandes[0]?.argumentaire"></p>
                        
                        <div style="margin-top: auto; display: flex; flex-direction: column; gap: 8px;">
                            <a :href="getAffiliateLink(job.outils_recommandes[0]?.nom)" target="_blank" rel="sponsored nofollow"
                               :style="`display: block; width: 100%; text-align: center; background: ${getToolColor(job.outils_recommandes[0]?.nom).badge}; color: ${getToolColor(job.outils_recommandes[0]?.nom).text}; padding: 10px; border-radius: 8px; font-size: 13px; font-weight: 700; text-decoration: none; transition: opacity 0.2s; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);`"
                               onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                                Essayer gratuitement →
                            </a>
                            
                            <a :href="getHubLink(job.nom)"
                               style="display: block; width: 100%; text-align: center; background: transparent; color: #475569; border: 1px solid #cbd5e1; padding: 10px; border-radius: 8px; font-size: 13px; font-weight: 700; text-decoration: none; transition: all 0.2s;"
                               onmouseover="this.style.background='#f1f5f9'; this.style.borderColor='#94a3b8'; this.style.color='#0f172a'" onmouseout="this.style.background='transparent'; this.style.borderColor='#cbd5e1'; this.style.color='#475569'">
                                Voir le guide complet
                            </a>
                        </div>
                    </div>

                </div>
            </template>
        </div>
        
        <!-- Empty State -->
        <div x-show="filteredJobs.length === 0" style="display: none; text-align: center; padding: 60px 20px;">
            <div style="font-size: 48px; margin-bottom: 16px;">🔍</div>
            <h3 style="font-size: 20px; font-weight: 800; color: #0f172a; margin-bottom: 8px;">Aucun métier trouvé</h3>
            <p style="color: #64748b; font-size: 16px;">Essayez d'autres termes de recherche ou sélectionnez un autre secteur.</p>
            <button @click="searchQuery = ''" style="margin-top: 16px; background: #f1f5f9; color: #475569; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer;">Réinitialiser la recherche</button>
        </div>

    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('metierDirectory', () => ({
            searchQuery: '',
            selectedSector: 'all',
            sectors: @json($secteurs),
            
            get allJobs() {
                let jobs = [];
                this.sectors.forEach(sector => {
                    sector.metiers.forEach(m => {
                        jobs.push({
                            ...m,
                            sector_id: sector.id,
                            sector_label: sector.label
                        });
                    });
                });
                return jobs;
            },

            getToolColor(toolName) {
                if (!toolName) return { badge: '#64748b', text: 'white', icon: '#64748b' };
                const name = toolName.toLowerCase();
                if (name.includes('indy')) return { badge: '#F75A77', text: 'white', icon: '#F75A77' }; 
                if (name.includes('pennylane')) return { badge: '#10b981', text: 'white', icon: '#10b981' }; 
                if (name.includes('abby')) return { badge: '#1e3a8a', text: 'white', icon: '#1e3a8a' }; 
                if (name.includes('dougs')) return { badge: '#8b5cf6', text: 'white', icon: '#8b5cf6' }; 
                if (name.includes('tiime')) return { badge: '#c026d3', text: 'white', icon: '#c026d3' }; 
                if (name.includes('shine')) return { badge: '#FEF08A', text: '#022C22', icon: '#EAB308' }; // Jaune pâle Shine
                return { badge: '#64748b', text: 'white', icon: '#64748b' };
            },
            getAffiliateLink(toolName) {
                if (!toolName) return '#';
                const name = toolName.toLowerCase().replace(/[^a-z0-9]/g, '-');
                return `/go/${name}`;
            },
            getHubLink(jobName) {
                if (!jobName) return '#';
                const name = jobName.toLowerCase()
                                    .normalize("NFD").replace(/[\u0300-\u036f]/g, "")
                                    .replace(/[^a-z0-9]/g, '-')
                                    .replace(/-+/g, '-')
                                    .replace(/^-|-$/g, '');
                return `/hubs/${name}`;
            },
            
            get filteredJobs() {
                return this.allJobs.filter(job => {
                    const matchesSearch = job.nom.toLowerCase().includes(this.searchQuery.toLowerCase()) || 
                                        job.statuts_compatibles.some(s => s.toLowerCase().includes(this.searchQuery.toLowerCase()));
                    const matchesSector = this.selectedSector === 'all' || job.sector_id === this.selectedSector;
                    return matchesSearch && matchesSector;
                });
            }
        }));
    });
</script>
@endsection
