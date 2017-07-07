<?php

/**
 * Client IP
 *
 * Get client IP with safe and coincident way from server even behind Proxy 
 * or Load-Balancer.
 *
 * @author      Nick Tsai <myintaer@gmail.com>
 * @version     1.0.0
 * @example
 *  $ip = IP::get();                      // Get $_SERVER['REMOTE_ADDR']
 *
 * @example
 *  // Set specific proxys
 *  IP::config([
 *      'proxyIPs' => ['192.168.1.2']
 *      ]);
 *  $ip = IP::get();                      // Get Forward IP if via the proxy
 *
 * @example
 *  // Set a range of private network
 *  IP::config([
 *      'proxyIPs' => ['192.168.0.0/16']
 *      ]);
 *  $ip = IP::get();                      // Get Forward IP if via lan proxies
 *
 * @example
 *  // Set as Prxoy mode
 *  IP::config([
 *      'proxyIPs' => true
 *      ]);
 *  $ip = IP::get();                      // Get Forward IP always
 *
 * @example
 *  // Set as Prxoy mode by calling method
 *  IP::proxyMode();                      // Set proxyIPs as true
 *  IP::config([
 *      'headerKeys' => ['HTTP_X_FORWARDED_FOR']
 *      ]);
 *  $ip = IP::get();                      // Get x-Forward-for IP always
 */

class ClientIP
{
    /**
     * @var array $proxyIPs IP list of Proxy servers
     *
     * Specify Proxies when your server is in public network, but also receives 
     * from Specified Load-Balancer or Proxy. 
     * This only works while the value is not empty and proxy mode is off.
     */
    public static $proxyIPs = [];

    /**
     * @var array $headerKeys Header Key list for IP Forward
     */
    public static $headerKeys = [
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'HTTP_VIA'
        ];

    /**
     * @var $cachedIP cache of Client IP
     */
    private static $cachedIP;

    /**
     * Set configuration
     *
     * @param mixed $config Configuration Array
     * @return Object Self
     */
    public static function config($config)
    {
        self::$proxyIPs = (isset($config['proxyIPs']))
            ? $config['proxyIPs']
            : self::$proxyIPs;

        self::$headerKeys = (isset($config['headerKeys']))
            ? $config['headerKeys']
            : self::$headerKeys;

        // Clear cachedIP
        self::$cachedIP = NULL;

        return new self;
    }

    /**
     * Set as proxy mode
     *
     * @return Object Self
     */
    public static function proxyMode()
    {
        self::$proxyIPs = true;

        // Clear cachedIP
        self::$cachedIP = NULL;

        return new self;
    }

    /**
     * Alias of getRealIP()
     *
     * @see getRealIP()
     */
    public static function get()
    {
        // Check cache
        if (self::$cachedIP) {
            
            return self::$cachedIP;
        }

        self::$cachedIP = self::getRemoteIP();

        // Check IP is available
        if (!self::$cachedIP) {
            
            return false;
        }

        $proxyIPs = self::$proxyIPs;

        /* Proxy Mode */
        if ($proxyIPs === true) {
            
            return self::$cachedIP = self::getForwardIP();
        }

        /* String format */
        if (!empty($proxyIPs) && !is_array($proxyIPs)) {

            $proxyIPs = self::validateIP($proxyIPs) ? [$proxyIPs] : NULL;
        }

        if ($proxyIPs) {

            // Get the forward IP from active header
            foreach ((array)self::$headerKeys as $header) {

                $spoof = isset($_SERVER[$header]) 
                    ? $_SERVER[$header]
                    : NULL;

                if ($spoof !== NULL) {

                    // Some proxies typically list the whole chain of IP
                    // addresses through which the client has reached us.
                    // e.g. client_ip, proxy_ip1, proxy_ip2, etc.
                    sscanf($spoof, '%[^,]', $spoof);

                    if ( ! self::validateIP($spoof)) {

                        $spoof = NULL;
                    }
                    else {

                        break;
                    }
                }
            }

            if ($spoof) {

                for ($i = 0, $c = count($proxyIPs); $i < $c; $i++) {

                    // Check if we have an IP address or a subnet
                    if (strpos($proxyIPs[$i], '/') === FALSE) {

                        // An IP address (and not a subnet) is specified.
                        // We can compare right away.
                        if ($proxyIPs[$i] === self::$cachedIP) {

                            self::$cachedIP = $spoof;
                            break;
                        }

                        continue;
                    }

                    // We have a subnet ... now the heavy lifting begins
                    isset($separator) OR $separator = self::validateIP(self::$cachedIP, 'ipv6') ? ':' : '.';

                    // If the proxy entry doesn't match the IP protocol - skip it
                    if (strpos($proxyIPs[$i], $separator) === FALSE) {

                        continue;
                    }

                    // Convert the REMOTE_ADDR IP address to binary, if needed
                    if ( !isset($ip, $sprintf)) {

                        if ($separator === ':') {

                            // Make sure we're have the "full" IPv6 format
                            $ip = explode(':',
                                str_replace('::',
                                    str_repeat(':', 9 - substr_count(self::$cachedIP, ':')),
                                    self::$cachedIP
                                )
                            );

                            for ($j = 0; $j < 8; $j++) {

                                $ip[$j] = intval($ip[$j], 16);
                            }

                            $sprintf = '%016b%016b%016b%016b%016b%016b%016b%016b';
                        }
                        else {

                            $ip = explode('.', self::$cachedIP);
                            $sprintf = '%08b%08b%08b%08b';
                        }

                        $ip = vsprintf($sprintf, $ip);
                    }

                    // Split the netmask length off the network address
                    sscanf($proxyIPs[$i], '%[^/]/%d', $netaddr, $masklen);

                    // Again, an IPv6 address is most likely in a compressed form
                    if ($separator === ':') {

                        $netaddr = explode(':', str_replace('::', str_repeat(':', 9 - substr_count($netaddr, ':')), $netaddr));
                        for ($j = 0; $j < 8; $j++)
                        {
                            $netaddr[$j] = intval($netaddr[$j], 16);
                        }
                    }
                    else {

                        $netaddr = explode('.', $netaddr);
                    }

                    // Convert to binary and finally compare
                    if (strncmp($ip, vsprintf($sprintf, $netaddr), $masklen) === 0) {

                        self::$cachedIP = $spoof;
                        break;
                    }
                }
            }
        }

        return self::$cachedIP;
    }

    /**
     * Get Forward IP
     *
     * @return string Forward IP
     */
    public static function getRemoteIP()
    {
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : false;
    }

    /**
     * Get Forward IP
     *
     * @return string Forward IP
     */
    public static function getForwardIP()
    {
        // Match headers
        foreach (self::$headerKeys as $key => $headerKey) {

            if (isset($_SERVER[$headerKey])) {

                if (self::validateIP($_SERVER[$headerKey])) {
                    
                    return self::$cachedIP = $_SERVER[$headerKey];
                }
            }
        }

        // No matched IP from Proxy header
        return self::getRemoteIP();
    }

    /**
     * Validate IP
     *
     * @param string $ip
     * @return string|bool IP with validation
     */
    private static function validateIP($ip, $which = '')
    {
        switch (strtolower($which))
        {
            case 'ipv4':
                $which = FILTER_FLAG_IPV4;
                break;
            case 'ipv6':
                $which = FILTER_FLAG_IPV6;
                break;
            default:
                $which = NULL;
                break;
        }

        return (bool) filter_var($ip, FILTER_VALIDATE_IP, $which);
    }
}
