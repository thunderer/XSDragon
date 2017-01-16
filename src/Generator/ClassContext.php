<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Generator;

final class ClassContext
{
    public $comment = '';
    public $name = '';
    public $namespace = '';
    public $fqcn = '';
    public $xmlName = '';
    public $xmlNamespace = '';

    public $uses = [];
    public $attributes = [];
    public $properties = [];
    public $ctorVars = [];
    public $ctorArgs = [];
    public $ctorAssigns = [];
    public $ctorChecks = [];
    public $getters = [];
    public $xmlGen = [];
    public $xmlGenAttrs = [];
    public $ctorVisibility = 'public';
    public $namedCtors = [];
}
