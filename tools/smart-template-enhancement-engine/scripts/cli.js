#!/usr/bin/env node
const fs = require('fs');
const path = require('path');
const { enhance } = require('./engine');

const inputPath = process.argv[2];
if (!inputPath) {
  process.stderr.write('Usage: node scripts/cli.js <scenario.json>\n');
  process.exitCode = 2;
} else {
  try {
    const input = JSON.parse(fs.readFileSync(path.resolve(inputPath), 'utf8'));
    process.stdout.write(`${JSON.stringify(enhance(input), null, 2)}\n`);
  } catch (error) {
    process.stderr.write(`${error.message}\n`);
    process.exitCode = 1;
  }
}
