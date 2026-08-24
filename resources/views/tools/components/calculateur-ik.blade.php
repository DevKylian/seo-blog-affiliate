<div x-data="calculateurIk()" class="tool-calculator-layout">
    
    <div class="tool-inputs-grid">
        <!-- Type de véhicule -->
        <div class="tool-input-group">
            <label class="tool-field-label">
                Type de véhicule
                <span>Voiture, moto ou scooter (cyclomoteur)</span>
                <select x-model="vehicleType" class="tool-text-input">
                    <option value="auto">Automobile</option>
                    <option value="moto">Moto (> 50cc)</option>
                    <option value="cyclo">Scooter / Cyclomoteur (< 50cc)</option>
                </select>
            </label>
        </div>

        <!-- Puissance Fiscale (CV) -->
        <div class="tool-input-group">
            <label class="tool-field-label">
                Puissance fiscale
                <span>Indiquée sur la carte grise (champ P.6)</span>
                
                <select x-show="vehicleType === 'auto'" x-model="cvAuto" class="tool-text-input">
                    <option value="3">3 CV et moins</option>
                    <option value="4">4 CV</option>
                    <option value="5">5 CV</option>
                    <option value="6">6 CV</option>
                    <option value="7">7 CV et plus</option>
                </select>

                <select x-show="vehicleType === 'moto'" x-model="cvMoto" class="tool-text-input" style="display: none;">
                    <option value="1">1 ou 2 CV</option>
                    <option value="3">3, 4 ou 5 CV</option>
                    <option value="6">6 CV et plus</option>
                </select>

                <select x-show="vehicleType === 'cyclo'" disabled class="tool-text-input" style="display: none;">
                    <option>Non applicable</option>
                </select>
            </label>
        </div>

        <!-- Kilométrage -->
        <div class="tool-input-group" style="grid-column: 1 / -1;">
            <label class="tool-field-label">
                Kilométrage professionnel annuel (km)
                <span>Seuls les trajets à but professionnel sont déductibles</span>
                <input type="number" x-model.number="distance" min="0" step="100" class="tool-text-input">
            </label>
        </div>
    </div>

    <!-- Majoration électrique -->
    <label class="tool-checkbox-card">
        <input type="checkbox" x-model="isElectric">
        <div class="tool-checkbox-card-info">
            Véhicule 100% électrique
            <div class="tool-checkbox-card-desc">Le barème est majoré de 20% pour les véhicules purement électriques.</div>
        </div>
    </label>

    <!-- Résultats -->
    <div class="tool-results-panel">
        <div class="tool-result-row tool-result-card-highlight">
            <div class="tool-result-eyebrow" style="color: var(--tool-accent);">Frais Kilométriques Déductibles (Barème 2026)</div>
            <div class="tool-result-value" x-text="format(results.amount) + ' €'"></div>
        </div>
        
        <div class="tool-results-split">
            <div class="tool-result-row">
                <div class="tool-result-eyebrow">Distance prise en compte</div>
                <div class="tool-result-value" style="font-size: 24px;" x-text="distance + ' km'"></div>
            </div>
            <div class="tool-result-row">
                <div class="tool-result-eyebrow">Formule appliquée</div>
                <div class="tool-result-value accent" style="font-size: 16px; font-weight: 600; line-height: 1.4;" x-text="results.formula"></div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('calculateurIk', () => ({
        vehicleType: 'auto',
        cvAuto: '5',
        cvMoto: '3',
        distance: 8000,
        isElectric: false,
        
        get results() {
            let d = parseFloat(this.distance) || 0;
            let amount = 0;
            let formula = '';
            
            if (this.vehicleType === 'auto') {
                const cv = parseInt(this.cvAuto);
                if (d <= 5000) {
                    if (cv <= 3) { amount = d * 0.529; formula = 'd x 0.529'; }
                    else if (cv === 4) { amount = d * 0.606; formula = 'd x 0.606'; }
                    else if (cv === 5) { amount = d * 0.636; formula = 'd x 0.636'; }
                    else if (cv === 6) { amount = d * 0.665; formula = 'd x 0.665'; }
                    else { amount = d * 0.697; formula = 'd x 0.697'; }
                } else if (d <= 20000) {
                    if (cv <= 3) { amount = (d * 0.316) + 1061; formula = '(d x 0.316) + 1061'; }
                    else if (cv === 4) { amount = (d * 0.340) + 1330; formula = '(d x 0.340) + 1330'; }
                    else if (cv === 5) { amount = (d * 0.357) + 1391; formula = '(d x 0.357) + 1391'; }
                    else if (cv === 6) { amount = (d * 0.374) + 1457; formula = '(d x 0.374) + 1457'; }
                    else { amount = (d * 0.394) + 1515; formula = '(d x 0.394) + 1515'; }
                } else {
                    if (cv <= 3) { amount = d * 0.358; formula = 'd x 0.358'; }
                    else if (cv === 4) { amount = d * 0.407; formula = 'd x 0.407'; }
                    else if (cv === 5) { amount = d * 0.427; formula = 'd x 0.427'; }
                    else if (cv === 6) { amount = d * 0.447; formula = 'd x 0.447'; }
                    else { amount = d * 0.470; formula = 'd x 0.470'; }
                }
            } else if (this.vehicleType === 'moto') {
                const cv = parseInt(this.cvMoto);
                if (d <= 3000) {
                    if (cv <= 2) { amount = d * 0.395; formula = 'd x 0.395'; }
                    else if (cv <= 5) { amount = d * 0.468; formula = 'd x 0.468'; }
                    else { amount = d * 0.606; formula = 'd x 0.606'; }
                } else if (d <= 6000) {
                    if (cv <= 2) { amount = (d * 0.099) + 891; formula = '(d x 0.099) + 891'; }
                    else if (cv <= 5) { amount = (d * 0.082) + 1158; formula = '(d x 0.082) + 1158'; }
                    else { amount = (d * 0.079) + 1583; formula = '(d x 0.079) + 1583'; }
                } else {
                    if (cv <= 2) { amount = d * 0.396; formula = 'd x 0.396'; }
                    else if (cv <= 5) { amount = d * 0.275; formula = 'd x 0.275'; }
                    else { amount = d * 0.343; formula = 'd x 0.343'; }
                }
            } else if (this.vehicleType === 'cyclo') {
                if (d <= 3000) {
                    amount = d * 0.315; formula = 'd x 0.315';
                } else if (d <= 6000) {
                    amount = (d * 0.079) + 711; formula = '(d x 0.079) + 711';
                } else {
                    amount = d * 0.198; formula = 'd x 0.198';
                }
            }

            if (this.isElectric) {
                amount = amount * 1.20;
                formula += ' (Majoré de 20%)';
            }

            return { amount, formula };
        },

        format(val) {
            return new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(Math.ceil(val));
        }
    }));
});
</script>
