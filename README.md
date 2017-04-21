# Simple IoC container with autowiring

<dl>
<dt>Q: What is it?</dt>
<dd>A: Simple IoC (DI) container with autowiring and lazy-load. Written in php.</dd>
<dt>Q: What it's for?</dt>
<dd>A: To do things like this, for instance

```php
interface AInterface { /* ... */ }

class A {
    public function __construct(B $b) { /* ... */ }
    // ...
}

class B {
    public function __construct() { /* ... */ }
    // ...
}

class C {
    public function __construct(AInterface $a, $variable = false) { /* ... */ }
    // ...
    public function run() { /* ... */ }
}

// ...

$c = new IocContainer();
$c->set('AInterface', 'A');
$c->set('C', 'C', ['variable' => true]);

$c['C']->run();
// ...
```

$c['C'] will be instance of class C with all constructor dependencies resolved recursively.
</dt>
<dt>Q: Stability?</dt>
<dd>A: Dev. Needs testing</dd>
</dl>

## Container initialization

```php
$container = new iocContainer();
```
or 

```php
$container = new iocContainer([
  'config' => new config(),
  'logger' => 'logger',
  'response' => new response(),
  // ...
]);
```

or 
```php
$services = [
    'regular' => [
        'config' => new config(),
        // ...
    ],
    'lazy' => [
        /**
         * @param iocContainer $c
         * @return \Redis
         */
        'redis' => function($c) {
            $redis = new \Redis();
            $redis->pconnect(
                $c['config']->redis_host,
                $c['config']->redis_port
            );
            
            return $redis;
        },
        // ...
    ]
];

$container = new iocContainer($services);
```

## Services initialization

1. Simple class instance

```php
$container['Cam'] = new Cam();
```

2. Closure for service initialization

Parameter (lambda) will be container instance

```php
$container['Redis'] = function($c) {
  $redis = new Redis();
  $redis->pconnect('127.0.0.1', 6379);
  return $redis;
};
```

3. Closure with lazy load, parameters definition and autowiring

More complex example

```php
$container->setLazy('redisSlave',  function ($c) {
  $redis = new Redis();
  $redis->pconnect(
    $c['config']->redis_slave['host'],
    $c['config']->redis_slave['port']
  );
 
  return $redis;
});

$container->setLazy('redisLocal',  function ($c) {
  $redis = new Redis();
  $redis->pconnect(
    $c['config']->redis_local['host'],
    $c['config']->redis_local['port']
  );
 
  return $redis;
});
```

this will be resolved here:

```php
class client {
    public function __construct(Redis $redisSlave, Redis $redisLocal, Redis $redis) {}
}

$container['client'] = 'client';
```

In clent::__constructor()

* $redisSlave will be instance of redis connected to slave ($container['redisSlave'])
* $redisLocal will be instance of local redis ($container['redisLocal'])
* $redis will be newly instantiated redis
 
4. Autowiring example #2

```php
class client {
    public function __construct(Redis $redisSlave, Redis $redisLocal, $useCookie = false) {}
}

$container->set('client', 'client', ['useCookie' => true]);
```

5. Autowiring example #3

```php
class cam {
    public function __construct(Redis $redisLocal) {}
}

class client {
    public function __construct(Redis $redisSlave, Redis $redisLocal, cam $cam, $useCookie = false) {}
}

$container->set('client', 'client', ['useCookie' => true]);
```

Note: class 'cam' not in container. It will be instantiated and its constructor dependency  resolved (redisLocal)

## Services usage

```php
$container->resolve('redisLocal')->set('xxxx', 1);
$container['validator']->validate();
```
