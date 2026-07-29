<div class="calculator-widget tva-calculator">
    <div class="calculator-header">
        <h3>Calculateur de TVA</h3>
        <p>Calculez rapidement un montant HT ou TTC</p>
    </div>
    
    <div class="calculator-body">
        <div class="calculator-row">
            <div class="input-group">
                <label for="tva-amount">Montant (€)</label>
                <input type="number" id="tva-amount" value="100" min="0" step="0.01">
            </div>
            <div class="input-group">
                <label for="tva-rate">Taux de TVA</label>
                <select id="tva-rate">
                    <option value="20">20% (Standard)</option>
                    <option value="10">10% (Intermédiaire)</option>
                    <option value="5.5">5.5% (Réduit)</option>
                    <option value="2.1">2.1% (Particulier)</option>
                </select>
            </div>
        </div>

        <div class="calculator-toggle">
            <label class="toggle-option">
                <input type="radio" name="tva-direction" value="ht-ttc" checked>
                <span>HT vers TTC</span>
            </label>
            <label class="toggle-option">
                <input type="radio" name="tva-direction" value="ttc-ht">
                <span>TTC vers HT</span>
            </label>
        </div>

        <div class="calculator-results">
            <div class="result-box">
                <span class="result-label">Montant HT</span>
                <strong class="result-value" id="tva-result-ht">100.00 €</strong>
            </div>
            <div class="result-box highlighted">
                <span class="result-label">Montant TVA</span>
                <strong class="result-value" id="tva-result-tax">20.00 €</strong>
            </div>
            <div class="result-box">
                <span class="result-label">Montant TTC</span>
                <strong class="result-value" id="tva-result-ttc">120.00 €</strong>
            </div>
        </div>
    </div>
</div>

<style>
.calculator-widget {
    background: #ffffff;
    border: 1px solid var(--pub-border, #e2e8f0);
    border-radius: 12px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    margin: 32px 0;
    overflow: hidden;
    font-family: 'DM Sans', sans-serif;
}
.calculator-header {
    background: #f8fafc;
    padding: 20px 24px;
    border-bottom: 1px solid var(--pub-border, #e2e8f0);
}
.calculator-header h3 {
    margin: 0 0 4px 0;
    font-family: 'Manrope', sans-serif;
    color: var(--pub-text-main, #0f172a);
}
.calculator-header p {
    margin: 0;
    font-size: 13px;
    color: var(--pub-text-muted, #475569);
}
.calculator-body {
    padding: 24px;
}
.calculator-row {
    display: flex;
    gap: 16px;
    margin-bottom: 20px;
}
.input-group {
    flex: 1;
    display: flex;
    flex-direction: column;
}
.input-group label {
    font-size: 12px;
    font-weight: 700;
    color: var(--pub-text-main, #0f172a);
    margin-bottom: 8px;
}
.input-group input, .input-group select {
    padding: 12px 16px;
    border: 1px solid var(--pub-border, #e2e8f0);
    border-radius: 8px;
    font-size: 14px;
    outline: none;
    transition: border-color 0.2s;
}
.input-group input:focus, .input-group select:focus {
    border-color: var(--pub-primary, #2563eb);
}
.calculator-toggle {
    display: flex;
    gap: 12px;
    margin-bottom: 24px;
    background: #f1f5f9;
    padding: 4px;
    border-radius: 8px;
}
.toggle-option {
    flex: 1;
    text-align: center;
}
.toggle-option input {
    display: none;
}
.toggle-option span {
    display: block;
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    transition: all 0.2s;
}
.toggle-option input:checked + span {
    background: #ffffff;
    color: var(--pub-primary, #2563eb);
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.calculator-results {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    padding-top: 20px;
    border-top: 1px dashed var(--pub-border, #e2e8f0);
}
.result-box {
    text-align: center;
    padding: 16px;
    border-radius: 8px;
    background: #f8fafc;
}
.result-box.highlighted {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
}
.result-label {
    display: block;
    font-size: 11px;
    text-transform: uppercase;
    font-weight: 700;
    color: #64748b;
    margin-bottom: 6px;
}
.result-value {
    display: block;
    font-size: 20px;
    font-family: 'Manrope', sans-serif;
    color: var(--pub-text-main, #0f172a);
}
.result-box.highlighted .result-value {
    color: var(--pub-primary, #2563eb);
}
@media (max-width: 600px) {
    .calculator-row {
        flex-direction: column;
    }
    .calculator-results {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const amountInput = document.getElementById('tva-amount');
    const rateSelect = document.getElementById('tva-rate');
    const directionRadios = document.querySelectorAll('input[name="tva-direction"]');
    
    const resultHt = document.getElementById('tva-result-ht');
    const resultTax = document.getElementById('tva-result-tax');
    const resultTtc = document.getElementById('tva-result-ttc');

    function formatCurrency(val) {
        return parseFloat(val).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
    }

    function calculate() {
        const amount = parseFloat(amountInput.value) || 0;
        const rate = parseFloat(rateSelect.value) / 100;
        const direction = document.querySelector('input[name="tva-direction"]:checked').value;
        
        let ht, tax, ttc;

        if (direction === 'ht-ttc') {
            ht = amount;
            tax = ht * rate;
            ttc = ht + tax;
        } else {
            ttc = amount;
            ht = ttc / (1 + rate);
            tax = ttc - ht;
        }

        resultHt.textContent = formatCurrency(ht);
        resultTax.textContent = formatCurrency(tax);
        resultTtc.textContent = formatCurrency(ttc);
    }

    amountInput.addEventListener('input', calculate);
    rateSelect.addEventListener('change', calculate);
    directionRadios.forEach(radio => radio.addEventListener('change', calculate));
});
</script>
