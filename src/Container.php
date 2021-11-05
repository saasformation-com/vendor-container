<?php

namespace SaaSFormation\Vendor\Container;

use Psr\Container\ContainerInterface;

class Container implements ContainerInterface
{
    private array $services = [];

    private function __construct()
    {
    }

    public static function createEmpty(): static
    {
        return new static();
    }

    public function addServicesFromConfig(string $path): static
    {
        $directory = new \RecursiveDirectoryIterator($path);
        $iterator = new \RecursiveIteratorIterator($directory);
        $files = array();
        foreach ($iterator as $info) {
            if(!in_array($info->getFilename(), ['.', '..'])) {
                $files[] = $info->getPathname();
            }
        }

        foreach($files as $file) {
            $json = json_decode(file_get_contents($file), true);
            foreach($json["services"] as $service) {
                $className = $service["service"];
                $arguments = $service["arguments"] ?? [];
                $arguments = array_map(function($argument) {
                    if(is_string($argument) && str_starts_with($argument, "@")) {
                        $argument = $this->get($argument);
                    }

                    return $argument;
                }, $arguments);

                $reflection = new \ReflectionClass($className);
                $this->services[$service["name"]] = $reflection->newInstanceArgs($arguments);
            }
            foreach($json["aliases"] as $alias) {
                $this->services[$alias["interface"]] = $this->get($alias["service"]);
            }
        }

        return $this;
    }

    public function addService(string $name, object $service): static
    {
        $this->services[$name] = $service;

        return $this;
    }

    public function get(string $id)
    {
        if($this->has($id)) {
            return $this->services[$id];
        } else {
            if(!class_exists($id)) {
                if(interface_exists($id)) {
                    throw new NotFoundException("$id interface has no service defined");
                } else {
                    throw new NotFoundException("$id service has not been found");
                }
            }

            $reflection = new \ReflectionClass($id);
            $constructorParams = [];
            $constructorParamsClasses = $reflection->getConstructor()?->getParameters() ?? [];
            foreach ($constructorParamsClasses AS $constructorParamsClass) {
                $constructorParams[] = $this->get($constructorParamsClass->getType()?->getName());
            }

            $this->services[$id] = $service = $reflection->newInstanceArgs($constructorParams);

            return $service;
        }
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }
}
