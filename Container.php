<?php

namespace HuanL\Container;

use ArrayAccess;
use Closure;
use ReflectionClass;
use ReflectionParameter;
use LogicException;

/**
 * 依赖注入容器
 * Class Container
 * @package HuanL\Container
 */
class Container implements IContainer,ArrayAccess {

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
    public function make($abstract, $parameter = []) {
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
     * 实例化类型
     * @param $concrete
     * @param array $parameter
     */
    protected function build($concrete, $parameter = []) {
        //判断是不是匿名函数,是匿名函数则执行匿名函数并返回
        if ($concrete instanceof Closure) {
            return $concrete($this, $parameter);
        }
        //通过反射来获取类的参数
        $ref = new ReflectionClass($concrete);

        //判断是否可以实例化,不能实例化就炸裂吧,这是存放数据类型的容器
        if (!$ref->isInstantiable()) {
            throw new InstantiationException("Target [$concrete] is not instantiable.");
            return null;
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
     * @param $dependencies
     * @param array $parameter
     */
    protected function mergeDependencies($dependencies, $parameter = []) {
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