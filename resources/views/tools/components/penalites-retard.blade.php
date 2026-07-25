<div x-data="penalitesRetard()" class="tool-calculator-layout">
    
    <div class="tool-inputs-grid">
        <!-- Montant impayé -->
        <div class="tool-input-group">
            <label class="tool-field-label">
                Montant TTC de la facture impayée (€)
                <span>La valeur totale figurant sur la facture en souffrance</span>
                <input type="number" x-model.number="amount" min="1" step="100" class="tool-text-input">
            </label>
        </div>
        
        <!-- Jours de retard -->
        <div class="tool-input-group">
            <label class="tool-field-label">
                Nombre de jours de retard
                <span>Jours écoulés depuis la date d'échéance de paiement</span>
                <input type="number" x-model.number="days" min="1" step="1" class="tool-text-input">
            </label>
        </div>

        <!-- Taux applicable -->
        <div class="tool-input-group">
            <label class="tool-field-label">
                Taux des pénalités
                <span>Le taux d'intérêt contractuel ou légal par an</span>
                <select x-model.number="rate" class="tool-select-input">
                    <option value="14.25">Taux légal (BCE + 10 points) - Recommandé</option>
                    <option value="12">Taux standard (3 fois le taux d'intérêt légal)</option>
                </select>
            </label>
        </div>
    </div>

    <!-- Indemnité de recouvrement -->
    <label class="tool-checkbox-card">
        <input type="checkbox" x-model="recoveryFee" id="recovery">
        <div class="tool-checkbox-card-info">
            Ajouter l'indemnité forfaitaire de recouvrement (+40€)
            <div class="tool-checkbox-card-desc">Indemnité forfaitaire due légalement pour frais de recouvrement (transactions B2B uniquement)</div>
        </div>
    </label>

    <!-- Résultats -->
    <div class="tool-results-panel" style="background: radial-gradient(circle at top right, rgba(239, 68, 68, 0.03) 0%, rgba(248, 250, 252, 0) 60%), #f8fafc; border-color: #fca5a5;">
        <div class="tool-result-row">
            <div class="tool-result-eyebrow" style="color: #b91c1c;">Total des pénalités dues</div>
            <div class="tool-result-value danger" style="font-size: 48px;" x-text="format(results.totalPenalties) + ' €'"></div>
        </div>
        
        <div class="tool-results-split" style="border-top: 1px solid #fecaca; padding-top: 20px;">
            <div class="tool-result-row">
                <div class="tool-result-eyebrow">Intérêts de retard</div>
                <div class="tool-result-value" style="font-size: 24px;" x-text="format(results.interest) + ' €'"></div>
            </div>
            <div class="tool-result-row">
                <div class="tool-result-eyebrow">Nouveau total à facturer</div>
                <div class="tool-result-value" style="font-size: 24px; color: var(--tool-primary);" x-text="format(results.totalDue) + ' €'"></div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('penalitesRetard', () => ({
        amount: 2000,
        days: 30,
        rate: 14.25,
        recoveryFee: true,
        
        get results() {
            let amt = parseFloat(this.amount) || 0;
            let d = parseFloat(this.days) || 0;
            let r = (parseFloat(this.rate) || 0) / 100;
            
            // Intérêts de retard = (Montant dû * Taux * Nombre de jours de retard) / 365
            let interest = (amt * r * d) / 365;
            
            let fee = this.recoveryFee ? 40 : 0;
            let totalPenalties = interest + fee;
            let totalDue = amt + totalPenalties;

            return { interest, fee, totalPenalties, totalDue };
        },

        format(val) {
            return new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(val);
        }
    }));
});
</script>
