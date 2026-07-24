const fs = require('fs');
const path = require('path');

const cssPath = path.join(__dirname, 'resources', 'css', 'app.css');
let css = fs.readFileSync(cssPath, 'utf8');

const replacements = {
    // Primary / Green
    '#1f6b55': '#2563eb', // Blue 600
    '#174f40': '#1d4ed8', // Blue 700

    // Dark Backgrounds / Text
    '#13221f': '#0f172a', // Slate 900
    '#1a3028': '#1e293b', // Slate 800
    '#1f2a25': '#1e293b', // Slate 800
    '#182923': '#1e293b', // Slate 800
    '#10201b': '#0f172a', // Slate 900
    '#18201d': '#0f172a', // Slate 900

    // Gray / Muted Text
    '#3f4a45': '#475569', // Slate 600
    '#69736e': '#475569',
    '#59645e': '#475569',
    '#5f6a64': '#475569',
    '#65706b': '#475569',
    
    // Light Text
    '#d9e0db': '#94a3b8', // Slate 400
    '#7c867f': '#94a3b8',
    '#aeb8b2': '#cbd5e1',

    // Borders / Lines
    '#cfd8d0': '#e2e8f0', // Slate 200
    '#d8ded7': '#e2e8f0',

    // Backgrounds / Surfaces
    '#fffefb': '#ffffff', // White
    '#f3f5f1': '#f8fafc', // Slate 50
    '#f9fbf7': '#f1f5f9', // Slate 100
    '#edf2ed': '#f1f5f9', // Slate 100
    '#eef2ed': '#f8fafc', // Slate 50
};

// Case insensitive replace for each
for (const [greenHex, blueHex] of Object.entries(replacements)) {
    const regex = new RegExp(greenHex, 'gi');
    css = css.replace(regex, blueHex);
}

fs.writeFileSync(cssPath, css);
console.log('CSS Fixed!');
