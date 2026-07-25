<div x-data="calculateurTjm()" class="tool-calculator-layout">
    
    <div class="tool-inputs-grid">
        <!-- Salaire Net Visé -->
        <div class="tool-input-group">
            <label class="tool-field-label">
                Salaire net mensuel visé (€)
                <span>Le montant net que vous souhaitez recevoir après charges</span>
                <input type="number" x-model.number="netGoal" min="0" step="100" class="tool-text-input">
            </label>
        </div>
        
        <!-- Jours travaillés -->
        <div class="tool-input-group">
            <label class="tool-field-label">
                Jours facturés par mois
                <span>Le nombre moyen de jours de prestation par mois</span>
                <input type="number" x-model.number="days" min="1" max="31" class="tool-text-input">
            </label>
        </div>

        <!-- Charges -->
        <div class="tool-input-group">
            <label class="tool-field-label">
                Taux de charges (ex: URSSAF en %)
                <span>Dépend de votre statut (ex: 21.1% en micro-entreprise)</span>
                <input type="number" x-model.number="chargesRate" min="0" max="100" step="0.1" class="tool-text-input">
            </label>
        </div>

        <!-- Frais pro -->
        <div class="tool-input-group">
            <label class="tool-field-label">
                Frais pro mensuels (Assurance, logiciels...)
                <span>Mutuelle, abonnement SaaS, loyers, frais de transport</span>
                <input type="number" x-model.number="expenses" min="0" step="10" class="tool-text-input">
            </label>
        </div>
    </div>

    <!-- Marge de sécurité -->
    <label class="tool-checkbox-card">
        <input type="checkbox" x-model="securityMargin" id="secMargin">
        <div class="tool-checkbox-card-info">
            Ajouter une marge de sécurité de 10%
            <div class="tool-checkbox-card-desc">Couvre les congés payés, la maladie et les périodes d'intercontrat</div>
        </div>
    </label>

    <!-- Résultats -->
    <div class="tool-results-panel">
        <div class="tool-result-row tool-result-card-highlight">
            <div class="tool-result-eyebrow" style="color: var(--tool-accent);">Taux Journalier Moyen (TJM) Recommandé</div>
            <div class="tool-result-value" x-text="format(results.tjm) + ' €'"></div>
        </div>
        
        <div class="tool-results-split">
            <div class="tool-result-row">
                <div class="tool-result-eyebrow">Chiffre d'Affaires Mensuel</div>
                <div class="tool-result-value" style="font-size: 24px;" x-text="format(results.ca) + ' €'"></div>
            </div>
            <div class="tool-result-row">
                <div class="tool-result-eyebrow">Total Cotisations</div>
                <div class="tool-result-value accent" style="font-size: 24px;" x-text="format(results.taxes) + ' €'"></div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('calculateurTjm', () => ({
        netGoal: 3000,
        days: 15,
        chargesRate: 21.1,
        expenses: 150,
        securityMargin: true,
        
        get results() {
            let net = parseFloat(this.netGoal) || 0;
            let d = parseFloat(this.days) || 1;
            let rate = (parseFloat(this.chargesRate) || 0) / 100;
            let exp = parseFloat(this.expenses) || 0;

            let baseCA = (net + exp) / (1 - rate);

            if (this.securityMargin) {
                baseCA = baseCA * 1.10;
            }

            let tjm = baseCA / d;
            let taxes = baseCA * rate;

            return { tjm, ca: baseCA, taxes };
        },

        format(val) {
            return new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(Math.ceil(val));
        }
    }));
});
</script>
