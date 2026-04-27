<?php

namespace MyApp\DIContainer;

enum ServiceLifetime
{
    case Singleton;
    case Scoped;
    case Transient;

}
