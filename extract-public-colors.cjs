const fs = require('fs');
const path = require('path');

const cssPath = path.join(__dirname, 'resources', 'css', 'app.css');
const cssLines = fs.readFileSync(cssPath, 'utf8').split('\n');

const publicSection = cssLines.slice(357, 1663).join('\n');

const regex = /#([A-Fa-f0-9]{3,8})\b/g;
const colors = {};
let match;
while ((match = regex.exec(publicSection)) !== null) {
    const hex = match[0].toLowerCase();
    colors[hex] = (colors[hex] || 0) + 1;
}

const sortedColors = Object.entries(colors).sort((a, b) => b[1] - a[1]);
for (const [hex, count] of sortedColors) {
    console.log(`${hex}: ${count}`);
}
