<div x-data="comparateurStatuts()" class="tool-calculator-layout" style="max-width: 800px;">
    
    <div class="tool-inputs-grid" style="max-width: 600px; margin: 0 auto; width: 100%;">
        <!-- Chiffre d'Affaires -->
        <div class="tool-input-group">
            <label class="tool-field-label">
                Chiffre d'Affaires Annuel HT (€)
                <span>Estimation de votre facturation brute annuelle</span>
                <input type="number" x-model.number="ca" min="0" step="1000" class="tool-text-input">
            </label>
        </div>
        
        <!-- Frais pros -->
        <div class="tool-input-group">
            <label class="tool-field-label">
                Frais professionnels annuels HT (€)
                <span>Vos dépenses d'exploitation déductibles (logiciels, hébergement, loyer, déplacements...)</span>
                <input type="number" x-model.number="expenses" min="0" step="500" class="tool-text-input">
            </label>
        </div>
    </div>

    <!-- Résultats Comparaison -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 24px;">
        
        <!-- Micro-entreprise -->
        <div style="background: white; border-radius: 20px; border: 1px solid var(--tool-border); padding: 32px 24px; box-shadow: 0 10px 30px -10px rgba(15,23,42,0.04); text-align: center; position:relative; display: flex; flex-direction: column;" :style="results.best === 'micro' ? 'border-color: var(--tool-accent); box-shadow: 0 10px 30px -5px rgba(37,99,235,0.08);' : ''">
            <div x-show="results.best === 'micro'" style="position:absolute; top:-12px; left:50%; transform:translateX(-50%); background:var(--tool-accent); color:white; font-size:10px; font-weight:800; padding:4px 14px; border-radius:20px; text-transform:uppercase; letter-spacing:0.05em; white-space: nowrap;">Choix le plus rentable</div>
            <h3 style="font-size: 18px; font-weight: 800; color: var(--tool-primary); margin-bottom: 24px; font-family:'Manrope';">Micro-entreprise (BNC)</h3>
            <div style="font-size: 12px; color: var(--tool-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">Reste net dans votre poche</div>
            <div style="font-size: 32px; font-weight: 900; color: var(--tool-primary); margin-bottom: 24px; flex-grow: 1; display: flex; align-items: center; justify-content: center;" :style="results.best === 'micro' ? 'color: var(--tool-accent);' : ''" x-text="format(results.micro.net) + ' €'"></div>
            <ul style="list-style:none; padding:16px 0 0; margin:16px 0 0; border-top: 1px solid var(--tool-border); font-size:13px; color:var(--tool-text); display:grid; gap:10px; text-align:left;">
                <li style="display:flex; justify-content:space-between;"><span>URSSAF (21.1%)</span> <strong x-text="format(results.micro.urssaf) + ' €'"></strong></li>
                <li style="display:flex; justify-content:space-between; color: var(--tool-muted);"><span>Frais non déduits</span> <strong x-text="format(results.expenses) + ' €'"></strong></li>
            </ul>
        </div>

        <!-- EURL -->
        <div style="background: white; border-radius: 20px; border: 1px solid var(--tool-border); padding: 32px 24px; box-shadow: 0 10px 30px -10px rgba(15,23,42,0.04); text-align: center; position:relative; display: flex; flex-direction: column;" :style="results.best === 'eurl' ? 'border-color: var(--tool-accent); box-shadow: 0 10px 30px -5px rgba(37,99,235,0.08);' : ''">
            <div x-show="results.best === 'eurl'" style="position:absolute; top:-12px; left:50%; transform:translateX(-50%); background:var(--tool-accent); color:white; font-size:10px; font-weight:800; padding:4px 14px; border-radius:20px; text-transform:uppercase; letter-spacing:0.05em; white-space: nowrap;">Choix le plus rentable</div>
            <h3 style="font-size: 18px; font-weight: 800; color: var(--tool-primary); margin-bottom: 24px; font-family:'Manrope';">EURL (Rémunération)</h3>
            <div style="font-size: 12px; color: var(--tool-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">Reste net dans votre poche</div>
            <div style="font-size: 32px; font-weight: 900; color: var(--tool-primary); margin-bottom: 24px; flex-grow: 1; display: flex; align-items: center; justify-content: center;" :style="results.best === 'eurl' ? 'color: var(--tool-accent);' : ''" x-text="format(results.eurl.net) + ' €'"></div>
            <ul style="list-style:none; padding:16px 0 0; margin:16px 0 0; border-top: 1px solid var(--tool-border); font-size:13px; color:var(--tool-text); display:grid; gap:10px; text-align:left;">
                <li style="display:flex; justify-content:space-between;"><span>Charges TNS (~45%)</span> <strong x-text="format(results.eurl.charges) + ' €'"></strong></li>
                <li style="display:flex; justify-content:space-between; color: var(--tool-green);"><span>Frais pros déduits</span> <strong x-text="format(results.expenses) + ' €'"></strong></li>
            </ul>
        </div>

        <!-- SASU -->
        <div style="background: white; border-radius: 20px; border: 1px solid var(--tool-border); padding: 32px 24px; box-shadow: 0 10px 30px -10px rgba(15,23,42,0.04); text-align: center; position:relative; display: flex; flex-direction: column;" :style="results.best === 'sasu' ? 'border-color: var(--tool-accent); box-shadow: 0 10px 30px -5px rgba(37,99,235,0.08);' : ''">
            <div x-show="results.best === 'sasu'" style="position:absolute; top:-12px; left:50%; transform:translateX(-50%); background:var(--tool-accent); color:white; font-size:10px; font-weight:800; padding:4px 14px; border-radius:20px; text-transform:uppercase; letter-spacing:0.05em; white-space: nowrap;">Choix le plus rentable</div>
            <h3 style="font-size: 18px; font-weight: 800; color: var(--tool-primary); margin-bottom: 24px; font-family:'Manrope';">SASU (100% Dividendes)</h3>
            <div style="font-size: 12px; color: var(--tool-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">Reste net dans votre poche</div>
            <div style="font-size: 32px; font-weight: 900; color: var(--tool-primary); margin-bottom: 24px; flex-grow: 1; display: flex; align-items: center; justify-content: center;" :style="results.best === 'sasu' ? 'color: var(--tool-accent);' : ''" x-text="format(results.sasu.net) + ' €'"></div>
            <ul style="list-style:none; padding:16px 0 0; margin:16px 0 0; border-top: 1px solid var(--tool-border); font-size:13px; color:var(--tool-text); display:grid; gap:10px; text-align:left;">
                <li style="display:flex; justify-content:space-between;"><span>Impôt Sociétés (IS)</span> <strong x-text="format(results.sasu.is) + ' €'"></strong></li>
                <li style="display:flex; justify-content:space-between;"><span>Flat Tax (30%)</span> <strong x-text="format(results.sasu.pfu) + ' €'"></strong></li>
            </ul>
        </div>

    </div>
    <div class="tool-meta-note">
        * Estimation simplifiée pour information (hors impôt sur le revenu personnel, CFE, taxes consulaires). L'EURL maximise la protection sociale TNS, la SASU optimise les dividendes fiscaux (idéal avec ARE), et la Micro-entreprise minimise les démarches et coûts administratifs.
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('comparateurStatuts', () => ({
        ca: 50000,
        expenses: 5000,
        
        get results() {
            let c = parseFloat(this.ca) || 0;
            let e = parseFloat(this.expenses) || 0;
            
            // Micro BNC (Services)
            let mUrssaf = c * 0.211;
            let mNet = c - mUrssaf - e;
            if (mNet < 0) mNet = 0;

            // EURL (Rémunération)
            let benefice = Math.max(0, c - e);
            let eNet = benefice / 1.45; // ~45% de charges sur le net
            let eCharges = benefice - eNet;

            // SASU (Dividendes)
            let sIs = 0;
            if (benefice <= 42500) {
                sIs = benefice * 0.15;
            } else {
                sIs = (42500 * 0.15) + ((benefice - 42500) * 0.25);
            }
            let sDiv = benefice - sIs;
            let sPfu = sDiv * 0.30;
            let sNet = sDiv - sPfu;

            // Find best
            let best = 'micro';
            let max = mNet;
            if (eNet > max) { best = 'eurl'; max = eNet; }
            if (sNet > max) { best = 'sasu'; max = sNet; }

            return {
                expenses: e,
                best: best,
                micro: { net: mNet, urssaf: mUrssaf },
                eurl: { net: eNet, charges: eCharges },
                sasu: { net: sNet, is: sIs, pfu: sPfu }
            };
        },

        format(val) {
            return new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(Math.ceil(val));
        }
    }));
});
</script>
