<?php


#[\Attribute(\Attribute::TARGET_METHOD)]
class MethodAttr {
}

#[\Attribute]
class GenericAttr {
    public function __construct($name = null) {

    }
}

#[GenericAttr(name: 'example')]
class Decorated {

    #[MethodAttr]
    public function foo () {
    }

    public function bar (#[GenericAttr] string $ding) {
    }
}