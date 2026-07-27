import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const repositoryRoot = path.resolve(here, '../../..');
const playgroundPath = '/tools/motion-design-playground/';
const host = process.env.PLAYGROUND_HOST || '127.0.0.1';
const port = Number(process.env.PLAYGROUND_PORT || 4173);

const mimeTypes = {
  '.html': 'text/html; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.svg': 'image/svg+xml; charset=utf-8',
  '.md': 'text/markdown; charset=utf-8',
  '.txt': 'text/plain; charset=utf-8',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.webp': 'image/webp',
};

const server = http.createServer((request, response) => {
  try {
    const url = new URL(request.url, `http://${request.headers.host || `${host}:${port}`}`);
    if (url.pathname === '/health') return send(response, 200, 'application/json; charset=utf-8', JSON.stringify({ ok: true, service: 'motion-design-playground' }));
    if (url.pathname === '/') {
      response.writeHead(302, { Location: playgroundPath });
      return response.end();
    }
    if (!['GET', 'HEAD'].includes(request.method)) return send(response, 405, 'text/plain; charset=utf-8', 'Method Not Allowed');

    let pathname;
    try { pathname = decodeURIComponent(url.pathname); } catch (_) { return send(response, 400, 'text/plain; charset=utf-8', 'Bad Request'); }
    if (pathname.endsWith('/')) pathname += 'index.html';
    const requested = path.resolve(repositoryRoot, `.${pathname}`);
    const relative = path.relative(repositoryRoot, requested);
    if (relative === '..' || relative.startsWith(`..${path.sep}`) || path.isAbsolute(relative)) return send(response, 403, 'text/plain; charset=utf-8', 'Forbidden');
    if (!fs.existsSync(requested) || !fs.statSync(requested).isFile()) return send(response, 404, 'text/plain; charset=utf-8', 'Not Found');

    const extension = path.extname(requested).toLowerCase();
    const headers = {
      'Content-Type': mimeTypes[extension] || 'application/octet-stream',
      'Content-Length': fs.statSync(requested).size,
      'X-Content-Type-Options': 'nosniff',
      'Referrer-Policy': 'no-referrer',
      'Cross-Origin-Resource-Policy': 'same-origin',
      'Content-Security-Policy': "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' blob: data:; connect-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'",
      'Cache-Control': ['.html', '.json', '.js', '.css'].includes(extension) ? 'no-store' : 'public, max-age=3600',
    };
    response.writeHead(200, headers);
    if (request.method === 'HEAD') return response.end();
    return fs.createReadStream(requested).pipe(response);
  } catch (error) {
    return send(response, 500, 'text/plain; charset=utf-8', `Server Error: ${error.message}`);
  }
});

server.listen(port, host, () => {
  process.stdout.write(`Motion Design Playground: http://${host}:${port}${playgroundPath}\n`);
  process.stdout.write('Read-only static server. Press Ctrl+C to stop.\n');
});

function send(response, status, contentType, body) {
  response.writeHead(status, { 'Content-Type': contentType, 'Content-Length': Buffer.byteLength(body), 'X-Content-Type-Options': 'nosniff' });
  response.end(body);
}
