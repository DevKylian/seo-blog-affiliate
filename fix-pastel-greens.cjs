const fs = require('fs');
const path = require('path');

const cssPath = path.join(__dirname, 'resources', 'css', 'app.css');
let cssLines = fs.readFileSync(cssPath, 'utf8').split('\n');

const replacements = {
  // Dark text / Dark Backgrounds
  '#173d32': '#1e293b',
  '#1d2823': '#1e293b',
  '#334039': '#1e293b',
  '#21302a': '#1e293b',
  '#394740': '#1e293b',
  '#1f352e': '#1e293b',
  '#1e3a68': '#1e293b',
  '#6a3a1f': '#1e293b',
  '#4a3473': '#1e293b',
  '#215433': '#1e293b',
  '#26302b': '#1e293b',
  '#26335a08': '#0f172a08',
  '#26335a0c': '#0f172a0c',
  '#12221f24': '#0f172a24',
  '#554b40': '#475569',
  '#9a6428': '#f59e0b', // Amber

  // Muted text / grays
  '#53605a': '#475569',
  '#66716b': '#64748b',
  '#7a847e': '#64748b',
  '#606b65': '#64748b',
  '#8f9b95': '#94a3b8',

  // Borders / Lines / Midtones
  '#a8cdbd': '#cbd5e1',
  '#9fbfb1': '#cbd5e1',
  '#cdd7d0': '#cbd5e1',
  '#dbe3dd': '#e2e8f0',
  '#dfeae3': '#e2e8f0',
  '#d7e2da': '#e2e8f0',
  '#dbe3dc': '#e2e8f0',
  '#c9d4ce': '#cbd5e1',
  '#dce6df': '#e2e8f0',
  '#dfe5dc': '#e2e8f0',
  
  // Light Backgrounds
  '#e8efe9': '#f1f5f9',
  '#f8faf7': '#f8fafc',
  '#f7f7f2': '#f8fafc',
  '#edf4ef': '#f8fafc',
  '#f3f7f2': '#f8fafc',
  '#eef3ef': '#f8fafc',
  '#f4eee5': '#f8fafc',
  '#edf3ee': '#f8fafc',
  '#f7faf6': '#f8fafc',
  '#eef3ed': '#f8fafc',
  '#eaeeeb': '#f1f5f9',
  '#f4f8f5': '#f8fafc',
  '#e2ece5': '#f1f5f9',
  '#eef2ef': '#f8fafc',
  '#eef3fc': '#f8fafc',
  '#fdf2ea': '#f8fafc',
  '#f3f0fb': '#f8fafc',
  '#edf7ef': '#f8fafc',
  '#e8f8f0': '#eff6ff', // specific article category background seen earlier
  '#e8f8f1': '#eff6ff',
  '#f0fdf4': '#eff6ff',
  '#bbf7d0': '#bfdbfe',
  '#16a34a': '#2563eb',
  '#059669': '#2563eb'
};

// Process only the public block lines (357 to 1663)
// Note: Arrays are 0-indexed, so lines 358 to 1663 corresponds to index 357 to 1662
for (let i = 357; i <= 1662; i++) {
  if (cssLines[i] !== undefined) {
    let line = cssLines[i];
    for (const [greenHex, blueHex] of Object.entries(replacements)) {
      const regex = new RegExp(greenHex, 'gi');
      line = line.replace(regex, blueHex);
    }
    cssLines[i] = line;
  }
}

// Write back to app.css
fs.writeFileSync(cssPath, cssLines.join('\n'));
console.log('Pastel Greens/Purples Replaced!');
