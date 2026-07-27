const fs = require('fs');
const path = require('path');
const { ROOT, enhance, loadContext } = require('./engine');

const examplesDir = path.join(ROOT, 'examples');
const outputDir = path.join(ROOT, 'recommendations');
const context = loadContext();

for (const name of fs.readdirSync(examplesDir).filter((file) => file.endsWith('.json')).sort()) {
  const input = JSON.parse(fs.readFileSync(path.join(examplesDir, name), 'utf8'));
  const output = enhance(input, context);
  const outputName = name.replace(/\.json$/, '.recommendations.json');
  fs.writeFileSync(path.join(outputDir, outputName), `${JSON.stringify(output, null, 2)}\n`, 'utf8');
  process.stdout.write(`Generated ${outputName}: ${output.enhancement_score.before} -> ${output.enhancement_score.after}\n`);
}
