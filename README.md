*php* Client IP
===============

Get client IP with safe and coincident way from server even behind Proxy or Load-Balancer.

[![Latest Stable Version](https://poser.pugx.org/yidas/client-ip/v/stable?format=flat-square)](https://packagist.org/packages/yidas/client-ip)
[![Latest Unstable Version](https://poser.pugx.org/yidas/client-ip/v/unstable?format=flat-square)](https://packagist.org/packages/yidas/client-ip)
[![License](https://poser.pugx.org/yidas/client-ip/license?format=flat-square)](https://packagist.org/packages/yidas/client-ip)

Real IP implement on Web application, which solve the problem that the server receiving requests through trust proxies or load-balancers without Transparent-Mode.

---

DEMONSTRATION
-------------

```php
echo ClientIP::get();
ClientIP::config([
    'proxyIPs' => ['192.168.0.0/16', '172.217.3.11'],
    'headerKeys' => ['HTTP_X_FORWARDED_FOR']
    ]);
echo ClientIP::get();
```

If the client IP is `203.169.1.37`, there are some connection situation for demonstrating referring by above sample code:

### Load-Balancer normal network

your server is behind a Load-Balencer and in a private network.

| Client         | Load-Balancer  | Server        |
|:--------------:|:--------------:|:-------------:|
| 203.169.1.37 → | 172.217.2.88 ↓ |               |
|                | 192.168.0.10 → | 192.168.4.100 |

```php
ClientIP::config([
    'proxyIPs' => true
    ]);
```

Setting `proxyIPs` as `true` means all requests are go through Load-balancer, which will always get forward IP, same as above setting:

```php
ClientIP::config([
    'proxyIPs' => ['0.0.0.0/32']
    ]);
```

**The result from the server:**

```
192.168.0.10 //Before setting the config
203.169.1.37 //After setting the config, get the forward IP
```

### Proxy optional network

If your server is in public network, not only receives requests directly, but also supports trust proxies for going through:

|     | Client         | Proxy          | Server        |
|:---:|:--------------:|:--------------:|:-------------:|
|Way 1| 203.169.1.37 → |                | 172.217.4.100 |
|Way 2| 203.169.1.37 → | 172.217.2.89 ↓ |               |
|     |                | 172.217.3.11 → | 172.217.4.100 |

```php
ClientIP::config([
    'proxyIPs' => ['172.217.3.11']
    ]);
```

**The result from the server**

- Way 1: Client connect to server directly:

```
203.169.1.37 //Before setting the config
203.169.1.37 //The request IP is not from proxyIPs, so identify as a Client.
```

- Way 2: Client connect to server through Proxy:

```
172.217.3.11 //Before setting the config
203.169.1.37 //The request IP comes from proxyIPs, get the forward IP.
```


---

INSTALLATION
------------

Run Composer in your project:

    composer require yidas/client-ip
    
Then initialize it at the bootstrap of application such as `config` file:

```php
require __DIR__ . '/vendor/autoload.php';
ClientIP::config([
   'proxyIPs' => ['192.168.0.0/16']
   ]);
```

---

CONFIGURATION
-------------

Example configuration:

```php
ClientIP::config([
   'proxyIPs' => ['192.168.0.0/16', '172.217.2.89'],
   'headerKeys' => ['HTTP_X_FORWARDED_FOR'],
   ]);
```

| Attribute | Type  | Description |
|-----------|-------|-------------|
|proxyIPs   |array  |Trust Proxies' IP list, which support subnet mask for each IP set.|
|headerKeys |array  |Header Key list for IP Forward.|


