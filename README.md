O2Session
=====
[![Latest Stable Version](https://poser.pugx.org/o2system/o2session/v/stable)](https://packagist.org/packages/o2system/o2session) [![Total Downloads](https://poser.pugx.org/o2system/o2session/downloads)](https://packagist.org/packages/o2system/o2session) [![Latest Unstable Version](https://poser.pugx.org/o2system/o2session/v/unstable)](https://packagist.org/packages/o2system/o2session) [![License](https://poser.pugx.org/o2system/o2session/license)](https://packagist.org/packages/o2system/o2session)

[O2Session](https://github.com/circlecreative/o2session) is an Open Source Native PHP Session Management Handler Library. 
Allows different storage engines to be used. 
All but file-based storage require specific server requirements, and a Fatal Exception will be thrown if server requirements are not met. 
Another amazing product from Circle Creative, released under MIT License.

[O2Session](https://github.com/circlecreative/o2session) is build for working more powerfull with [O2System Framework](https://github.com/circlecreative/o2system), but also can be used for integrated with others as standalone version with limited features.

[O2Session](https://github.com/circlecreative/o2session) is insipired by [CodeIgniter](http://codeigniter.com) Session Driver, so [O2Session](https://github.com/circlecreative/o2session) is has same method functions.

### Supported Storage Engines Handlers
| Engine | Support | Tested  | &nbsp; |
| ------------- |:-------------:|:-----:| ----- |
| APC | ```Yes``` | ```Yes``` | http://php.net/apc |
| eAccelerator | ```Soon``` | ```Soon``` | http://eaccelerator.net |
| File | ```Yes``` | ```Yes``` | http://php.net/file |
| Memcached | ```Yes``` | ```Yes``` | http://php.net/memcached |
| MongoDB | ```Soon``` | ```Soon``` | https://www.mongodb.com |
| Zend OPCache | ```Soon``` | ```Soon``` | http://php.net/opcache |
| Redis | ```Yes``` | ```Yes``` | http://redis.io |
| SSDB | ```Soon``` | ```Soon``` | http://ssdb.io |
| Wincache | ```Yes``` | ```Yes``` | http://php.net/wincache
| XCache | ```Yes``` | ```Yes``` | https://xcache.lighttpd.net/ |

### Composer Instalation
The best way to install O2Session is to use [Composer](https://getcomposer.org)
```
composer require o2system/o2session
```
> Packagist: [https://packagist.org/packages/o2system/o2session](https://packagist.org/packages/o2system/o2session)

### Usage Example
```php
use O2System\Session;

// Initialize O2Session Instance using APC Storage Engine
$session = new Session(['handler' => 'apc']);

// Set session userdata
$session->set('foo', ['bar' => 'something']);

// Get session userdata
$foo = $session->get('foo');
```
> More details at the [Documentation](https://www.gitbook.com/book/circlecreative/o2session).

### Ideas and Suggestions
Please kindly mail us at [o2system.framework@gmail.com](mailto:o2system.framework@gmail.com])

### Bugs and Issues
Please kindly submit your [issues at Github](https://github.com/circlecreative/o2session/issues) so we can track all the issues along development.

### System Requirements
- PHP 5.5+
- [Composer](https://getcomposer.org)
- [O2System Glob (O2Glob)](https://github.com/circlecreative.com/o2glob)
- [O2System Database (O2DB)](https://github.com/circlecreative.com/o2db)

### Credits
|Role|Name|
|----|----|
|Founder and Lead Projects|[Steeven Andrian Salim (steevenz.com)](http://steevenz.com)|
|Documentation|[Steeven Andrian Salim](http://steevenz.com), [Ayun G. Wibowo](http://ayun.co)|
> Special Thanks To: [Yudi Primaputra (CTO - PT. YukBisnis Indonesia)](http://yukbisnis.com/xpartacvs)
