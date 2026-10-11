<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Kernel\CoroutinesSupportingKernel;

class Kernel extends BaseKernel implements WarmableInterface
{
    use CoroutinesSupportingKernel;
    use MicroKernelTrait {
        CoroutinesSupportingKernel::initializeContainer insteadof MicroKernelTrait;
        CoroutinesSupportingKernel::getContainerBaseClass insteadof MicroKernelTrait;
    }
}
