<?php

use Hyperf\Context\ApplicationContext;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSourceFactory;

return ApplicationContext::setContainer(new Container((new DefinitionSourceFactory())()));
