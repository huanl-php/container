<?php

namespace HuanL\Container;

use ArrayAccess;
use Closure;
use ReflectionClass;
use ReflectionParameter;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionFunction;
use LogicException;

/**
 * 依赖注入容器
 * Class Container
 * @package HuanL\Container
 */
class Container implements IContainer, ArrayAccess {

    /**
     * 实例化列表
     * @var array
     */
    protected $instances = [];

    /**
     * 绑定列表
     * @var array
     */
    protected $bindings = [];

    /**
     * 别名列表
     * @var array
     */
    protected $aliases = [];

    /**
     * 全局的容器
     * @var Container
     */
    protected static $instance = null;

    public function __construct() {
        if (static::$instance == null) {
            static::setInstance($this);
        }
    }

    /**
     * 将抽象类型绑定到容器
     * @param string 抽象类型 $abstract
     * @param mixed 具体实例 $concrete
     * @param bool $unique
     * @return void
     */
    public function bind($abstract, $concrete = null, $unique = false) {
        //移除掉在列表中的实例和别名
        unset($this->instances[$abstract], $this->aliases[$abstract]);

        //如果具体数据为空,则绑定到抽象类型
        if (is_null($concrete)) {
            $concrete = $abstract;
        }

        //添加至列表
        $this->bindings[$abstract] = ['concrete' => $concrete, 'unique' => $unique];
    }

    /**
     * 绑定唯一的实例
     * @param string 抽象类型 $abstract
     * @param mixed 具体实例 $concrete
     * @return void
     */
    public function singleton($abstract, $concrete = null) {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * 添加一个实例到容器里面
     * @param $abstract
     * @param $instance
     * @return mixed
     */
    public function instance($abstract, $instance) {
        $this->instances[$abstract] = $instance;
        return $instance;
    }


    /**
     * 获取别名对应的抽象类型
     * @param $abstract
     * @return mixed
     */
    public function getAlias($abstract) {
        if (!isset($this->aliases[$abstract])) {
            return $abstract;
        }
        if ($this->aliases[$abstract] === $abstract) {
            throw new LogicException("[{$abstract}] is aliased to itself.");
        }
        return $this->getAlias($this->aliases[$abstract]);
    }

    /**
     * 解析抽象类型为具体实例
     * @param $abstract
     * @param array $parameter
     * @return mixed
     */
    public function make($abstract, array $parameter = []) {
        $abstract = $this->getAlias($abstract);
        //存在的实例直接返回
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        //不存在的实例,先获取具体的类型,然后构建
        $concrete = $this->getConcrete($abstract);
        $instance = $this->build($concrete, $parameter);

        //判断实例是否唯一,唯一的实例则加入实例列表
        if ($this->isUnique($abstract)) {
            $this->instances[$abstract] = $instance;
        }
        return $instance;
    }

    /**
     * 调用方法注入参数
     * @param mixed $callback
     * @param array $parameter
     * @return mixed
     */
    public function call($callback, array $parameter = []) {
        // TODO: Implement call() method.
        //判断输入的类型,允许4中方法 类名@方法 [实例,方法名] 函数名 匿名函数
        $reflection = $this->getMethodReflection($callback, $class, $type);

        //获取参数
        $dependencies = $reflection->getParameters();
        $args = $this->mergeDependencies($dependencies, $parameter);
        return $this->callMethod($callback, $args, $reflection, $class, $type);
    }

    /**
     * 调用参数
     * @param $callback
     * @param $args
     * @param $reflection
     * @param $class
     * @param int $type
     * @return mixed
     */
    protected function callMethod($callback, $args, $reflection, $class, int $type) {
        if ($type == 1) {
            return $reflection->invokeArgs($args);
        } else {
            return call_user_func_array([$class, $reflection->name], $args);
        }
    }

    /**
     * 获取方法反射类型,$type=1为函数,$type=2为 类名@方法 的方法
     * $type=3为 [实例/类名,方法名]
     * @param mixed $callbackm
     * @param mixed $class
     * @param int $type
     * @return ReflectionFunction|ReflectionMethod
     * @throws TypesException
     * @throws \ReflectionException
     */
    protected function getMethodReflection($callback, &$class = null, &$type = 0) {
        if (is_string($callback)) {
            //如果是字符串,判断是类名@方法的形式还是 函数名的形式
            if (($logoPos = strpos($callback, '@')) === false) {
                //寻找不到@为函数名的方式
                $reflection = new ReflectionFunction($callback);
                $type = 1;
            } else {
                $class = substr($callback, 0, $logoPos);
                $reflection = new ReflectionMethod($class,
                    substr($callback, $logoPos + 1));
                $class = $this->make($class);
                $type = 2;
            }
        } else if (is_array($callback) && sizeof($callback) == 2) {
            //数组则判断为 [实例/类名,方法名] 的形式
            $reflection = new ReflectionMethod($callback[0], $callback[1]);
            $class = $callback[0];
            $type = 3;
        } else if ($callback instanceof Closure) {
            //匿名函数还用想么?
            $reflection = new ReflectionFunction($callback);
            $type = 1;
        } else {
            throw new TypesException('Types not allowed to appear');
        }
        return $reflection;
    }

    /**
     * 获取全局实例
     * @return Container
     * @throws InstantiationException
     */
    public static function getInstance() {
        if (is_null(static::$instance)) {
            throw new InstantiationException('Empty instance');
        }
        return static::$instance;
    }

    /**
     * 设置全局实例
     * @param Container $container
     * @return Container
     */
    public static function setInstance(Container $container) {
        return static::$instance = $container;
    }

    /**
     * 实例化类型
     * @param $concrete
     * @param array $parameter
     * @return mixed|null|object
     * @throws InstantiationException
     * @throws \ReflectionException
     */
    protected function build($concrete, array $parameter = []) {
        //判断是不是匿名函数,是匿名函数则执行匿名函数并返回
        if ($concrete instanceof Closure) {
            return $concrete($this, $parameter);
        }
        //通过反射来获取类的参数
        $ref = new ReflectionClass($concrete);

        //判断是否可以实例化,不能实例化就炸裂吧,这是存放数据类型的容器
        if (!$ref->isInstantiable()) {
            throw new InstantiationException("Target [$concrete] is not instantiable.");
        }

        //获取实例化需要的依赖,无需参数则直接实例化返回
        $constructor = $ref->getConstructor();
        if (is_null($constructor)) {
            return new $concrete();
        }

        //获取依赖与传入的参数合并
        $dependencies = $constructor->getParameters();
        $args = $this->mergeDependencies($dependencies, $parameter);

        //实例化
        return $ref->newInstanceArgs($args);
    }

    /**
     * 给一个抽象类型加一个别名
     * @param $alias
     * @param $abstract
     */
    public function alias($alias, $abstract) {
        $this->aliases[$alias] = $abstract;
    }

    /**
     * 移除别名
     * @param $alias
     */
    public function removeAlias($alias) {
        unset($this->aliases[$alias]);
    }

    /**
     * 将依赖和传入的参数合并返回一个参数数组
     * @param ReflectionParameter $dependencies
     * @param array $parameter
     */
    protected function mergeDependencies($dependencies, array $parameter = []) {
        $args = [];
        foreach ($dependencies as $item) {
            //首先判断是否覆盖
            if (isset($parameter[$item->name])) {
                $args[] = $parameter[$item->name];
                continue;
            }

            //处理参数
            $args[] = $this->dealParam($item);
        }
        return $args;
    }

    /**
     * 处理参数,如果是抽象类型则make没有则返回默认值,再没有返回null
     * @param ReflectionParameter $reflectionParameter
     * @return mixed|null
     */
    protected function dealParam(ReflectionParameter $reflectionParameter) {
        //判断参数类型,如果是类则实例化它,如果有默认值则返回默认值,否则返回一个空的
        if (!is_null($reflectionParameter->getClass())) {
            return $this->make($reflectionParameter->getClass()->name);
        } else if ($reflectionParameter->isDefaultValueAvailable()) {
            return $reflectionParameter->getDefaultValue();
        }
        return null;
    }

    /**
     * 获取具体数据
     * @param $abstract
     * @return mixed
     */
    protected function getConcrete($abstract) {
        if (isset($this->bindings[$abstract]['concrete'])) {
            return $this->bindings[$abstract]['concrete'];
        }
        return $abstract;
    }

    /**
     * 判断是不是唯一实例
     * @param $abstract
     * @return bool
     */
    protected function isUnique($abstract) {
        if (isset($this->bindings[$abstract]['unique'])) {
            return $this->bindings[$abstract]['unique'];
        }
        return false;
    }

    /**
     * 别名是否存在
     * @param $name
     * @return bool
     */
    public function isAlias($name) {
        return isset($this->aliases[$name]);
    }

    public function offsetExists($key) {
        // TODO: Implement offsetExists() method.
        return isset($this->bindings[$key]) || isset($this->instances) || $this->isAlias($key);
    }

    public function offsetGet($key) {
        // TODO: Implement offsetGet() method.
        return $this->make($key);
    }

    public function offsetSet($key, $value) {
        // TODO: Implement offsetSet() method.
        $this->bind($key, $value);
    }

    public function offsetUnset($key) {
        // TODO: Implement offsetUnset() method.
        unset($this->bindings[$key], $this->instances[$key]);
    }
}