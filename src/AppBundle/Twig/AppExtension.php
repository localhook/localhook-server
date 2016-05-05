<?php

namespace AppBundle\Twig;

use Twig_Extension;
use Twig_SimpleFilter;
use Twig_SimpleFunction;

class AppExtension extends Twig_Extension
{
    public function getFilters()
    {
        return array(
            new Twig_SimpleFilter('json_decode', 'json_decode'),
        );
    }

    public function getFunctions()
    {
        return array(
            new Twig_SimpleFunction('parse_str',  array($this, 'parseStr')),
        );
    }

    public function parseStr($string) {
        parse_str($string, $array);
        if ($array && count($array) && array_values($array)[0]) {
            return $array;
        }
        return null;
    }

    public function getName()
    {
        return 'app_extension';
    }
}
