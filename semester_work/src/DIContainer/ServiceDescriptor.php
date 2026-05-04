<?php

namespace MyApp\DIContainer;

class ServiceDescriptor
{
    public string $serviceType;
    public string $implementationType;
    public object $implementationInstance;
    public ServiceLifetime $serviceLifetime;
}
