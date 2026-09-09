import http from 'node:http';

const listenPort = Number(process.env.UI_C2B_PROXY_PORT || 8018);
const upstreamPorts = (process.env.UI_C2B_UPSTREAM_PORTS || '8019,8020')
  .split(',')
  .map((value) => Number(value.trim()))
  .filter(Number.isFinite);

if (upstreamPorts.length < 2) {
  throw new Error('UI_C2B_UPSTREAM_PORTS must contain at least two ports.');
}

const active = upstreamPorts.map(() => 0);

const server = http.createServer((request, response) => {
  const upstreamIndex = active.indexOf(Math.min(...active));
  active[upstreamIndex] += 1;
  let finished = false;
  const finish = () => {
    if (finished) return;
    finished = true;
    active[upstreamIndex] -= 1;
  };

  const upstream = http.request({
    hostname: '127.0.0.1',
    port: upstreamPorts[upstreamIndex],
    path: request.url,
    method: request.method,
    headers: request.headers,
  }, (upstreamResponse) => {
    response.writeHead(upstreamResponse.statusCode ?? 502, upstreamResponse.headers);
    upstreamResponse.pipe(response);
    upstreamResponse.once('end', finish);
    upstreamResponse.once('close', finish);
  });

  upstream.once('error', (error) => {
    finish();
    if (!response.headersSent) {
      response.writeHead(502, { 'content-type': 'text/plain; charset=utf-8' });
    }
    response.end(`UI-C2B harness upstream failure: ${error.message}`);
  });
  request.once('aborted', () => {
    upstream.destroy();
    finish();
  });
  response.once('close', () => {
    if (!finished) upstream.destroy();
    finish();
  });
  request.pipe(upstream);
});

server.listen(listenPort, '127.0.0.1');

const close = () => server.close(() => process.exit(0));
process.on('SIGINT', close);
process.on('SIGTERM', close);
