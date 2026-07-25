<div x-data="simulateurMicro()" class="tool-calculator-layout">
    
    <div class="tool-inputs-grid">
        <!-- Chiffre d'Affaires -->
        <div class="tool-input-group">
            <label class="tool-field-label">
                Chiffre d'Affaires Mensuel (€)
                <span>Montant facturé sur le mois (brut)</span>
                <input type="number" x-model.number="ca" min="0" step="100" class="tool-text-input">
            </label>
        </div>
        
        <!-- Type d'activité -->
        <div class="tool-input-group">
            <label class="tool-field-label">
                Activité principale
                <span>Détermine le taux de cotisations sociales</span>
                <select x-model="activity" class="tool-select-input">
                    <option value="vente">Vente de marchandises (12.3%)</option>
                    <option value="bic">Prestations de services commerciales/artisanales (BIC - 21.2%)</option>
                    <option value="bnc">Professions libérales / Services (BNC - 21.1%)</option>
                </select>
            </label>
        </div>
    </div>

    <!-- Options supplémentaires (ACRE / VFL) -->
    <div style="display: flex; flex-direction: column; gap: 16px;">
        <label class="tool-checkbox-card">
            <input type="checkbox" x-model="acre">
            <div class="tool-checkbox-card-info">
                Je bénéficie de l'ACRE
                <div class="tool-checkbox-card-desc">Vos cotisations URSSAF sont réduites de moitié la première année</div>
            </div>
        </label>
        
        <label class="tool-checkbox-card">
            <input type="checkbox" x-model="vfl">
            <div class="tool-checkbox-card-info">
                Option de versement libératoire de l'impôt
                <div class="tool-checkbox-card-desc">Impôt sur le revenu payé à la source en même temps que vos cotisations</div>
            </div>
        </label>
    </div>

    <!-- Résultats -->
    <div class="tool-results-panel">
        <div class="tool-results-split">
            <div class="tool-result-row">
                <div class="tool-result-eyebrow">Cotisations URSSAF</div>
                <div class="tool-result-value" x-text="format(results.urssaf) + ' €'"></div>
                <div style="font-size: 12px; color: var(--tool-muted); margin-top: 4px; font-weight:600;">Soit <span x-text="results.urssafRate"></span>% du CA</div>
            </div>
            
            <div class="tool-result-row" x-show="vfl">
                <div class="tool-result-eyebrow">Impôt Libératoire</div>
                <div class="tool-result-value" style="color: var(--tool-yellow);" x-text="format(results.impot) + ' €'"></div>
                <div style="font-size: 12px; color: var(--tool-muted); margin-top: 4px; font-weight:600;">Soit <span x-text="results.impotRate"></span>% du CA</div>
            </div>
        </div>

        <div class="tool-result-row tool-result-card-highlight">
            <div class="tool-result-eyebrow" style="color: var(--tool-accent);">Revenu Net après charges</div>
            <div class="tool-result-value" x-text="format(results.net) + ' €'"></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('simulateurMicro', () => ({
        ca: 3000,
        activity: 'bnc',
        acre: false,
        vfl: false,
        
        get results() {
            let baseCA = parseFloat(this.ca) || 0;
            
            let baseUrssafRate = 0;
            let impotRate = 0;

            if (this.activity === 'vente') {
                baseUrssafRate = 12.3;
                impotRate = 1.0;
            } else if (this.activity === 'bic') {
                baseUrssafRate = 21.2;
                impotRate = 1.7;
            } else {
                baseUrssafRate = 21.1; // BNC
                impotRate = 2.2;
            }

            let finalUrssafRate = this.acre ? (baseUrssafRate / 2) : baseUrssafRate;
            
            let urssaf = baseCA * (finalUrssafRate / 100);
            let impot = this.vfl ? baseCA * (impotRate / 100) : 0;
            
            let net = baseCA - urssaf - impot;

            return { 
                urssaf: urssaf,
                urssafRate: finalUrssafRate.toFixed(1),
                impot: impot,
                impotRate: impotRate.toFixed(1),
                net: net 
            };
        },

        format(val) {
            return new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(Math.ceil(val));
        }
    }));
});
</script>
