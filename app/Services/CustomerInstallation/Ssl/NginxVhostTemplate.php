<?php

namespace App\Services\CustomerInstallation\Ssl;

final class NginxVhostTemplate
{
    /** @param array{cert: string, key: string}|null $sslPaths */
    public function render(string $domain, string $webRoot, string $phpFpmSocket, ?array $sslPaths): string
    {
        // The snippet's content lives in ArvanRealIpSnippet; NginxVhostWriter
        // installs it on the server before nginx -t.
        $arvanRealIpInclude = (new ArvanRealIpSnippet())->includeDirective();
        $originPeerParam = 'fastcgi_param HTTP_X_ORIGIN_PEER_IP $realip_remote_addr;';

        $httpBlock = <<<NGINX
# Customer tenant: {$domain}
server {
    listen 80;
    server_name {$domain};
    root {$webRoot}/public;
    index index.php;

    {$arvanRealIpInclude}

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:{$phpFpmSocket};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param HTTPS \$forwarded_https;
        include fastcgi_params;
        # Must follow fastcgi_params so a client-supplied value is overwritten.
        {$originPeerParam}
        fastcgi_read_timeout 300;
    }

    location ~ /\.ht { deny all; }
    client_max_body_size 50M;
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml;
}
NGINX;

        if ($sslPaths === null) {
            return $httpBlock;
        }

        $sslCert = $sslPaths['cert'];
        $sslKey = $sslPaths['key'];

        return $httpBlock . <<<NGINX


# HTTPS
server {
    listen 443 ssl;
    server_name {$domain};
    root {$webRoot}/public;
    index index.php;

    ssl_certificate     {$sslCert};
    ssl_certificate_key {$sslKey};
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         HIGH:!aNULL:!MD5;

    {$arvanRealIpInclude}

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:{$phpFpmSocket};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param HTTPS \$https;
        include fastcgi_params;
        # Must follow fastcgi_params so a client-supplied value is overwritten.
        {$originPeerParam}
        fastcgi_read_timeout 300;
    }

    location ~ /\.ht { deny all; }
    client_max_body_size 50M;
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml;
}
NGINX;
    }
}
