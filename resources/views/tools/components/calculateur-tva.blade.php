<div x-data="calculateurTva()" class="tool-calculator-layout">
    
    <div class="tool-inputs-grid">
        <!-- Montant -->
        <div class="tool-input-group">
            <label class="tool-field-label">
                Montant initial (€)
                <span>Entrez la valeur numérique à convertir</span>
                <input type="number" x-model.number="amount" min="0" step="0.01" class="tool-text-input">
            </label>
        </div>
        
        <!-- Type de montant -->
        <div class="tool-input-group">
            <label class="tool-field-label">
                Ce montant est
                <span>Indiquez si le montant de départ est HT ou TTC</span>
                <select x-model="type" class="tool-select-input">
                    <option value="ht">Hors Taxes (HT)</option>
                    <option value="ttc">Toutes Taxes Comprises (TTC)</option>
                </select>
            </label>
        </div>

        <!-- Taux TVA -->
        <div class="tool-input-group">
            <label class="tool-field-label">
                Taux de TVA
                <span>Sélectionnez le taux légal applicable en France</span>
                <select x-model.number="rate" class="tool-select-input">
                    <option value="20">20 % (Standard - Services, Ventes)</option>
                    <option value="10">10 % (Intermédiaire - Restauration, Travaux)</option>
                    <option value="5.5">5.5 % (Réduit - Livres, Produits de première nécessité)</option>
                    <option value="2.1">2.1 % (Particulier - Médicaments remboursés, Presse)</option>
                </select>
            </label>
        </div>
    </div>

    <!-- Résultats -->
    <div class="tool-results-panel">
        <div class="tool-result-row">
            <div class="tool-result-eyebrow">Montant HT</div>
            <div class="tool-result-value" x-text="format(results.ht) + ' €'"></div>
        </div>
        <div class="tool-result-row">
            <div class="tool-result-eyebrow">Montant TVA (<span x-text="rate"></span>%)</div>
            <div class="tool-result-value accent" x-text="format(results.tva) + ' €'"></div>
        </div>
        <div class="tool-result-row tool-result-card-highlight">
            <div class="tool-result-eyebrow" style="color: var(--tool-accent);">Montant TTC</div>
            <div class="tool-result-value" x-text="format(results.ttc) + ' €'"></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('calculateurTva', () => ({
        amount: 1000,
        type: 'ht',
        rate: 20,
        
        get results() {
            let ht = 0;
            let tva = 0;
            let ttc = 0;
            let amt = parseFloat(this.amount) || 0;
            let r = parseFloat(this.rate) / 100;

            if (this.type === 'ht') {
                ht = amt;
                tva = ht * r;
                ttc = ht + tva;
            } else {
                ttc = amt;
                ht = ttc / (1 + r);
                tva = ttc - ht;
            }

            return { ht, tva, ttc };
        },

        format(val) {
            return new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(val);
        }
    }));
});
</script>
