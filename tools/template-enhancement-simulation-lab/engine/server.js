const http = require('http');
const fs = require('fs');
const path = require('path');

const host = '127.0.0.1';
const port = Number(process.env.PORT || 4174);
const repoRoot = path.resolve(__dirname, '../../..');
const labPath = '/tools/template-enhancement-simulation-lab/';
const types = { '.html':'text/html; charset=utf-8','.js':'text/javascript; charset=utf-8','.css':'text/css; charset=utf-8','.json':'application/json; charset=utf-8','.svg':'image/svg+xml; charset=utf-8','.md':'text/markdown; charset=utf-8' };

const server = http.createServer((request,response)=>{
  try{
    const url=new URL(request.url,`http://${host}:${port}`);
    if(url.pathname==='/health')return send(response,200,'application/json; charset=utf-8',JSON.stringify({ok:true,service:'template-enhancement-simulation-lab',mode:'read-only'}));
    const relative=decodeURIComponent(url.pathname===labPath?`${labPath}index.html`:url.pathname).replace(/^\/+/, '');
    const requested=path.resolve(repoRoot,relative);
    if(!requested.startsWith(repoRoot+path.sep)||!fs.existsSync(requested)||!fs.statSync(requested).isFile())return send(response,404,'text/plain; charset=utf-8','Not Found');
    const ext=path.extname(requested).toLowerCase();
    response.writeHead(200,{'Content-Type':types[ext]||'application/octet-stream','X-Content-Type-Options':'nosniff','Referrer-Policy':'no-referrer','Content-Security-Policy':"default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'",'Cache-Control':['.html','.js','.css','.json'].includes(ext)?'no-store':'public, max-age=3600'});
    if(request.method==='HEAD')return response.end();
    fs.createReadStream(requested).pipe(response);
  }catch(error){send(response,500,'text/plain; charset=utf-8',`Server Error: ${error.message}`)}
});
server.listen(port,host,()=>process.stdout.write(`Template Enhancement Simulation Lab: http://${host}:${port}${labPath}\nRead-only static server. Press Ctrl+C to stop.\n`));
function send(response,status,type,body){response.writeHead(status,{'Content-Type':type,'Content-Length':Buffer.byteLength(body),'X-Content-Type-Options':'nosniff'});response.end(body)}
