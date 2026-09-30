import fs from 'node:fs/promises';

const path = 'storage/app/theme-baselines/foodmart/phase2d/cascade-audit.json';
const data = JSON.parse(await fs.readFile(path, 'utf8'));
const summary = {};

for (const [width, viewport] of Object.entries(data)) {
  const transitions = new Map();
  const declarations = new Map();
  const examples = new Map();
  let directStyleChanges = 0;
  let rectOnlyChanges = 0;
  for (const element of viewport.changed) {
    const changedProperties = Object.keys(element.propertyChanges);
    if (!changedProperties.length) rectOnlyChanges++;
    else directStyleChanges++;
    for (const property of changedProperties) {
      const change = element.propertyChanges[property];
      const key = `${property}: ${change.withPlatformCss} -> ${change.isolated}`;
      transitions.set(key, (transitions.get(key) ?? 0) + 1);
      if (!examples.has(key)) examples.set(key, element.path);
      for (const rule of element.matchedPlatformRules) {
        if (!rule.declarations[property]) continue;
        const declaration = `${rule.selector} { ${property}: ${rule.declarations[property].value}${rule.declarations[property].priority ? ' !important' : ''} }`;
        declarations.set(declaration, (declarations.get(declaration) ?? 0) + 1);
      }
    }
  }
  summary[width] = {
    elementCount: viewport.elementCount,
    changedCount: viewport.changedCount,
    directStyleChanges,
    rectOnlyChanges,
    transitions: [...transitions].sort((a, b) => b[1] - a[1]).map(([change, count]) => ({ count, change, example: examples.get(change) })),
    matchingPlatformDeclarations: [...declarations].sort((a, b) => b[1] - a[1]).map(([declaration, count]) => ({ count, declaration })),
  };
}

await fs.writeFile('storage/app/theme-baselines/foodmart/phase2d/cascade-summary.json', JSON.stringify(summary, null, 2));
for (const [width, item] of Object.entries(summary)) {
  console.log(`\n${width}: direct=${item.directStyleChanges}, rectOnly=${item.rectOnlyChanges}`);
  console.log(item.transitions.slice(0, 30));
  console.log(item.matchingPlatformDeclarations.slice(0, 30));
}
