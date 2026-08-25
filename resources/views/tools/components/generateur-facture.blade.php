<div x-data="invoiceGenerator()" class="tool-calculator-layout generateur-facture">
    
    <div class="tool-inputs-grid no-print">
        <!-- Informations de l'émetteur -->
        <div class="tool-input-group" style="grid-column: 1 / -1;">
            <h3 style="font-size: 16px; font-weight: 800; color: #0f172a; margin-bottom: 12px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px;">Vos informations (Émetteur)</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <label class="tool-field-label">
                    Nom / Raison sociale
                    <input type="text" x-model="seller.name" placeholder="Ex: Jean Dupont ou MonAgence SAS" class="tool-text-input">
                </label>
                <label class="tool-field-label">
                    SIRET
                    <input type="text" x-model="seller.siret" placeholder="14 chiffres" class="tool-text-input">
                </label>
                <label class="tool-field-label" style="grid-column: 1 / -1;">
                    Adresse
                    <input type="text" x-model="seller.address" placeholder="123 rue de la République, 75001 Paris" class="tool-text-input">
                </label>
            </div>
        </div>

        <!-- Informations du client -->
        <div class="tool-input-group" style="grid-column: 1 / -1;">
            <h3 style="font-size: 16px; font-weight: 800; color: #0f172a; margin-bottom: 12px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px;">Informations du client</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <label class="tool-field-label">
                    Nom du client
                    <input type="text" x-model="client.name" placeholder="Ex: Acme Corp" class="tool-text-input">
                </label>
                <label class="tool-field-label">
                    Adresse du client
                    <input type="text" x-model="client.address" placeholder="Adresse complète" class="tool-text-input">
                </label>
            </div>
        </div>

        <!-- Détails de la facture -->
        <div class="tool-input-group" style="grid-column: 1 / -1;">
            <h3 style="font-size: 16px; font-weight: 800; color: #0f172a; margin-bottom: 12px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px;">Détails du document</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                <label class="tool-field-label">
                    Numéro
                    <input type="text" x-model="invoice.number" placeholder="F2026-001" class="tool-text-input">
                </label>
                <label class="tool-field-label">
                    Date d'émission
                    <input type="date" lang="fr-FR" x-model="invoice.date" class="tool-text-input">
                </label>
                <label class="tool-field-label">
                    Date d'échéance
                    <input type="date" lang="fr-FR" x-model="invoice.dueDate" class="tool-text-input">
                </label>
            </div>
        </div>

        <!-- Lignes de facturation -->
        <div class="tool-input-group" style="grid-column: 1 / -1;">
            <h3 style="font-size: 16px; font-weight: 800; color: #0f172a; margin-bottom: 12px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px;">Prestations / Produits</h3>
            
            <template x-for="(item, index) in items" :key="index">
                <div style="display: grid; grid-template-columns: 3fr 1fr 1.5fr 1fr auto; gap: 12px; align-items: end; margin-bottom: 12px; background: #f8fafc; padding: 12px; border-radius: 8px;">
                    <label class="tool-field-label" style="margin: 0;">
                        Description
                        <input type="text" x-model="item.desc" placeholder="Développement site web" class="tool-text-input">
                    </label>
                    <label class="tool-field-label" style="margin: 0;">
                        Qté
                        <input type="number" x-model.number="item.qty" min="1" class="tool-text-input">
                    </label>
                    <label class="tool-field-label" style="margin: 0;">
                        Prix Unitaire HT
                        <input type="number" x-model.number="item.price" min="0" step="10" class="tool-text-input">
                    </label>
                    <label class="tool-field-label" style="margin: 0;">
                        TVA (%)
                        <select x-model.number="item.tva" class="tool-text-input">
                            <option value="0">0%</option>
                            <option value="5.5">5.5%</option>
                            <option value="10">10%</option>
                            <option value="20">20%</option>
                        </select>
                    </label>
                    <button @click="removeItem(index)" style="background: #fee2e2; color: #ef4444; border: none; border-radius: 8px; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s;" onmouseover="this.style.background='#fecaca'" onmouseout="this.style.background='#fee2e2'">
                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </button>
                </div>
            </template>
            
            <button @click="addItem()" style="margin-top: 8px; padding: 10px 16px; background: #f1f5f9; color: #0f172a; border: 1px dashed #cbd5e1; border-radius: 8px; font-weight: 700; cursor: pointer; width: 100%; transition: 0.2s;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">
                + Ajouter une ligne
            </button>
        </div>

        <div class="tool-input-group" style="grid-column: 1 / -1;">
            <label class="tool-checkbox-card">
                <input type="checkbox" x-model="isAutoEntrepreneur">
                <div class="tool-checkbox-card-info">
                    Franchise en base de TVA (Auto-entrepreneur)
                    <div class="tool-checkbox-card-desc">Ajoute la mention légale "TVA non applicable, art. 293 B du CGI" et force la TVA à 0%.</div>
                </div>
            </label>
        </div>

        <!-- Mentions légales & RIB -->
        <div class="tool-input-group" style="grid-column: 1 / -1;">
            <h3 style="font-size: 16px; font-weight: 800; color: #0f172a; margin-bottom: 12px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px;">Mentions légales & Paiement</h3>
            <label class="tool-field-label">
                IBAN / RIB (optionnel)
                <input type="text" x-model="invoice.iban" placeholder="FR76 1234 5678..." class="tool-text-input">
            </label>
            <label class="tool-field-label">
                Mentions supplémentaires (Pénalités de retard, conditions...)
                <textarea x-model="invoice.notes" class="tool-text-input" style="min-height: 80px;" placeholder="Pénalités de retard applicables : 3 fois le taux d'intérêt légal..."></textarea>
            </label>
        </div>

        <!-- Capture Email -->
        <div class="tool-input-group" style="grid-column: 1 / -1; margin-top: 8px; background: #eff6ff; padding: 24px; border-radius: 12px; border: 1px solid #bfdbfe;">
            <h4 style="font-size: 15px; font-weight: 800; color: #1e3a8a; margin-bottom: 8px;">Recevoir cette facture par email (+ notre Kit de Démarrage Gratuit)</h4>
            <p style="font-size: 13px; color: #3b82f6; margin-bottom: 16px;">Vous n'êtes pas prêt à créer un compte Indy ? Pas de problème. Entrez votre email pour recevoir votre document et nos conseils pour freelances.</p>
            <div style="display: flex; gap: 8px;" x-data="{ emailSent: false, email: '' }">
                <template x-if="!emailSent">
                    <div style="display: flex; gap: 8px; width: 100%;">
                        <input type="email" x-model="email" placeholder="votre@email.com" class="tool-text-input" style="flex: 1; border-color: #93c5fd;">
                        <button type="button" @click="if(email) { emailSent = true; setTimeout(() => emailSent = false, 4000); email = ''; }" style="padding: 10px 20px; background: #2563eb; color: white; border: none; border-radius: 8px; font-weight: 700; cursor: pointer;">M'envoyer la facture</button>
                    </div>
                </template>
                <template x-if="emailSent">
                    <div style="color: #059669; font-weight: 700; display: flex; align-items: center; gap: 8px; padding: 10px 0;">
                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                        C'est envoyé ! Vérifiez votre boîte mail d'ici quelques minutes.
                    </div>
                </template>
            </div>
        </div>
        
        <div style="grid-column: 1 / -1; margin-top: 16px;">
            <button @click="window.print()" style="width: 100%; padding: 18px; background: #0f172a; color: white; border: none; border-radius: 12px; font-size: 18px; font-weight: 800; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.2); transition: 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 16px rgba(15, 23, 42, 0.3)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 4px 12px rgba(15, 23, 42, 0.2)';">
                <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                Télécharger en PDF (Imprimer)
            </button>
        </div>
    </div>

    <!-- Aperçu de la facture (C'est ce qui sera imprimé) -->
    <div class="invoice-preview print-area" style="background: white; padding: 40px; border: 1px solid #e2e8f0; border-radius: 12px; margin-top: 32px; color: #0f172a;">
        
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 60px;">
            <div>
                <h1 style="font-size: 32px; font-weight: 900; letter-spacing: -0.02em; color: #0f172a; margin: 0 0 24px 0; line-height: 1;">FACTURE</h1>
                <div style="font-weight: 700; font-size: 18px;" x-text="seller.name || 'Votre Nom/Entreprise'"></div>
                <div style="color: #475569; font-size: 14px; margin-top: 4px;" x-text="seller.address || 'Votre adresse complète'"></div>
                <div style="color: #475569; font-size: 14px; margin-top: 4px;" x-show="seller.siret">SIRET: <span x-text="seller.siret"></span></div>
            </div>
            
            <div style="text-align: right; background: #f8fafc; padding: 24px; border-radius: 8px; min-width: 250px;">
                <div style="font-size: 12px; text-transform: uppercase; font-weight: 800; color: #64748b; margin-bottom: 8px;">Facturé à</div>
                <div style="font-weight: 700; font-size: 16px;" x-text="client.name || 'Nom du client'"></div>
                <div style="color: #475569; font-size: 14px; margin-top: 4px;" x-text="client.address || 'Adresse du client'"></div>
            </div>
        </div>

        <div style="display: flex; gap: 40px; margin-bottom: 40px; border-top: 2px solid #f1f5f9; border-bottom: 2px solid #f1f5f9; padding: 16px 0;">
            <div>
                <div style="font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase;">N° Facture</div>
                <div style="font-weight: 800; font-size: 16px;" x-text="invoice.number || 'F2026-001'"></div>
            </div>
            <div>
                <div style="font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase;">Date d'émission</div>
                <div style="font-weight: 800; font-size: 16px;" x-text="formatDate(invoice.date)"></div>
            </div>
            <div>
                <div style="font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase;">Échéance</div>
                <div style="font-weight: 800; font-size: 16px;" x-text="formatDate(invoice.dueDate)"></div>
            </div>
        </div>

        <table style="width: 100%; border-collapse: collapse; margin-bottom: 40px;">
            <thead>
                <tr style="border-bottom: 2px solid #e2e8f0;">
                    <th style="text-align: left; padding: 12px 0; color: #64748b; font-size: 13px; text-transform: uppercase;">Description</th>
                    <th style="text-align: center; padding: 12px 0; color: #64748b; font-size: 13px; text-transform: uppercase;">Qté</th>
                    <th style="text-align: right; padding: 12px 0; color: #64748b; font-size: 13px; text-transform: uppercase;">P.U HT</th>
                    <th style="text-align: right; padding: 12px 0; color: #64748b; font-size: 13px; text-transform: uppercase;" x-show="!isAutoEntrepreneur">TVA</th>
                    <th style="text-align: right; padding: 12px 0; color: #64748b; font-size: 13px; text-transform: uppercase;">Total HT</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="(item, index) in items" :key="index">
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 16px 0; font-weight: 600;" x-text="item.desc || 'Description...'"></td>
                        <td style="text-align: center; padding: 16px 0;" x-text="item.qty"></td>
                        <td style="text-align: right; padding: 16px 0;" x-text="formatAmount(item.price)"></td>
                        <td style="text-align: right; padding: 16px 0; color: #64748b; font-size: 14px;" x-show="!isAutoEntrepreneur" x-text="item.tva + '%'"></td>
                        <td style="text-align: right; padding: 16px 0; font-weight: 700;" x-text="formatAmount(item.qty * item.price)"></td>
                    </tr>
                </template>
            </tbody>
        </table>

        <div style="display: flex; justify-content: flex-end;">
            <div style="width: 300px; background: #f8fafc; padding: 24px; border-radius: 8px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 15px;">
                    <span style="color: #64748b;">Total HT</span>
                    <strong x-text="formatAmount(totals.ht)"></strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 16px; font-size: 15px;" x-show="!isAutoEntrepreneur">
                    <span style="color: #64748b;">Total TVA</span>
                    <strong x-text="formatAmount(totals.tva)"></strong>
                </div>
                <div style="display: flex; justify-content: space-between; padding-top: 16px; border-top: 2px solid #e2e8f0; font-size: 20px;">
                    <span style="font-weight: 800;">Total TTC</span>
                    <strong style="color: #2563eb; font-weight: 900;" x-text="formatAmount(totals.ttc)"></strong>
                </div>
            </div>
        </div>

        <div style="margin-top: 60px; font-size: 12px; color: #64748b; border-top: 1px solid #e2e8f0; padding-top: 24px;">
            <div x-show="isAutoEntrepreneur" style="margin-bottom: 8px; font-weight: 700; color: #0f172a;">TVA non applicable, article 293 B du Code Général des Impôts.</div>
            <div x-show="invoice.iban" style="margin-bottom: 8px;">
                <strong>IBAN de règlement :</strong> <span x-text="invoice.iban"></span>
            </div>
            <div style="white-space: pre-line;" x-text="invoice.notes || 'Pénalités de retard : 3 fois le taux d\'intérêt légal. Indemnité forfaitaire pour frais de recouvrement : 40 €.'"></div>
        </div>
    </div>
</div>

<style>
@media print {
    /* Cache les éléments de navigation et de layout global du site */
    header, footer, nav, aside, .blog-footer, .site-header {
        display: none !important;
    }

    /* Enlève les marges et contraintes de largeur pour la page d'impression */
    body, html, main, .home-container, .tool-wrapper, .tool-calculator-layout, .generateur-facture {
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
        width: 100% !important;
        max-width: none !important;
        border: none !important;
        box-shadow: none !important;
    }

    /* Cache tout ce qui a la classe no-print (le formulaire de facture, les boutons retour, etc) */
    .no-print {
        display: none !important;
    }

    /* La zone de facture prend 100% de la largeur disponible */
    .print-area {
        margin: 0 !important;
        padding: 1cm !important;
        box-sizing: border-box !important;
        border: none !important;
        box-shadow: none !important;
        width: 100% !important;
    }
    
    @page {
        size: auto;
        margin: 0mm; /* Désactive l'entête et pied de page du navigateur (URL, Date) */
    }
}
</style>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('invoiceGenerator', () => ({
        seller: {
            name: '',
            siret: '',
            address: ''
        },
        client: {
            name: '',
            address: ''
        },
        invoice: {
            number: '',
            date: new Date().toISOString().split('T')[0],
            dueDate: new Date(new Date().setDate(new Date().getDate() + 30)).toISOString().split('T')[0],
            iban: '',
            notes: ''
        },
        items: [
            { desc: '', qty: 1, price: 0, tva: 20 }
        ],
        isAutoEntrepreneur: false,

        addItem() {
            this.items.push({ desc: '', qty: 1, price: 0, tva: 20 });
        },
        
        removeItem(index) {
            if (this.items.length > 1) {
                this.items.splice(index, 1);
            }
        },

        get totals() {
            let ht = 0;
            let tva = 0;
            
            this.items.forEach(item => {
                let lineHt = (parseFloat(item.qty) || 0) * (parseFloat(item.price) || 0);
                ht += lineHt;
                if (!this.isAutoEntrepreneur) {
                    tva += lineHt * ((parseFloat(item.tva) || 0) / 100);
                }
            });

            return {
                ht: ht,
                tva: this.isAutoEntrepreneur ? 0 : tva,
                ttc: ht + (this.isAutoEntrepreneur ? 0 : tva)
            };
        },

        formatAmount(val) {
            return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(val || 0);
        },

        formatDate(dateString) {
            if (!dateString) return '';
            const d = new Date(dateString);
            return new Intl.DateTimeFormat('fr-FR').format(d);
        }
    }));
});
</script>
