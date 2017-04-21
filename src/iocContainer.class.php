<?php

/**
 * Простой IoC контейнер. Использование:
 *
 *  Инициализация контейнера
 *
 * ```php
 * $container = new iocContainer();
 * ```
 *
 * 1a. Инициализация сервисов через конструктор
 *
 * ```php
 * $this->container = new iocContainer([
 *   'config' => new config(),
 *   'logger' => 'logger',
 *   'response' => new response(),
 *   // ...
 * ]);
 * ```
 *
 * 1.1. Простой инстанс класса
 *
 * ```php
 * $container['Cam'] = new Cam();
 * ```
 *
 * 1.2. Closure для инициализации сервиса
 *
 * Параметром в лямбда-функцию будет передан инстанс контейнера
 *
 * ```php
 * $container['Redis'] = function($c) {
 *   $redis = new Redis();
 *   $redis->pconnect('127.0.0.1', 6379);
 *
 *   return $redis;
 * };
 * ```
 *
 * 1.3. Ленивая инициализация сервиса
 *
 * Сервис будет инициализирован только при непосредственном обращении к нему.
 * Параметром в лямбда-функцию будет передан инстанс контейнера.
 *
 * ```php
 * $this->container->setLazy('redisSlave',  function ($c) {
 *   $redis = new Redis();
 *   $redis->pconnect(
 *     $this->container['config']->redis_slave['host'],
 *     $this->container['config']->redis_slave['port']
 *   );
 *
 *   return $redis;
 * });
 * ```
 *
 * Для класса authParamsValidator
 *
 * ```php
 * public function __construct(Redis $redis, $useCookie = false)
 * ```
 *
 * 1.3. Autowiring 1
 *
 * В конструктор автоматически будет внедрен инстанс редиса
 *
 * ```php
 * $container->set('validator', 'authParamsValidator');
 * ```
 *
 * 1.4. Autowiring 2
 *
 * В конструктор автоматически будет внедрен:
 * - инстанс редиса
 * - параметр useCookie(=true)
 *
 * ```php
 * $container->set('validator', 'authParamsValidator', ['useCookie' => true]);
 * ```
 *
 *  1.5. Autowiring 3
 * Для класса authParamsValidator
 *
 * ```php
 * public function __construct(Redis $redis, Cam $cam, $useCookie = false)
 * ```
 *
 * В конструктор автоматически будет внедрен:
 * - инстанс редиса
 * - новый экземпляр класса Cam (также будет задействован механизм autowiring для его инициалищации)
 * - параметр useCookie
 *
 * ```php
 * $container->set('validator', 'authParamsValidator', ['useCookie' => false]);
 * ```
 *
 * 1.6. Autowiring 4 - по имени переменной и типу
 *
 * ```php
 * // ...
 * $this->container['redisLocal'] = function ($c) {
 *   $redis = new Redis();
 *   $redis->pconnect(
 *     $c['config']->redis_local['host'],
 *     $c['config']->redis_local['port']
 *   );
 *
 *   return $redis;
 * };
 *
 * $this->container['redisSlave'] = function ($c) {
 *   $redis = new Redis();
 *   $redis->pconnect(
 *     $c['config']->redis_slave['host'],
 *     $c['config']->redis_slave['port']
 *   );
 *
 *   return $redis;
 * };
 *
 * // ...
 *
 * public function __construct(Redis $redisLocal = null, Redis $redisSlave, Redis $redisXXX, $ro = false)
 * {
 *   $this->redis1 = $redisLocal; // <- будет инстанс redisLocal
 *   $this->redis2 = $redisSlave; // <- будет инстанс redisSlave
 *   $redisXXX; // <- будет просто new Redis()
 * ```
 *
 * 2. Использование сервиса из контейнера
 *
 * ```php
 * $container->resolve('redis')->set('xxxx', 1);
 * $container['validator']->validate();
 * ```
 *
 * @category DI
 *
 * @author Vladimir Chmil <vladimir.chmil@gmail.com>
 */
class iocContainer implements \ArrayAccess
{
    /**
     * @var iocContainer
     */
    protected static $instance;

    /**
     * @var array
     */
    protected $services = [];

    /**
     * @var injector
     */
    protected $injector;

    /**
     * @var array
     */
    protected $lazy = [];

    /**
     * iocContainer constructor.
     */
    public function __construct(array $objects = [])
    {
        self::$instance = $this;

        $this->injector = new injector();

        foreach ($objects as $key => $object) {
            $this->offsetSet($key, $object);
        }
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    /**
     * Добавление сервиса в контейнер
     *
     * @param $service название сервиса в контейнере
     * @param string|Closure|mixed $instance инстанс класса|Closure|строка (имя класса)
     * @param array $optionalParams опциональные параметры конструктора класса для autowiring
     * @param bool $lazy если true - для сервиса будет исп. lazy load
     */
    public function set($service, $instance, $optionalParams = [], $lazy = false)
    {
        if (isset($this->services[$service])) {
            // сервис уже есть
            return;
        }

        if (is_object($instance) && $instance instanceof Closure) {
            // передан closure
            $inst = null;
            if ($lazy) {
                // lazy load - объект будет инстанциирован при получении
                $this->lazy[$service] = true;
                $inst = $instance;
            } else {
                $inst = call_user_func($instance);
            }
            $this->services[$service] = $inst;

        } elseif (is_object($instance)) {
            // просто передан инстанс класса
            $this->services[$service] = $instance;
        } elseif (is_string($instance)) {
            // autowiring
            $this->services[$service] = $this->injector
                ->inject($this, $instance, $optionalParams);
        }
    }

    /**
     * @return iocContainer
     */
    public static function instance()
    {
        return self::$instance;
    }

    /**
     * @param mixed $offset
     * @return bool|object
     */
    public function offsetGet($offset)
    {
        return $this->resolve($offset);
    }

    /**
     * Метод возвращает сервис из контейнера
     *
     * @param $service имя сервиса
     *
     * @return object|null
     */
    public function resolve($service)
    {
        if ($this->offsetExists($service)) {
            if (isset($this->lazy[$service]) && $this->services[$service] instanceof Closure) {
                // lazy load
                return call_user_func_array($this->services[$service], [$this]);
            }

            return $this->services[$service];
        }

        return null;
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->services[$offset]);
    }

    /**
     * Добавление сервиса в контейнер. Сервис будет lazy loaded
     *
     * Метод - синтаксический сахар для set(..., true)
     *
     * @param $service
     * @param $instance
     * @param array $optionalParams
     */
    public function setLazy($service, $instance, $optionalParams = [])
    {
        $this->set($service, $instance, $optionalParams, true);
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        if ($this->offsetExists($offset)) {
            unset($this->services[$offset]);
        }
    }
}