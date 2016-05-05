<?php

namespace AppBundle\Twig;

use Twig_Extension;
use Twig_SimpleFilter;
use Twig_SimpleFunction;

class AppExtension extends Twig_Extension
{
    public function getFilters()
    {
        return [];
    }

    public function getFunctions()
    {
        return [];
    }

    public function getName()
    {
        return 'app_extension';
    }
}
