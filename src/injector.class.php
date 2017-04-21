<?php

/**
 * В данном классе реализован механизм autowiring'а
 *
 * @category DI
 *
 * @author Vladimir Chmil <vladimir.chmil@gmail.com>
 */
class injector
{
    public function inject($container, $className, $additionalParams = [])
    {
        $reflection = new ReflectionClass($className);

        if (!($construct = $reflection->getConstructor())) {
            // у класса нет конструктора - нечего инжектить; возвращаем новый инстанс класса
            return new $className;
        }

        $srcParams = $construct->getParameters();

        $params = [];
        foreach ($srcParams as $i => $paramRef) {
            // 1. Параметр
            if (!($class = $paramRef->getClass())) {
                // параметр - не класс, ищем в optionalParameters
                if (isset($additionalParams[$paramRef->name])) {
                    $params[$i] = $additionalParams[$paramRef->name];
                }
                continue;
            }
            $className = $class->getName();

            // 2. Класс из контейнера по имени переменной
            if ($classInstance = $container[$paramRef->name]) {
                if ($classInstance instanceof $className) {
                    $params[$i] = $classInstance;
                    continue;
                }
            }

            // 3. Класс по типу (type-hint)
            if (!($classInstance = $container[$className])) {
                // класса в контейнере нет - создаем новый инстанс
                $params[$i] = $this->inject($container, $className);
                continue;
            }

            // берем класс из контейнера
            $params[$i] = $classInstance;
        }

        return $reflection->newInstanceArgs($params);
    }
}
