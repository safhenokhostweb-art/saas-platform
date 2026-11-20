<?php

namespace App\Services\CustomerInstallation\Ssl;

/**
 * Renders a customer tenant's nginx vhost.
 *
 * $domain is the canonical host. $redirectHosts (the www name, a site's
 * previous domains) are answered and 301-redirected to it, except for
 * /.well-known/acme-challenge/, which is served from the webroot so certbot
 * can still validate those names.
 *
 * With $forceHttps (a real certificate on a non-CDN domain) port 80 only
 * redirects to https://$domain, each $httpsRedirects entry gets a 443 block
 * that redirects to it, and the application is served on 443 alone. Without
 * it (no certificate yet, or ArvanCloud-proxied, where the CDN may reach the
 * origin over HTTP and an origin redirect would loop) port 80 still serves
 * the application.
 */
final class NginxVhostTemplate
{
    /**
     * @param array{cert: string, key: string}|null $sslPaths
     * @param list<string> $redirectHosts
     * @param list<array{names: list<string>, cert: string, key: string}> $httpsRedirects
     */
    public function render(
        string $domain,
        string $webRoot,
        string $phpFpmSocket,
        ?array $sslPaths,
        array $redirectHosts = [],
        bool $forceHttps = false,
        array $httpsRedirects = [],
    ): string {
        // The snippet's content lives in ArvanRealIpSnippet; NginxVhostWriter
        // installs it on the server before nginx -t.
        $arvanRealIpInclude = (new ArvanRealIpSnippet())->includeDirective();
        $originPeerParam = 'fastcgi_param HTTP_X_ORIGIN_PEER_IP $realip_remote_addr;';
        $redirectHosts = array_values(array_diff(array_unique($redirectHosts), [$domain]));
        $acme = $this->acmeLocation($webRoot);

        if ($sslPaths !== null && $forceHttps) {
            $allHttpNames = implode(' ', [$domain, ...$redirectHosts]);
            $config = <<<NGINX
# Customer tenant: {$domain}
# HTTP: certificate renewal challenges, everything else to https://{$domain}
server {
    listen 80;
    server_name {$allHttpNames};

    {$arvanRealIpInclude}

{$acme}

    location / {
        return 301 https://{$domain}\$request_uri;
    }
}
NGINX;

            foreach ($httpsRedirects as $group) {
                $names = implode(' ', array_values(array_diff(array_unique($group['names']), [$domain])));
                if ($names === '') {
                    continue;
                }
                $config .= <<<NGINX


# HTTPS alternate names -> https://{$domain}
server {
    listen 443 ssl;
    server_name {$names};

    ssl_certificate     {$group['cert']};
    ssl_certificate_key {$group['key']};
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         HIGH:!aNULL:!MD5;

    return 301 https://{$domain}\$request_uri;
}
NGINX;
            }

            return $config . "\n\n" . $this->httpsBlock($domain, $webRoot, $phpFpmSocket, $sslPaths, $arvanRealIpInclude, $originPeerParam);
        }

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

        if ($redirectHosts !== []) {
            $names = implode(' ', $redirectHosts);
            $httpBlock .= <<<NGINX


# Alternate names -> http://{$domain} (no certificate yet)
server {
    listen 80;
    server_name {$names};

{$acme}

    location / {
        return 301 http://{$domain}\$request_uri;
    }
}
NGINX;
        }

        if ($sslPaths === null) {
            return $httpBlock;
        }

        return $httpBlock . "\n\n" . $this->httpsBlock($domain, $webRoot, $phpFpmSocket, $sslPaths, $arvanRealIpInclude, $originPeerParam);
    }

    /** @param array{cert: string, key: string} $sslPaths */
    private function httpsBlock(string $domain, string $webRoot, string $phpFpmSocket, array $sslPaths, string $arvanRealIpInclude, string $originPeerParam): string
    {
        return <<<NGINX
# HTTPS
server {
    listen 443 ssl;
    server_name {$domain};
    root {$webRoot}/public;
    index index.php;

    ssl_certificate     {$sslPaths['cert']};
    ssl_certificate_key {$sslPaths['key']};
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

    private function acmeLocation(string $webRoot): string
    {
        return <<<NGINX
    location ^~ /.well-known/acme-challenge/ {
        root {$webRoot}/public;
        default_type "text/plain";
        try_files \$uri =404;
    }
NGINX;
    }
}
