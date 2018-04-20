<?php

namespace HuanL\Container;

use ArrayAccess;
use Closure;
use ReflectionClass;
use ReflectionParameter;

/**
 * 依赖注入容器
 * Class Container
 * @package HuanL\Container
 */
class Container implements ArrayAccess {

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
     * 将数据绑定到容器
     * @param string 抽象类型 $abstract
     * @param mixed 具体数据 $concrete
     * @param bool $unique
     * @return void
     */
    public function bind($abstract, $concrete = null, $unique = false) {
        //如果具体数据为空,则绑定到抽象类型
        if (is_null($concrete)) {
            $concrete = $abstract;
        }

        //添加至列表
        $this->bindings[$abstract] = ['concrete' => $concrete, 'unique' => $unique];
    }

    /**
     * 绑定唯一的数据
     * @param string 抽象类型 $abstract
     * @param mixed 具体数据 $concrete
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
     * 解析抽象类型为具体数据(实例)
     * @param $abstract
     * @param array $parameter
     * @return mixed
     */
    public function make($abstract, $parameter = []) {
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
        //判断是不是匿名函数
        if ($concrete instanceof Closure) {
            return $concrete($this, $parameter);
        }
        //通过反射来获取类的参数
        $ref = new ReflectionClass($concrete);

        //判断是否可以实例化,不能实例化则认为这是一个数据返回
        if (!$ref->isInstantiable()) {
            return $concrete;
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
    public function getConcrete($abstract) {
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
    public function isUnique($abstract) {
        if (isset($this->bindings[$abstract]['unique'])) {
            return $this->bindings[$abstract]['unique'];
        }
        return false;
    }

    /**
     * 构建一个闭包
     * @param $abstract
     * @param $concrete
     * @return Closure
     */
    protected function getClosure($abstract, $concrete) {
        return function ($container, $parameter) use ($abstract, $concrete) {
//            if ($abstract)
        };
    }

    public function offsetExists($offset) {
        // TODO: Implement offsetExists() method.
    }

    public function offsetGet($offset) {
        // TODO: Implement offsetGet() method.
    }

    public function offsetSet($offset, $value) {
        // TODO: Implement offsetSet() method.
    }

    public function offsetUnset($offset) {
        // TODO: Implement offsetUnset() method.
    }
}