<?php

namespace totum\common\sql;

use IteratorAggregate;
use totum\config\Conf;


class SqlSuperlangPDODecorator implements IteratorAggregate
{

    protected $class;

    public function __construct($class, protected Conf $Config)
    {
        $this->class = $class;
    }

    public function __get($name)
    {
        return $this->class->{$name};
    }

    public function __set($name, $value)
    {
        $this->class->{$name} = $value;
    }

    public function __call($method, $arguments = [])
    {
        return call_user_func_array([$this->class, $method], $arguments);
    }

    public function render()
    {
        return 'decorator ' . $this->class->render();
    }

    public function fetchAll(...$arguments)
    {
        $data = $this->class->fetchAll(...$arguments);
        return $this->Config->superTranslate($data);
    }

    public function fetch(...$arguments)
    {
        $data = $this->class->fetch(...$arguments);
        return $this->Config->superTranslate($data);
    }

    public function getIterator()
    {
        $data = [];
        foreach ($this->class as $k => $v) {
            $data[$k] = $this->Config->superTranslate($v);
        }
        return (new \ArrayObject($data))->getIterator();
    }
}