<div class="calculator-widget net-income-calculator">
    <div class="calculator-header">
        <h3>Simulateur de revenu net (Micro-entreprise)</h3>
        <p>Estimez vos cotisations et votre revenu net mensuel</p>
    </div>
    
    <div class="calculator-body">
        <div class="calculator-row">
            <div class="input-group">
                <label for="income-ca">Chiffre d'affaires mensuel (€)</label>
                <input type="number" id="income-ca" value="3000" min="0" step="10">
            </div>
            <div class="input-group">
                <label for="income-activity">Type d'activité</label>
                <select id="income-activity">
                    <option value="21.1">Prestations de services (21.1%)</option>
                    <option value="12.3">Vente de marchandises (12.3%)</option>
                    <option value="21.2">Professions libérales (21.2%)</option>
                </select>
            </div>
        </div>

        <div class="calculator-row">
            <div class="input-group" style="flex: 0 0 auto;">
                <label style="display:flex; align-items:center; gap:8px; font-weight:normal; cursor:pointer;">
                    <input type="checkbox" id="income-accre" style="width: auto;">
                    Bénéficiaire de l'ACRE (réduction 50%)
                </label>
            </div>
        </div>

        <div class="calculator-results">
            <div class="result-box">
                <span class="result-label">Cotisations URSSAF</span>
                <strong class="result-value" id="income-result-tax">633.00 €</strong>
            </div>
            <div class="result-box highlighted" style="grid-column: span 2;">
                <span class="result-label">Revenu Net Estimé</span>
                <strong class="result-value" id="income-result-net">2367.00 €</strong>
            </div>
        </div>
        
        <p style="font-size: 10px; color: var(--pub-text-muted, #475569); margin-top: 16px; text-align: center;">
            * Estimation indicative, hors impôt sur le revenu et frais professionnels annexes.
        </p>
    </div>
</div>

<style>
/* It shares styles with the TVA calculator, only needs specific overrides if any */
.net-income-calculator .result-box.highlighted {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
}
.net-income-calculator .result-box.highlighted .result-value {
    color: #166534;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const caInput = document.getElementById('income-ca');
    const activitySelect = document.getElementById('income-activity');
    const accreCheck = document.getElementById('income-accre');
    
    const resultTax = document.getElementById('income-result-tax');
    const resultNet = document.getElementById('income-result-net');

    function formatCurrency(val) {
        return parseFloat(val).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
    }

    function calculate() {
        const ca = parseFloat(caInput.value) || 0;
        let rate = parseFloat(activitySelect.value) / 100;
        const hasAccre = accreCheck.checked;
        
        if (hasAccre) {
            rate = rate / 2;
        }
        
        const tax = ca * rate;
        const net = ca - tax;

        resultTax.textContent = formatCurrency(tax);
        resultNet.textContent = formatCurrency(net);
    }

    caInput.addEventListener('input', calculate);
    activitySelect.addEventListener('change', calculate);
    accreCheck.addEventListener('change', calculate);
    
    // Initial calculate
    calculate();
});
</script>
