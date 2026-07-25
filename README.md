# BusinessKit — SEO Affiliate Content OS

CMS éditorial affilié natif construit avec Laravel 10, Livewire 3 et Blade. Aucun WordPress n’est utilisé : l’administration, le générateur et le blog public vivent dans la même application.

## Accès local

- Site public : https://businesskit.test/blog
- Outils : https://businesskit.test/outils
- Administration : https://businesskit.test/admin
- Flux automatique : https://businesskit.test/admin/automation
- Content Factory : https://businesskit.test/admin/scheduler
- E-mail : `admin@example.com`
- Mot de passe : `password`

Changez ce mot de passe avant toute mise en ligne.

## Flux automatique recommandé

L'écran **Flux automatique AI** réunit tout le parcours dans une seule page :

1. Créez un nouvel affilié ou sélectionnez un projet existant.
2. Saisissez le nom de l'outil, le site officiel, la page tarifs, le lien affilié, le pays et la devise.
3. Ajoutez éventuellement d'autres pages officielles et la clé Gemini.
4. Déposez le fichier Semrush ou Google Keyword Planner puis cliquez sur **Analyser et préparer**.
5. Choisissez le nombre de contenus à produire, de 1 à 30, et ajoutez vos consignes éditoriales.
6. Cliquez sur **Planifier**. Gemini propose davantage d’idées que nécessaire, puis l’application déduplique, note et verrouille exactement le nombre d’angles demandé sans rédiger d’article.
7. Vérifiez le plan puis cliquez sur **Générer les articles**. La rédaction suit les briefs verrouillés et remplace automatiquement un brouillon refusé par une idée de réserve.

Le planificateur et le rédacteur sont deux moteurs indépendants. Une campagne effectue ensuite un appel Gemini distinct par contenu afin d'éviter une requête HTTP trop longue. Les workers sont autonomes : le navigateur peut être fermé sans interrompre la génération. Les erreurs techniques restent visibles et n'empêchent pas les contenus suivants d'être traités.

Chaque article généré est enregistré avec le statut **à relire**. La publication reste volontairement soumise à validation humaine.

Un modèle CSV prêt à remplir est disponible dans `public/examples/semrush-template.csv` et directement depuis l'écran d'automatisation.

## Content Factory

L’écran **Content Factory** transforme la bibliothèque de mots-clés en file de production continue :

1. sélectionnez un projet et, si nécessaire, collez une nouvelle liste Semrush ;
2. choisissez une cadence de 1 à 7 articles par semaine ;
3. conservez le mode **À relire** ou activez la publication automatique dans le CMS Laravel natif ;
4. activez l’usine : Gemini transforme d’abord les requêtes en idées qualifiées avec titre, intention, angle, audience, score et contrôle de similarité ;
5. seules les idées retenues sont ensuite réparties du lundi au dimanche, avec un seul article maximum par jour ;
6. glissez-déposez un contenu sur une date, générez-le immédiatement ou excluez-le de la production.

Le calendrier maintient automatiquement un stock roulant couvrant 30 jours, affiche le KD et l’état en temps réel, et n’accepte jamais un mot-clé brut : chaque tâche possède obligatoirement un brief éditorial validé. Les idées retenues sont programmées sans validation humaine ; la seule intervention éditoriale restante se fait sur le brouillon « À relire ». Le bouton **Générer la semaine** remplit les prochains créneaux. Le bouton **Générer maintenant les contenus prévus** lance toute la file séquentiellement en arrière-plan, tandis que chaque ligne propose **Générer maintenant** ou **Ne pas générer**. Les nouveaux mots-clés importés plus tard déclenchent un nouveau plan sans déplacer les dates choisies manuellement. Un dernier verrou retire les titres, mots-clés principaux ou couples sujet/intention trop proches avant leur entrée dans le calendrier, puis complète automatiquement les places libérées.

Une indisponibilité temporaire de Gemini est reprise par le worker ; si un run se termine néanmoins sur une erreur 429/503 ou un timeout, le scheduler le remet en file cinq minutes plus tard. Une erreur non transitoire est isolée sur sa tâche et reste relançable depuis l’interface. Quand le blog possède moins de dix articles publiés, le prompt de maillage peut compléter ses cibles avec une catégorie pertinente, l’accueil du blog ou la fiche de l’outil : l’absence de trois articles compatibles ne bloque donc jamais la production.

## Workflow manuel avancé

1. Créez un outil dans **Projets & outils**.
2. Collectez ses pages officielles dans **Collecte & preuves**.
3. Importez un CSV Semrush et contrôlez le score d’opportunité.
4. Ajoutez une clé dans **Réglages Gemini**.
5. Générez un brief et un brouillon sourcé depuis le **Studio de contenu**.
6. Relisez et enrichissez l’article dans **Articles & CMS**.
7. Programmez ou publiez directement sur le blog Laravel.

## Gemini

La clé saisie dans le dashboard est chiffrée en base avec `APP_KEY` et n’est jamais réaffichée en clair. Une variable d’environnement `GEMINI_API_KEY` peut aussi être utilisée. Le modèle par défaut est `gemini-2.5-flash-lite`, configuré sans budget de réflexion pour limiter le coût et la latence ; `gemini-2.5-flash` reste disponible uniquement comme alternative manuelle.

Le générateur :

- utilise uniquement les preuves vérifiées ;
- impose les citations `[S1]`, `[S2]` ;
- écrit « non communiqué » pour une information factuelle absente, mais jamais comme faux tarif ;
- produit un brief SEO et un article en blocs JSON ;
- place le brouillon au statut « à relire » ;
- ne promet aucun classement dans les moteurs de recherche.

## Structures éditoriales SEO

Chaque génération utilise une structure obligatoire propre au type de contenu : avis détaillé, page tarifs, comparatif direct, sélection des meilleurs outils, alternatives ou guide informationnel. Les objectifs se situent entre 2 200 et 5 000 mots selon le format ; ces plages servent la profondeur éditoriale et ne constituent pas une garantie de classement.

Avant l’enregistrement, le brouillon est contrôlé sur la longueur minimale, le nombre de sections H2/H3, les sujets obligatoires, la FAQ, la conclusion, les citations et la transparence affiliée. L’audit vérifie aussi la réponse dans les 150 premiers mots, une limite réaliste, un conseil d’implémentation, les listes à puces, l’absence de tarif vide et un tableau réellement discriminant. Un premier résultat incomplet déclenche une génération de secours en deux longues parties. Le brouillon est ensuite enregistré pour relecture avec ses contrôles qualité détaillés ; seuls les vrais incidents techniques (API, réseau ou réponse inexploitable) mettent un élément en échec et peuvent être relancés séparément.

Avant la création d’une campagne, un filtre lexical croise le mot-clé avec le nom, le positionnement, les fonctionnalités et les preuves du produit. Les requêtes manifestement hors sujet sont écartées sans appel Gemini. Les formats comparatif, meilleurs outils et alternatives exigent au moins deux solutions distinctes et sourcées ; un contenu mono-produit reçoit automatiquement un titre transparent qui nomme l’outil.

La V3 distingue strictement les contenus mono-produit (H1 de guide technique ou de cas d’usage, sans « Comparatif » ni « Meilleur ») et multi-produits (2 à 3 solutions concurrentes confrontées). Chaque outil conserve une limite opérationnelle, notamment dans la colonne « Limites » des matrices. Si aucun prix exact n’est vérifié, le texte explique la facturation au siège, au volume ou à l’usage et le coût total de possession avant de renvoyer vers la grille officielle. Les scénarios peuvent utiliser une métrique plausible uniquement sous l’étiquette « Hypothèse de simulation » ou « Scénario illustratif » ; elle ne doit jamais être présentée comme une performance réelle.

La V4 impose, dans les formats multi-produits, au moins 40 % de questions FAQ généralistes ou centrées sur les alternatives plutôt que sur l’affilié principal. Pour une requête e-commerce/B2C, le périmètre doit réunir au moins une solution CRM/marketing automation sectorielle (Klaviyo, Brevo ou ActiveCampaign) et un CRM commercial traditionnel. Ces produits ne sont retenus que si leurs preuves sont disponibles.

Une génération longue dispose d’un budget PHP de 420 secondes. Chaque appel Gemini reste borné à 120 secondes afin qu’une panne réseau soit transformée en erreur de campagne relançable plutôt qu’en erreur fatale PHP à 30 secondes.

## Gouvernance des doublons éditoriaux

Chaque idée reçoit avant la rédaction une empreinte enrichie `entité|sujet|intention|angle|audience|résultat`. Le détecteur compare les brouillons, contenus à relire, programmés et publiés, les idées déjà validées et toutes les idées du même lot. Les alias comme « gestion clients », « relation client » et « gestion clientèle » sont normalisés vers un sujet canonique. Les angles génériques sont refusés et la similarité du mini-plan H2 intervient dans la décision.

Le plan initial analyse jusqu’à trois fois plus de candidats que le nombre demandé et peut relancer cinq cycles en excluant les empreintes déjà testées. Les meilleurs candidats sont classés selon l’opportunité SEO, l’intention, la valeur commerciale, l’originalité, la couverture des sources et le potentiel de maillage. Le bouton de rédaction reste bloqué tant que le plan n’affiche pas exactement `X/X`. Le titre, le plan H2/H3 et la FAQ sont contrôlés une seconde fois après génération ; un refus active une réserve au lieu de réduire le lot.

La bibliothèque expose les doublons potentiels et permet de fusionner les meilleures sections, modifier l’angle, archiver ou ignorer exceptionnellement. Un contenu fusionné pointe vers son article canonique et un ancien slug publié reçoit une redirection 301. Les suffixes numériques tels que `-2` sont interdits pour les nouveaux slugs SEO.

## Blocs natifs

Les articles conservent le Markdown éditorial et un tableau `content_blocks`. Les blocs disponibles dans cette version sont :

- `markdown` ;
- `pricing_table` connecté aux derniers plans actifs ;
- `affiliate_disclosure` ;
- `last_verified`.

Le tableau tarifaire n’est pas copié dans le contenu. Une nouvelle collecte peut donc actualiser toutes les pages qui utilisent le bloc.

## Routes publiques

```text
/blog
/blog/{article-ou-categorie}
/outils
/outils/{outil}
/outils/{outil}/tarifs
/comparatifs/{slug}
/alternatives/{slug}
/meilleurs-outils/{slug}
/sitemap.xml
```

Toutes les pages sont rendues côté serveur avec Blade. Les articles publient leurs balises title, description, canonical et `Article` JSON-LD. Les fiches outils publient `SoftwareApplication` JSON-LD.

## Import de mots-clés

Les exports CSV Semrush et les exports CSV/TSV Google Keyword Planner sont reconnus automatiquement. Pour Keyword Planner, les lignes de métadonnées placées avant l’en-tête sont ignorées et les colonnes `Avg. monthly searches`, `Competition (indexed value)`, les enchères et la position organique sont converties vers le format interne.

Colonnes reconnues :

```text
keyword, search_volume, keyword_difficulty, intent, cpc, trend,
country, serp_features, current_position, ranking_url
```

Les séparateurs virgule et point-virgule sont acceptés.

## Tâches programmées

En local, l’activation depuis le dashboard démarre déjà un worker autonome compatible Windows, macOS et Linux. Pour conserver aussi le scheduler Laravel au premier plan pendant le développement :

```bash
php artisan schedule:work
```

En production Linux, configurez le cron Laravel standard :

```cron
* * * * * cd /chemin/vers/BusinessKit && php artisan schedule:run >> /dev/null 2>&1
```

Sous Windows, utilisez le Planificateur de tâches avec `php artisan schedule:run` toutes les minutes. Le verrou partagé empêche le cron, le worker local et un rafraîchissement manuel de lancer deux productions en parallèle.

## Actions groupées

Les tableaux Articles, Mots-clés, Sources, Logs d’accès et Campagnes disposent de cases de sélection, d’une sélection globale sur le filtre courant et d’une suppression groupée avec confirmation. La suppression d’une campagne conserve les articles déjà générés ; la suppression d’une source retire également ses preuves et ses tarifs associés.

## Développement

```bash
composer install
pnpm install
php artisan migrate --seed
pnpm run dev
php artisan test
```

Le crawler statique utilise Laravel HTTP, Symfony DomCrawler et CssSelector. Il respecte `robots.txt`, refuse les adresses privées, limite les pages à 5 Mo et conserve la source, l’extrait, la date, la méthode et le score de confiance. Lorsqu’une page dépend de JavaScript ou bloque l’appel HTTP, le crawler utilise automatiquement Playwright avec Chrome, Chromium, Edge ou Brave.

## Compatibilité Windows, macOS et Linux

L’application ne dépend d’aucun chemin propre à Herd ou à macOS. Elle détecte automatiquement dans `PATH` :

- le PHP CLI utilisé par les workers autonomes ;
- Node.js pour le rendu Playwright ;
- Chrome, Chromium, Microsoft Edge ou Brave pour les pages dynamiques.

Pré-requis communs : PHP 8.1 ou supérieur avec les extensions Laravel usuelles, Composer, Node.js 18 ou supérieur, pnpm et un navigateur Chromium. Exécutez `pnpm install --prod` également sur le serveur Linux si le scraping dynamique est activé : `playwright-core` est une dépendance de production.

Si un exécutable n’est pas présent dans `PATH`, renseignez son chemin absolu dans `.env` :

```dotenv
PHP_CLI_BINARY=
NODE_BINARY=
BROWSER_BINARY=
SCRAPING_BROWSER_ENABLED=true
```

Exemples possibles :

```text
Windows : C:\\Program Files\\PHP\\php.exe
Windows : C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe
macOS   : /Applications/Google Chrome.app/Contents/MacOS/Google Chrome
Linux   : /usr/bin/php
Linux   : /usr/bin/chromium
```

Les campagnes, planifications et collectes sont lancées en arrière-plan avec la mécanique native de la plateforme : `start /B` sous Windows et processus détaché/`nohup` sous macOS et Linux. En production Linux à fort trafic, un gestionnaire de processus tel que Supervisor ou systemd reste recommandé pour surveiller les workers longue durée.

Après chaque déploiement ou changement de chemin :

```bash
php artisan optimize:clear
php artisan test
```

## Sécurité et maintenance

Laravel 10 est utilisé parce qu’il a été explicitement demandé, mais cette version est hors support et Composer signale des avis de sécurité sur le framework. Migrez vers une version Laravel maintenue avant une mise en production publique.
