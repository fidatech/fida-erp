<?php
namespace App\Services\ERP;

use Illuminate\Support\Facades\Http;
use Throwable;

class TenantDomainVerifier
{
    /**
     * Tenant must resolve to the same IPv4 as the configured platform host,
     * serve a valid TLS certificate and route to the Laravel health endpoint.
     */
    public function isReady(string $hostname): bool
    {
        $platform = strtolower((string) parse_url((string)config('app.url'), PHP_URL_HOST));
        $hostname = strtolower($hostname);
        if (!$platform || !preg_match('/^[a-z0-9.-]{4,253}$/D',$hostname)
            || $hostname === $platform || !str_ends_with($hostname,'.'.$platform)) {
            return false;
        }
        $platformIp = gethostbyname($platform);
        if (!filter_var($platformIp,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)
            || filter_var($platformIp,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }
        $records = dns_get_record($hostname,DNS_A) ?: [];
        $addresses = array_values(array_filter(array_map(fn($record)=>$record['ip']??null,$records)));
        if ($addresses === [] || array_diff($addresses,[$platformIp]) !== []) return false;
        try {
            return Http::timeout(8)->withOptions(['allow_redirects'=>false])
                ->get('https://'.$hostname.'/up')->status() === 200;
        } catch (Throwable) {
            return false;
        }
    }
}
