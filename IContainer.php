<?php


namespace HuanL\Container;


interface IContainer {
    //将抽象类型绑定到容器
    public function bind($abstract, $concrete = null, $unique = false);

    //绑定单例
    public function singleton($abstract, $concrete = null);

    //添加实例
    public function instance($abstract, $instance);

    //解析抽象类型
    public function make($abstract, $parameter = []);
}