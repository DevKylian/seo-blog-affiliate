<div x-data="calculateurTvaMarge()" class="tool-calculator-layout">
    
    <div class="tool-inputs-grid">
        <div class="tool-input-group" style="grid-column: 1 / -1;">
            <div style="background: #e0f2fe; border-left: 4px solid #3b82f6; padding: 16px; border-radius: 0 8px 8px 0; margin-bottom: 24px;">
                <p style="margin: 0; font-size: 14px; color: #0369a1; line-height: 1.5;"><strong>Le régime de la TVA sur marge</strong> permet aux revendeurs de biens d'occasion (friperie, véhicules d'occasion, antiquités) de ne payer la TVA que sur leur marge nette, et non sur le prix de vente total.</p>
            </div>
        </div>

        <div class="tool-input-group">
            <label class="tool-field-label">
                Prix d'achat TTC du bien (€)
                <span>Combien vous avez payé l'objet (sans pouvoir récupérer la TVA)</span>
                <input type="number" x-model.number="prixAchat" min="0" step="1" class="tool-text-input">
            </label>
        </div>

        <div class="tool-input-group">
            <label class="tool-field-label">
                Prix de revente TTC (€)
                <span>Combien votre client final vous paie</span>
                <input type="number" x-model.number="prixVente" min="0" step="1" class="tool-text-input">
            </label>
        </div>

        <div class="tool-input-group" style="grid-column: 1 / -1;">
            <label class="tool-field-label">
                Taux de TVA applicable sur ce bien
                <span>En général 20% en France (parfois 5.5% ou 10% selon les objets)</span>
                <select x-model.number="tauxTva" class="tool-text-input">
                    <option value="20">Taux Normal (20%)</option>
                    <option value="10">Taux Intermédiaire (10%)</option>
                    <option value="5.5">Taux Réduit (5.5%)</option>
                </select>
            </label>
        </div>
    </div>

    <!-- Résultats -->
    <div class="tool-results-panel">
        <div class="tool-result-row tool-result-card-highlight">
            <div class="tool-result-eyebrow" style="color: var(--tool-accent);">TVA à reverser à l'État</div>
            <div class="tool-result-value" x-text="formatAmount(results.tvaDue)"></div>
        </div>
        
        <div class="tool-results-split">
            <div class="tool-result-row">
                <div class="tool-result-eyebrow">Marge Totale (TTC)</div>
                <div class="tool-result-value" style="font-size: 24px;" x-text="formatAmount(results.margeTtc)"></div>
            </div>
            <div class="tool-result-row">
                <div class="tool-result-eyebrow">Marge Nette (HT)</div>
                <div class="tool-result-value accent" style="font-size: 24px; color: #10b981;" x-text="formatAmount(results.margeHt)"></div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('calculateurTvaMarge', () => ({
        prixAchat: 50,
        prixVente: 120,
        tauxTva: 20,
        
        get results() {
            let pa = parseFloat(this.prixAchat) || 0;
            let pv = parseFloat(this.prixVente) || 0;
            let tvaRate = parseFloat(this.tauxTva) || 20;

            let margeTtc = Math.max(0, pv - pa);
            let margeHt = margeTtc / (1 + (tvaRate / 100));
            let tvaDue = margeTtc - margeHt;

            return {
                margeTtc: margeTtc,
                margeHt: margeHt,
                tvaDue: tvaDue
            };
        },

        formatAmount(val) {
            return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(val);
        }
    }));
});
</script>
