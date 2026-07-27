const fs = require('fs');
const path = require('path');
const { ROOT, loadSimulationContext, loadScenarios, simulateScenario } = require('./simulation-engine');

const context = loadSimulationContext();
const results = loadScenarios().map((scenario) => simulateScenario(scenario, context));
fs.writeFileSync(path.join(ROOT, 'data', 'simulation-results.json'), `${JSON.stringify({ generated_by: 'read-only-contract-simulation', simulations: results }, null, 2)}\n`, 'utf8');
for (const result of results) fs.writeFileSync(path.join(ROOT, 'examples', `${result.scenario.id}-before-after.json`), `${JSON.stringify(result, null, 2)}\n`, 'utf8');
process.stdout.write(`Built ${results.length} simulations from ${context.enhancement.library.assets.length} canonical assets.\n`);
