<?php

namespace AppBundle\Twig;

use Twig_Extension;
use Twig_SimpleFilter;

class AppExtension extends Twig_Extension
{
    public function getFilters()
    {
        return array(
            new Twig_SimpleFilter('json_decode', 'json_decode'),
        );
    }

    public function getName()
    {
        return 'app_extension';
    }
}
